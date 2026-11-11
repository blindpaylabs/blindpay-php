<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Customers\BusinessWithStandardKYB;
use BlindPay\SDK\Resources\Customers\IndividualWithEnhancedKYC;
use BlindPay\SDK\Resources\Customers\IndividualWithStandardKYC;
use BlindPay\SDK\Resources\Customers\KycType;
use BlindPay\SDK\Resources\Customers\ListCustomersResponse;
use BlindPay\SDK\Types\AccountClass;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class CustomersTest extends TestCase
{
    private BlindPay $blindpay;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler;
        $this->blindpay = new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000');
        (new ReflectionProperty(BlindPay::class, 'httpClient'))
            ->setValue($this->blindpay, new Client(['handler' => HandlerStack::create($this->mockHandler)]));
    }

    private function mockResponse(array $body): void
    {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], json_encode($body)));
    }

    /** CustomerOut with only the fields the spec requires (no address, documents or dates). */
    private function customer(array $overrides = []): array
    {
        return array_merge([
            'id' => 'cu_000000000000',
            'customer_id' => 'cu_000000000000',
            'type' => 'individual',
            'kyc_type' => 'standard',
            'kyc_status' => 'verifying',
            'email' => 'jane@example.com',
            'country' => 'US',
            'instance_id' => 'in_000000000000',
            'limit' => ['per_transaction' => 100000, 'daily' => 200000, 'monthly' => 1000000],
        ], $overrides);
    }

    #[Test]
    public function it_gets_an_enhanced_kyc_customer_without_the_legacy_holding_document(): void
    {
        // individual_holding_doc_front_file is not part of CustomerOut; reading it threw before.
        $this->mockResponse($this->customer(['kyc_type' => 'enhanced', 'purpose_of_transactions' => null]));

        $response = $this->blindpay->customers->get('cu_000000000000');

        $this->assertTrue($response->isSuccess(), (string) $response->error?->message);
        $this->assertInstanceOf(IndividualWithEnhancedKYC::class, $response->data);
        $this->assertSame(KycType::ENHANCED, $response->data->kycType);
        $this->assertNull($response->data->individualHoldingDocFrontFile);
        $this->assertNull($response->data->purposeOfTransactions);
        $this->assertNull($response->data->taxId);
        $this->assertNull($response->data->createdAt);
    }

    #[Test]
    public function it_gets_a_light_kyc_customer(): void
    {
        $this->mockResponse($this->customer(['kyc_type' => 'light']));

        $response = $this->blindpay->customers->get('cu_000000000000');

        $this->assertTrue($response->isSuccess(), (string) $response->error?->message);
        $this->assertInstanceOf(IndividualWithStandardKYC::class, $response->data);
        $this->assertSame(KycType::LIGHT, $response->data->kycType);
        $this->assertSame(AccountClass::INDIVIDUAL, $response->data->type);
    }

    #[Test]
    public function it_gets_a_business_customer_with_enhanced_kyb(): void
    {
        $this->mockResponse($this->customer(['type' => 'business', 'kyc_type' => 'enhanced', 'legal_name' => 'Acme LLC', 'owners' => null]));

        $response = $this->blindpay->customers->get('cu_000000000000');

        $this->assertTrue($response->isSuccess(), (string) $response->error?->message);
        $this->assertInstanceOf(BusinessWithStandardKYB::class, $response->data);
        $this->assertSame(AccountClass::BUSINESS, $response->data->type);
        $this->assertSame(KycType::ENHANCED, $response->data->kycType);
        $this->assertSame('Acme LLC', $response->data->legalName);
        $this->assertSame([], $response->data->owners);
    }

    #[Test]
    public function it_no_longer_fails_the_whole_list_page_for_one_unusual_customer(): void
    {
        $this->mockResponse([
            'data' => [
                $this->customer(),
                $this->customer(['id' => 'cu_000000000001', 'kyc_type' => 'light']),
                $this->customer(['id' => 'cu_000000000002', 'type' => 'business', 'kyc_type' => 'enhanced']),
                $this->customer(['id' => 'cu_000000000003', 'kyc_type' => 'future_kyc_level', 'business_industry' => '999999']),
            ],
            'pagination' => ['has_more' => false, 'next_page' => null, 'prev_page' => null],
        ]);

        $response = $this->blindpay->customers->list();

        $this->assertTrue($response->isSuccess(), (string) $response->error?->message);
        $this->assertInstanceOf(ListCustomersResponse::class, $response->data);
        $this->assertCount(4, $response->data->data);
        $this->assertSame('future_kyc_level', $response->data->data[3]->kycType);
    }
}
