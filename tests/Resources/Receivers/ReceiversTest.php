<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Receivers\AccountPurpose;
use BlindPay\SDK\Resources\Receivers\BusinessType;
use BlindPay\SDK\Resources\Receivers\CreateReceiverInput;
use BlindPay\SDK\Resources\Receivers\IdentificationDocument;
use BlindPay\SDK\Resources\Receivers\KycStatus;
use BlindPay\SDK\Resources\Receivers\KycType;
use BlindPay\SDK\Resources\Receivers\LimitIncreaseRequest;
use BlindPay\SDK\Resources\Receivers\LimitIncreaseRequestSupportingDocumentType;
use BlindPay\SDK\Resources\Receivers\ListReceiversInput;
use BlindPay\SDK\Resources\Receivers\ListReceiversResponse;
use BlindPay\SDK\Resources\Receivers\ProofOfAddressDocType;
use BlindPay\SDK\Resources\Receivers\ReceiverOut;
use BlindPay\SDK\Resources\Receivers\RequestLimitIncreaseInput;
use BlindPay\SDK\Resources\Receivers\SourceOfFundsDocType;
use BlindPay\SDK\Resources\Receivers\UpdateReceiverInput;
use BlindPay\SDK\Types\AccountClass;
use BlindPay\SDK\Types\Country;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ReceiversTest extends TestCase
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

    private function receiverOutFixture(string $id, string $type = 'individual', string $kycType = 'standard', string $kycStatus = 'verifying'): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'kyc_type' => $kycType,
            'kyc_status' => $kycStatus,
            'kyc_warnings' => null,
            'fraud_warnings' => null,
            'email' => 'test@example.com',
            'country' => 'BR',
            'instance_id' => 'in_000000000000',
            'limit' => [
                'per_transaction' => 100000,
                'daily' => 200000,
                'monthly' => 1000000,
            ],
        ];
    }

    #[Test]
    public function it_lists_receivers(): void
    {
        $mockedResponse = [
            'data' => [
                $this->receiverOutFixture('re_Euw7HN4OdxPn'),
                $this->receiverOutFixture('re_YuaMcI2B8zbQ', 'individual', 'enhanced', 'approved'),
                $this->receiverOutFixture('re_IOxAUL24LG7P', 'business', 'standard', 'verifying'),
            ],
            'pagination' => [
                'has_more' => false,
                'next_page' => null,
                'prev_page' => null,
            ],
        ];

        $this->mockResponse($mockedResponse);

        $response = $this->blindpay->receivers->list();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertInstanceOf(ListReceiversResponse::class, $response->data);
        $this->assertCount(3, $response->data->data);
        $this->assertEquals('re_Euw7HN4OdxPn', $response->data->data[0]->id);
        $this->assertEquals('re_YuaMcI2B8zbQ', $response->data->data[1]->id);
        $this->assertEquals('re_IOxAUL24LG7P', $response->data->data[2]->id);
    }

    #[Test]
    public function it_lists_receivers_with_filters(): void
    {
        $mockedResponse = [
            'data' => [
                $this->receiverOutFixture('re_Euw7HN4OdxPn'),
            ],
            'pagination' => [
                'has_more' => false,
                'next_page' => null,
                'prev_page' => null,
            ],
        ];

        $this->mockResponse($mockedResponse);

        $params = new ListReceiversInput(
            status: KycStatus::VERIFYING,
            country: Country::BR
        );

        $response = $this->blindpay->receivers->list($params);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertInstanceOf(ListReceiversResponse::class, $response->data);
        $this->assertCount(1, $response->data->data);
        $this->assertEquals('re_Euw7HN4OdxPn', $response->data->data[0]->id);
    }

    #[Test]
    public function it_creates_a_receiver(): void
    {
        $mockedReceiver = [
            'id' => 're_Euw7HN4OdxPn',
        ];

        $this->mockResponse($mockedReceiver);

        $input = new CreateReceiverInput(
            type: AccountClass::INDIVIDUAL,
            kycType: KycType::STANDARD,
            email: 'bernardo.simonassi@gmail.com',
            country: Country::BR,
            firstName: 'Bernardo',
            lastName: 'Simonassi',
            dateOfBirth: '1998-02-02T00:00:00.000Z',
            taxId: '12345678900',
            addressLine1: 'Av. Paulista, 1000',
            addressLine2: 'Apto 101',
            city: 'São Paulo',
            stateProvinceRegion: 'SP',
            postalCode: '01310-100',
            phoneNumber: '+5511987654321',
            idDocCountry: Country::BR,
            idDocType: IdentificationDocument::PASSPORT,
            idDocFrontFile: 'https://example.com/image.png',
            proofOfAddressDocType: ProofOfAddressDocType::UTILITY_BILL,
            proofOfAddressDocFile: 'https://example.com/image.png',
            tosId: 'to_tPiz4bM2nh5K'
        );

        $response = $this->blindpay->receivers->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('re_Euw7HN4OdxPn', $response->data->id);
    }

    #[Test]
    public function it_creates_a_business_receiver(): void
    {
        $mockedReceiver = [
            'id' => 're_IOxAUL24LG7P',
        ];

        $this->mockResponse($mockedReceiver);

        $input = new CreateReceiverInput(
            type: AccountClass::BUSINESS,
            kycType: KycType::STANDARD,
            email: 'contato@empresa.com.br',
            country: Country::BR,
            legalName: 'Empresa Exemplo Ltda',
            alternateName: 'Exemplo',
            taxId: '20096178000195',
            addressLine1: 'Av. Brigadeiro Faria Lima, 400',
            addressLine2: 'Sala 1201',
            city: 'São Paulo',
            stateProvinceRegion: 'SP',
            postalCode: '04538-132',
            website: 'https://site.com/',
            businessType: BusinessType::LLC,
            accountPurpose: AccountPurpose::BUSINESS_EXPENSES,
            tosId: 'to_nppX66ntvtHs'
        );

        $response = $this->blindpay->receivers->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('re_IOxAUL24LG7P', $response->data->id);
    }

    #[Test]
    public function it_gets_a_receiver(): void
    {
        $mockedReceiver = array_merge(
            $this->receiverOutFixture('re_YuaMcI2B8zbQ', 'individual', 'enhanced', 'verifying'),
            [
                'kyc_warnings' => [
                    [
                        'code' => null,
                        'message' => null,
                        'resolution_status' => null,
                        'warning_id' => null,
                    ],
                ],
                'email' => 'bernardo.simonassi@gmail.com',
                'tax_id' => '12345678900',
                'address_line_1' => 'Av. Paulista, 1000',
                'address_line_2' => 'Apto 101',
                'city' => 'São Paulo',
                'state_province_region' => 'SP',
                'country' => 'BR',
                'postal_code' => '01310-100',
                'ip_address' => '127.0.0.1',
                'image_url' => 'https://example.com/image.png',
                'phone_number' => '+5511987654321',
                'proof_of_address_doc_type' => 'UTILITY_BILL',
                'proof_of_address_doc_file' => 'https://example.com/image.png',
                'first_name' => 'Bernardo',
                'last_name' => 'Simonassi',
                'date_of_birth' => '1998-02-02T00:00:00.000Z',
                'id_doc_country' => 'BR',
                'id_doc_type' => 'PASSPORT',
                'id_doc_front_file' => 'https://example.com/image.png',
                'id_doc_back_file' => 'https://example.com/image.png',
                'source_of_funds_doc_type' => 'savings',
                'source_of_funds_doc_file' => 'https://example.com/image.png',
                'purpose_of_transactions' => 'personal_or_living_expenses',
                'purpose_of_transactions_explanation' => 'I am receiving salary payments from my employer',
                'tos_id' => 'to_3ZZhllJkvo5Z',
                'created_at' => '2021-01-01T00:00:00.000Z',
                'updated_at' => '2021-01-01T00:00:00.000Z',
            ]
        );

        $this->mockResponse($mockedReceiver);

        $response = $this->blindpay->receivers->get('re_YuaMcI2B8zbQ');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertInstanceOf(ReceiverOut::class, $response->data);
        $this->assertEquals('re_YuaMcI2B8zbQ', $response->data->id);
        $this->assertEquals('bernardo.simonassi@gmail.com', $response->data->email);
        $this->assertEquals('Bernardo', $response->data->firstName);
        $this->assertEquals('Simonassi', $response->data->lastName);
        $this->assertEquals(KycType::ENHANCED, $response->data->kycType);
        $this->assertEquals(KycStatus::VERIFYING, $response->data->kycStatus);
        $this->assertEquals(AccountClass::INDIVIDUAL, $response->data->type);
    }

    #[Test]
    public function it_updates_a_receiver(): void
    {
        $this->mockResponse(['data' => null]);

        $input = new UpdateReceiverInput(
            receiverId: 're_YuaMcI2B8zbQ',
            email: 'bernardo.simonassi@gmail.com',
            country: Country::BR,
            taxId: '12345678900',
            addressLine1: 'Av. Paulista, 1000',
            addressLine2: 'Apto 101',
            city: 'São Paulo',
            stateProvinceRegion: 'SP',
            postalCode: '01310-100',
            ipAddress: '127.0.0.1',
            imageUrl: 'https://example.com/image.png',
            phoneNumber: '+5511987654321',
            proofOfAddressDocType: ProofOfAddressDocType::UTILITY_BILL,
            proofOfAddressDocFile: 'https://example.com/image.png',
            firstName: 'Bernardo',
            lastName: 'Simonassi',
            dateOfBirth: '1998-02-02T00:00:00.000Z',
            idDocCountry: Country::BR,
            idDocType: IdentificationDocument::PASSPORT,
            idDocFrontFile: 'https://example.com/image.png',
            idDocBackFile: 'https://example.com/image.png',
            alternateName: 'Exemplo',
            formationDate: '2010-05-20T00:00:00.000Z',
            website: 'https://site.com',
            owners: [[
                'id' => 'ub_000000000000',
                'first_name' => 'Carlos',
                'last_name' => 'Silva',
                'role' => 'beneficial_owner',
                'date_of_birth' => '1995-05-15T00:00:00.000Z',
                'tax_id' => '12345678901',
                'address_line_1' => 'Rua Augusta, 1500',
                'address_line_2' => null,
                'city' => 'São Paulo',
                'state_province_region' => 'SP',
                'country' => 'BR',
                'postal_code' => '01304-001',
                'id_doc_country' => 'BR',
                'id_doc_type' => 'PASSPORT',
                'id_doc_front_file' => 'https://example.com/image.png',
                'id_doc_back_file' => 'https://example.com/image.png',
            ]],
            incorporationDocFile: 'https://example.com/image.png',
            proofOfOwnershipDocFile: 'https://example.com/image.png',
            sourceOfFundsDocType: SourceOfFundsDocType::SAVINGS,
            sourceOfFundsDocFile: 'https://example.com/image.png',
            purposeOfTransactions: \BlindPay\SDK\Resources\Receivers\PurposeOfTransactions::PERSONAL_OR_LIVING_EXPENSES,
            purposeOfTransactionsExplanation: 'I am receiving salary payments from my employer',
            externalId: 'some-external-id',
            tosId: 'to_3ZZhllJkvo5Z'
        );

        $response = $this->blindpay->receivers->update($input);

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertArrayHasKey('data', $response->data);
        $this->assertNull($response->data['data']);
    }

    #[Test]
    public function it_deletes_a_receiver(): void
    {
        $this->mockResponse(['data' => null]);

        $response = $this->blindpay->receivers->delete('re_YuaMcI2B8zbQ');

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertArrayHasKey('data', $response->data);
        $this->assertNull($response->data['data']);
    }

    #[Test]
    public function it_gets_receiver_limits(): void
    {
        $mockedReceiverLimits = [
            'limits' => [
                'payin' => [
                    'daily' => 10000,
                    'monthly' => 50000,
                ],
                'payout' => [
                    'daily' => 20000,
                    'monthly' => 100000,
                ],
            ],
        ];

        $this->mockResponse($mockedReceiverLimits);

        $response = $this->blindpay->receivers->getLimits('re_YuaMcI2B8zbQ');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsObject($response->data);
        $this->assertObjectHasProperty('limits', $response->data);
        $this->assertEquals(10000.0, $response->data->limits['payin']['daily']);
        $this->assertEquals(50000.0, $response->data->limits['payin']['monthly']);
        $this->assertEquals(20000.0, $response->data->limits['payout']['daily']);
        $this->assertEquals(100000.0, $response->data->limits['payout']['monthly']);
    }

    #[Test]
    public function it_gets_limit_increase_requests_for_a_receiver(): void
    {
        $mockedLimitIncreaseRequests = [
            [
                'id' => 'rl_000000000000',
                'receiver_id' => 're_YuaMcI2B8zbQ',
                'status' => 'in_review',
                'daily' => 50000,
                'monthly' => 250000,
                'per_transaction' => 25000,
                'supporting_document_file' => 'https://example.com/bank-statement.pdf',
                'supporting_document_type' => 'individual_bank_statement',
                'created_at' => '2025-01-15T10:30:00.000Z',
                'updated_at' => '2025-01-15T10:30:00.000Z',
            ],
            [
                'id' => 'rl_000000000001',
                'receiver_id' => 're_YuaMcI2B8zbQ',
                'status' => 'approved',
                'daily' => 30000,
                'monthly' => 150000,
                'per_transaction' => 15000,
                'approved_per_transaction' => 15000,
                'approved_daily' => 30000,
                'approved_monthly' => 150000,
                'supporting_document_file' => 'https://example.com/proof-of-income.pdf',
                'supporting_document_type' => 'individual_proof_of_income',
                'created_at' => '2024-12-10T14:20:00.000Z',
                'updated_at' => '2024-12-12T09:45:00.000Z',
            ],
        ];

        $this->mockResponse($mockedLimitIncreaseRequests);

        $response = $this->blindpay->receivers->getLimitIncreaseRequests('re_YuaMcI2B8zbQ');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(2, $response->data);
        $this->assertInstanceOf(LimitIncreaseRequest::class, $response->data[0]);
        $this->assertEquals('rl_000000000000', $response->data[0]->id);
        $this->assertEquals('in_review', $response->data[0]->status->value);
        $this->assertEquals('approved', $response->data[1]->status->value);
        $this->assertEquals(30000, $response->data[1]->approvedDaily);
        $this->assertEquals(150000, $response->data[1]->approvedMonthly);
        $this->assertEquals(15000, $response->data[1]->approvedPerTransaction);
    }

    #[Test]
    public function it_requests_a_limit_increase_for_a_receiver(): void
    {
        $mockedResponse = [
            'id' => 'rl_000000000000',
        ];

        $this->mockResponse($mockedResponse);

        $input = new RequestLimitIncreaseInput(
            receiverId: 're_YuaMcI2B8zbQ',
            perTransaction: 50000,
            daily: 100000,
            monthly: 500000,
            supportingDocumentFile: 'https://example.com/tax-return.pdf',
            supportingDocumentType: LimitIncreaseRequestSupportingDocumentType::INDIVIDUAL_TAX_RETURN
        );

        $response = $this->blindpay->receivers->requestLimitIncrease($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('rl_000000000000', $response->data->id);
    }
}
