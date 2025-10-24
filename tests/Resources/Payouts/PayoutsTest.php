<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Payouts\AuthorizeStellarTokenInput;
use BlindPay\SDK\Resources\Payouts\CreateEvmPayoutInput;
use BlindPay\SDK\Resources\Payouts\CreateStellarPayoutInput;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PayoutsTest extends TestCase
{
    private BlindPay $blindpay;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $this->blindpay = new BlindPay(
            apiKey: 'test-key',
            instanceId: 'in_000000000000'
        );

        $this->injectHttpClient($httpClient);
    }

    private function injectHttpClient(Client $client): void
    {
        $reflection = new ReflectionClass($this->blindpay);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($this->blindpay, $client);
    }

    private function mockResponse(array $body, int $status = 200): void
    {
        $this->mockHandler->append(
            new Response(
                $status,
                ['Content-Type' => 'application/json'],
                json_encode($body)
            )
        );
    }

    private function getPayoutMockData(): array
    {
        return [
            'receiver_id' => 're_000000000000',
            'id' => 'pa_000000000000',
            'status' => 'processing',
            'sender_wallet_address' => '0x123...890',
            'signed_transaction' => 'AAA...Zey8y0A',
            'quote_id' => 'qu_000000000000',
            'instance_id' => 'in_000000000000',
            'tracking_transaction' => [
                'step' => 'processing',
                'status' => 'failed',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_payment' => [
                'step' => 'on_hold',
                'provider_name' => 'blockchain',
                'provider_transaction_id' => '0x123...890',
                'provider_status' => 'canceled',
                'estimated_time_of_arrival' => '5_min',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_liquidity' => [
                'step' => 'processing',
                'provider_transaction_id' => '0x123...890',
                'provider_status' => 'deposited',
                'estimated_time_of_arrival' => '1_business_day',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_complete' => [
                'step' => 'on_hold',
                'status' => 'tokens_refunded',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_partner_fee' => [
                'step' => 'on_hold',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'created_at' => '2021-01-01T00:00:00Z',
            'updated_at' => '2021-01-01T00:00:00Z',
            'image_url' => 'https://example.com/image.png',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'legal_name' => 'Company Name Inc.',
            'network' => 'sepolia',
            'token' => 'USDC',
            'description' => 'Memo code or description, only works with USD and BRL',
            'sender_amount' => 1010,
            'receiver_amount' => 5240,
            'partner_fee_amount' => 150,
            'commercial_quotation' => 495,
            'blindpay_quotation' => 485,
            'total_fee_amount' => 1.5,
            'receiver_local_amount' => 1000,
            'currency' => 'BRL',
            'transaction_document_file' => 'https://example.com/image.png',
            'transaction_document_type' => 'invoice',
            'transaction_document_id' => '1234567890',
            'name' => 'Bank Account Name',
            'type' => 'wire',
            'pix_key' => '14947677768',
            'account_number' => '1001001234',
            'routing_number' => '012345678',
            'country' => 'US',
            'account_class' => 'individual',
            'address_line_1' => 'Address line 1',
            'address_line_2' => 'Address line 2',
            'city' => 'City',
            'state_province_region' => 'State/Province/Region',
            'postal_code' => 'Postal code',
            'account_type' => 'checking',
            'ach_cop_beneficiary_first_name' => 'Fernando',
            'ach_cop_bank_account' => '12345678',
            'ach_cop_bank_code' => '051',
            'ach_cop_beneficiary_last_name' => 'Guzman Alarcón',
            'ach_cop_document_id' => '1661105408',
            'ach_cop_document_type' => 'CC',
            'ach_cop_email' => 'fernando.guzman@gmail.com',
            'beneficiary_name' => 'Individual full name or business name',
            'spei_clabe' => '5482347403740546',
            'spei_protocol' => 'clabe',
            'spei_institution_code' => '40002',
            'swift_beneficiary_country' => 'MX',
            'swift_code_bic' => '123456789',
            'swift_account_holder_name' => 'John Doe',
            'swift_account_number_iban' => '123456789',
            'transfers_account' => 'BM123123123123',
            'transfers_type' => 'CVU',
            'has_virtual_account' => true,
        ];
    }

    #[Test]
    public function it_lists_payouts(): void
    {
        $mockedPayouts = [
            'data' => [
                $this->getPayoutMockData(),
            ],
            'pagination' => [
                'has_more' => true,
                'next_page' => 3,
                'prev_page' => 1,
            ],
        ];

        $this->mockResponse($mockedPayouts);

        $response = $this->blindpay->payouts->list();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data->data);
        $this->assertCount(1, $response->data->data);
        $this->assertEquals('pa_000000000000', $response->data->data[0]->id);
        $this->assertEquals('re_000000000000', $response->data->data[0]->receiverId);
        $this->assertEquals('0x123...890', $response->data->data[0]->senderWalletAddress);
        $this->assertTrue($response->data->pagination->hasMore);
        $this->assertEquals(3, $response->data->pagination->nextPage);
        $this->assertEquals(1, $response->data->pagination->prevPage);
    }

    #[Test]
    public function it_gets_a_payout(): void
    {
        $mockedPayout = $this->getPayoutMockData();

        $this->mockResponse($mockedPayout);

        $response = $this->blindpay->payouts->get('pa_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('pa_000000000000', $response->data->id);
        $this->assertEquals('re_000000000000', $response->data->receiverId);
        $this->assertEquals('processing', $response->data->status->value);
        $this->assertEquals('0x123...890', $response->data->senderWalletAddress);
        $this->assertEquals('AAA...Zey8y0A', $response->data->signedTransaction);
        $this->assertEquals('qu_000000000000', $response->data->quoteId);
        $this->assertEquals('in_000000000000', $response->data->instanceId);
        $this->assertEquals('John', $response->data->firstName);
        $this->assertEquals('Doe', $response->data->lastName);
        $this->assertEquals('Company Name Inc.', $response->data->legalName);
        $this->assertEquals('sepolia', $response->data->network->value);
        $this->assertEquals('USDC', $response->data->token->value);
        $this->assertEquals(1010.0, $response->data->senderAmount);
        $this->assertEquals(5240.0, $response->data->receiverAmount);
        $this->assertEquals('BRL', $response->data->currency->value);
        $this->assertEquals('Bank Account Name', $response->data->name);
        $this->assertEquals('wire', $response->data->type->value);
        $this->assertTrue($response->data->hasVirtualAccount);
    }

    #[Test]
    public function it_exports_payouts(): void
    {
        $mockedExportPayouts = [
            $this->getPayoutMockData(),
        ];

        $this->mockResponse($mockedExportPayouts);

        $response = $this->blindpay->payouts->export();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(1, $response->data);
        $this->assertEquals('pa_000000000000', $response->data[0]->id);
        $this->assertEquals('re_000000000000', $response->data[0]->receiverId);
        $this->assertEquals('processing', $response->data[0]->status->value);
        $this->assertEquals('0x123...890', $response->data[0]->senderWalletAddress);
    }

    #[Test]
    public function it_gets_payout_tracking_information(): void
    {
        $mockedPayoutTrack = $this->getPayoutMockData();

        $this->mockResponse($mockedPayoutTrack);

        $response = $this->blindpay->payouts->getTrack('pa_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('pa_000000000000', $response->data->id);
        $this->assertEquals('re_000000000000', $response->data->receiverId);
        $this->assertEquals('processing', $response->data->status->value);
        $this->assertEquals('processing', $response->data->trackingTransaction->step);
        $this->assertEquals('failed', $response->data->trackingTransaction->status);
        $this->assertEquals('on_hold', $response->data->trackingPayment->step);
        $this->assertEquals('blockchain', $response->data->trackingPayment->providerName);
        $this->assertEquals('processing', $response->data->trackingLiquidity->step);
        $this->assertEquals('deposited', $response->data->trackingLiquidity->providerStatus);
        $this->assertEquals('on_hold', $response->data->trackingComplete->step);
        $this->assertEquals('tokens_refunded', $response->data->trackingComplete->status);
        $this->assertEquals('on_hold', $response->data->trackingPartnerFee->step);
    }

    #[Test]
    public function it_authorizes_stellar_token(): void
    {
        $mockedAuthorizeToken = [
            'transaction_hash' => 'string',
        ];

        $this->mockResponse($mockedAuthorizeToken);

        $input = new AuthorizeStellarTokenInput(
            quoteId: 'qu_000000000000',
            senderWalletAddress: '0x123...890'
        );

        $response = $this->blindpay->payouts->authorizeStellarToken($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('string', $response->data->transactionHash);
    }

    #[Test]
    public function it_creates_a_stellar_payout(): void
    {
        $mockedStellarPayout = [
            'id' => 'pa_000000000000',
            'status' => 'processing',
            'sender_wallet_address' => '0x123...890',
            'tracking_complete' => [
                'step' => 'on_hold',
                'status' => 'tokens_refunded',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_payment' => [
                'step' => 'on_hold',
                'provider_name' => 'blockchain',
                'provider_transaction_id' => '0x123...890',
                'provider_status' => 'canceled',
                'estimated_time_of_arrival' => '5_min',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_transaction' => [
                'step' => 'processing',
                'status' => 'failed',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_partner_fee' => [
                'step' => 'on_hold',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_liquidity' => [
                'step' => 'processing',
                'provider_transaction_id' => '0x123...890',
                'provider_status' => 'deposited',
                'estimated_time_of_arrival' => '1_business_day',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'receiver_id' => 're_000000000000',
        ];

        $this->mockResponse($mockedStellarPayout);

        $input = new CreateStellarPayoutInput(
            quoteId: 'qu_000000000000',
            senderWalletAddress: '0x123...890'
        );

        $response = $this->blindpay->payouts->createStellar($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('pa_000000000000', $response->data->id);
        $this->assertEquals('processing', $response->data->status->value);
        $this->assertEquals('0x123...890', $response->data->senderWalletAddress);
        $this->assertEquals('re_000000000000', $response->data->receiverId);
        $this->assertNotNull($response->data->trackingComplete);
        $this->assertNotNull($response->data->trackingPayment);
        $this->assertNotNull($response->data->trackingTransaction);
        $this->assertNotNull($response->data->trackingPartnerFee);
        $this->assertNotNull($response->data->trackingLiquidity);
    }

    #[Test]
    public function it_creates_an_evm_payout(): void
    {
        $mockedEvmPayout = [
            'id' => 'pa_000000000000',
            'status' => 'processing',
            'sender_wallet_address' => '0x123...890',
            'tracking_complete' => [
                'step' => 'on_hold',
                'status' => 'tokens_refunded',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_payment' => [
                'step' => 'on_hold',
                'provider_name' => 'blockchain',
                'provider_transaction_id' => '0x123...890',
                'provider_status' => 'canceled',
                'estimated_time_of_arrival' => '5_min',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_transaction' => [
                'step' => 'processing',
                'status' => 'failed',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_partner_fee' => [
                'step' => 'on_hold',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_liquidity' => [
                'step' => 'processing',
                'provider_transaction_id' => '0x123...890',
                'provider_status' => 'deposited',
                'estimated_time_of_arrival' => '1_business_day',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'receiver_id' => 're_000000000000',
        ];

        $this->mockResponse($mockedEvmPayout);

        $input = new CreateEvmPayoutInput(
            quoteId: 'qu_000000000000',
            senderWalletAddress: '0x123...890'
        );

        $response = $this->blindpay->payouts->createEvm($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('pa_000000000000', $response->data->id);
        $this->assertEquals('processing', $response->data->status->value);
        $this->assertEquals('0x123...890', $response->data->senderWalletAddress);
        $this->assertEquals('re_000000000000', $response->data->receiverId);
        $this->assertNotNull($response->data->trackingComplete);
        $this->assertNotNull($response->data->trackingPayment);
        $this->assertNotNull($response->data->trackingTransaction);
        $this->assertNotNull($response->data->trackingPartnerFee);
        $this->assertNotNull($response->data->trackingLiquidity);
    }
}
