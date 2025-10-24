<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Receivers\CreateBusinessWithStandardKYBInput;
use BlindPay\SDK\Resources\Receivers\CreateIndividualWithEnhancedKYCInput;
use BlindPay\SDK\Resources\Receivers\CreateIndividualWithStandardKYCInput;
use BlindPay\SDK\Resources\Receivers\IdentificationDocument;
use BlindPay\SDK\Resources\Receivers\LimitIncreaseRequestSupportingDocumentType;
use BlindPay\SDK\Resources\Receivers\ProofOfAddressDocType;
use BlindPay\SDK\Resources\Receivers\PurposeOfTransactions;
use BlindPay\SDK\Resources\Receivers\RequestLimitIncreaseInput;
use BlindPay\SDK\Resources\Receivers\SourceOfFundsDocType;
use BlindPay\SDK\Resources\Receivers\UpdateReceiverInput;
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

    #[Test]
    public function it_lists_receivers(): void
    {
        $mockedReceivers = [
            [
                'id' => 're_Euw7HN4OdxPn',
                'type' => 'individual',
                'kyc_type' => 'standard',
                'kyc_status' => 'verifying',
                'kyc_warnings' => [
                    [
                        'code' => null,
                        'message' => null,
                        'resolution_status' => null,
                        'warning_id' => null,
                    ],
                ],
                'email' => 'bernardo@gmail.com',
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
                'aiprise_validation_key' => '',
                'instance_id' => 'in_000000000000',
                'tos_id' => 'to_3ZZhllJkvo5Z',
                'created_at' => '2021-01-01T00:00:00.000Z',
                'updated_at' => '2021-01-01T00:00:00.000Z',
                'limit' => [
                    'per_transaction' => 100000,
                    'daily' => 200000,
                    'monthly' => 1000000,
                ],
            ],
            [
                'id' => 're_YuaMcI2B8zbQ',
                'type' => 'individual',
                'kyc_type' => 'enhanced',
                'kyc_status' => 'approved',
                'kyc_warnings' => null,
                'email' => 'alice.johnson@example.com',
                'tax_id' => '98765432100',
                'address_line_1' => '123 Main St',
                'address_line_2' => null,
                'city' => 'New York',
                'state_province_region' => 'NY',
                'country' => 'US',
                'postal_code' => '10001',
                'ip_address' => '192.168.1.1',
                'image_url' => null,
                'phone_number' => '+15555555555',
                'proof_of_address_doc_type' => 'BANK_STATEMENT',
                'proof_of_address_doc_file' => 'https://example.com/image.png',
                'first_name' => 'Alice',
                'last_name' => 'Johnson',
                'date_of_birth' => '1990-05-10T00:00:00.000Z',
                'id_doc_country' => 'US',
                'id_doc_type' => 'PASSPORT',
                'id_doc_front_file' => 'https://example.com/image.png',
                'id_doc_back_file' => null,
                'aiprise_validation_key' => 'enhanced-key',
                'instance_id' => 'in_000000000001',
                'source_of_funds_doc_type' => 'salary',
                'source_of_funds_doc_file' => 'https://example.com/image.png',
                'individual_holding_doc_front_file' => 'https://example.com/image.png',
                'purpose_of_transactions' => 'investment_purposes',
                'purpose_of_transactions_explanation' => 'Investing in stocks',
                'tos_id' => 'to_nppX66ntvtHs',
                'created_at' => '2022-02-02T00:00:00.000Z',
                'updated_at' => '2022-02-02T00:00:00.000Z',
                'limit' => [
                    'per_transaction' => 50000,
                    'daily' => 100000,
                    'monthly' => 500000,
                ],
            ],
            [
                'id' => 're_IOxAUL24LG7P',
                'type' => 'business',
                'kyc_type' => 'standard',
                'kyc_status' => 'pending',
                'kyc_warnings' => null,
                'email' => 'business@example.com',
                'tax_id' => '20096178000195',
                'address_line_1' => '1 King St W',
                'address_line_2' => 'Suite 100',
                'city' => 'Toronto',
                'state_province_region' => 'ON',
                'country' => 'CA',
                'postal_code' => 'M5H 1A1',
                'ip_address' => null,
                'image_url' => null,
                'phone_number' => '+14165555555',
                'proof_of_address_doc_type' => 'UTILITY_BILL',
                'proof_of_address_doc_file' => 'https://example.com/image.png',
                'legal_name' => 'Business Corp',
                'alternate_name' => 'BizCo',
                'formation_date' => '2010-01-01T00:00:00.000Z',
                'website' => 'https://businesscorp.com',
                'owners' => [
                    [
                        'role' => 'beneficial_owner',
                        'first_name' => 'Carlos',
                        'last_name' => 'Silva',
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
                        'proof_of_address_doc_type' => 'UTILITY_BILL',
                        'proof_of_address_doc_file' => 'https://example.com/image.png',
                        'id' => 'ub_000000000000',
                        'instance_id' => 'in_000000000000',
                        'receiver_id' => 're_IOxAUL24LG7P',
                    ],
                ],
                'incorporation_doc_file' => 'https://example.com/image.png',
                'proof_of_ownership_doc_file' => 'https://example.com/image.png',
                'external_id' => null,
                'instance_id' => 'in_000000000002',
                'tos_id' => 'to_nppX66ntvtHs',
                'aiprise_validation_key' => '',
                'created_at' => '2015-03-15T00:00:00.000Z',
                'updated_at' => '2015-03-15T00:00:00.000Z',
                'limit' => [
                    'per_transaction' => 200000,
                    'daily' => 400000,
                    'monthly' => 2000000,
                ],
            ],
        ];

        $this->mockResponse($mockedReceivers);

        $response = $this->blindpay->receivers->list();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(3, $response->data);
        $this->assertEquals('re_Euw7HN4OdxPn', $response->data[0]->id);
        $this->assertEquals('re_YuaMcI2B8zbQ', $response->data[1]->id);
        $this->assertEquals('re_IOxAUL24LG7P', $response->data[2]->id);
    }

    #[Test]
    public function it_creates_an_individual_receiver_with_standard_kyc(): void
    {
        $mockedReceiver = [
            'id' => 're_Euw7HN4OdxPn',
        ];

        $this->mockResponse($mockedReceiver);

        $input = new CreateIndividualWithStandardKYCInput(
            addressLine1: 'Av. Paulista, 1000',
            city: 'São Paulo',
            country: Country::BR,
            dateOfBirth: '1998-02-02T00:00:00.000Z',
            email: 'bernardo.simonassi@gmail.com',
            firstName: 'Bernardo',
            idDocBackFile: 'https://example.com/image.png',
            idDocCountry: Country::BR,
            idDocFrontFile: 'https://example.com/image.png',
            idDocType: IdentificationDocument::PASSPORT,
            lastName: 'Simonassi',
            phoneNumber: '+5511987654321',
            postalCode: '01310-100',
            proofOfAddressDocFile: 'https://example.com/image.png',
            proofOfAddressDocType: ProofOfAddressDocType::UTILITY_BILL,
            stateProvinceRegion: 'SP',
            taxId: '12345678900',
            tosId: 'to_tPiz4bM2nh5K',
            addressLine2: 'Apto 101'
        );

        $response = $this->blindpay->receivers->createIndividualWithStandardKYC($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('re_Euw7HN4OdxPn', $response->data->id);
    }

    #[Test]
    public function it_creates_an_individual_receiver_with_enhanced_kyc(): void
    {
        $mockedReceiver = [
            'id' => 're_YuaMcI2B8zbQ',
        ];

        $this->mockResponse($mockedReceiver);

        $input = new CreateIndividualWithEnhancedKYCInput(
            addressLine1: 'Av. Paulista, 1000',
            city: 'São Paulo',
            country: Country::BR,
            dateOfBirth: '1998-02-02T00:00:00.000Z',
            email: 'bernardo.simonassi@gmail.com',
            firstName: 'Bernardo',
            idDocBackFile: 'https://example.com/image.png',
            idDocCountry: Country::BR,
            idDocFrontFile: 'https://example.com/image.png',
            idDocType: IdentificationDocument::PASSPORT,
            individualHoldingDocFrontFile: 'https://example.com/image.png',
            lastName: 'Simonassi',
            phoneNumber: '+5511987654321',
            postalCode: '01310-100',
            proofOfAddressDocFile: 'https://example.com/image.png',
            proofOfAddressDocType: ProofOfAddressDocType::UTILITY_BILL,
            purposeOfTransactions: PurposeOfTransactions::PERSONAL_OR_LIVING_EXPENSES,
            purposeOfTransactionsExplanation: 'I am receiving salary payments from my employer',
            sourceOfFundsDocFile: 'https://example.com/image.png',
            sourceOfFundsDocType: SourceOfFundsDocType::SAVINGS,
            stateProvinceRegion: 'SP',
            taxId: '12345678900',
            tosId: 'to_3ZZhllJkvo5Z',
            addressLine2: 'Apto 101'
        );

        $response = $this->blindpay->receivers->createIndividualWithEnhancedKYC($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('re_YuaMcI2B8zbQ', $response->data->id);
    }

    #[Test]
    public function it_creates_a_business_receiver_with_standard_kyb(): void
    {
        $mockedReceiver = [
            'id' => 're_IOxAUL24LG7P',
        ];

        $this->mockResponse($mockedReceiver);

        $owner = new class
        {
            public function toArray(): array
            {
                return [
                    'role' => 'beneficial_owner',
                    'first_name' => 'Carlos',
                    'last_name' => 'Silva',
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
                    'proof_of_address_doc_type' => 'UTILITY_BILL',
                    'proof_of_address_doc_file' => 'https://example.com/image.png',
                ];
            }
        };

        $input = new CreateBusinessWithStandardKYBInput(
            addressLine1: 'Av. Brigadeiro Faria Lima, 400',
            city: 'São Paulo',
            country: Country::BR,
            email: 'contato@empresa.com.br',
            formationDate: '2010-05-20T00:00:00.000Z',
            incorporationDocFile: 'https://example.com/image.png',
            legalName: 'Empresa Exemplo Ltda',
            owners: [$owner],
            postalCode: '04538-132',
            proofOfAddressDocFile: 'https://example.com/image.png',
            proofOfAddressDocType: ProofOfAddressDocType::UTILITY_BILL,
            proofOfOwnershipDocFile: 'https://example.com/image.png',
            stateProvinceRegion: 'SP',
            taxId: '20096178000195',
            tosId: 'to_nppX66ntvtHs',
            addressLine2: 'Sala 1201',
            alternateName: 'Exemplo',
            website: 'https://site.com/'
        );

        $response = $this->blindpay->receivers->createBusinessWithStandardKYB($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('re_IOxAUL24LG7P', $response->data->id);
    }

    #[Test]
    public function it_gets_a_receiver(): void
    {
        $mockedReceiver = [
            'id' => 're_YuaMcI2B8zbQ',
            'type' => 'individual',
            'kyc_type' => 'enhanced',
            'kyc_status' => 'verifying',
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
            'aiprise_validation_key' => '',
            'source_of_funds_doc_type' => 'savings',
            'source_of_funds_doc_file' => 'https://example.com/image.png',
            'individual_holding_doc_front_file' => 'https://example.com/image.png',
            'purpose_of_transactions' => 'personal_or_living_expenses',
            'purpose_of_transactions_explanation' => 'I am receiving salary payments from my employer',
            'instance_id' => 'in_000000000000',
            'tos_id' => 'to_3ZZhllJkvo5Z',
            'created_at' => '2021-01-01T00:00:00.000Z',
            'updated_at' => '2021-01-01T00:00:00.000Z',
            'limit' => [
                'per_transaction' => 100000,
                'daily' => 200000,
                'monthly' => 1000000,
            ],
        ];

        $this->mockResponse($mockedReceiver);

        $response = $this->blindpay->receivers->get('re_YuaMcI2B8zbQ');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('re_YuaMcI2B8zbQ', $response->data->id);
        $this->assertEquals('bernardo.simonassi@gmail.com', $response->data->email);
        $this->assertEquals('Bernardo', $response->data->firstName);
        $this->assertEquals('Simonassi', $response->data->lastName);
    }

    #[Test]
    public function it_updates_a_receiver(): void
    {
        $this->mockResponse(['data' => null]);

        $input = new UpdateReceiverInput(
            receiverId: 're_YuaMcI2B8zbQ',
            email: 'bernardo.simonassi@gmail.com',
            taxId: '12345678900',
            addressLine1: 'Av. Paulista, 1000',
            addressLine2: 'Apto 101',
            city: 'São Paulo',
            stateProvinceRegion: 'SP',
            country: Country::BR,
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
            individualHoldingDocFrontFile: 'https://example.com/image.png',
            purposeOfTransactions: PurposeOfTransactions::PERSONAL_OR_LIVING_EXPENSES,
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
                'id' => 'rl_000000000000',
                'receiver_id' => 're_YuaMcI2B8zbQ',
                'status' => 'approved',
                'daily' => 30000,
                'monthly' => 150000,
                'per_transaction' => 15000,
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
        $this->assertEquals('rl_000000000000', $response->data[0]->id);
        $this->assertEquals('in_review', $response->data[0]->status->value);
        $this->assertEquals('approved', $response->data[1]->status->value);
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
            daily: 100000,
            monthly: 500000,
            perTransaction: 50000,
            supportingDocumentFile: 'https://example.com/tax-return.pdf',
            supportingDocumentType: LimitIncreaseRequestSupportingDocumentType::INDIVIDUAL_TAX_RETURN
        );

        $response = $this->blindpay->receivers->requestLimitIncrease($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('rl_000000000000', $response->data->id);
    }
}
