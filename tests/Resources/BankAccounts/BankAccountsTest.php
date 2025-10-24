<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\BankAccounts\AchCopDocument;
use BlindPay\SDK\Resources\BankAccounts\ArgentinaTransfers;
use BlindPay\SDK\Resources\BankAccounts\CreateAchInput;
use BlindPay\SDK\Resources\BankAccounts\CreateArgentinaTransfersInput;
use BlindPay\SDK\Resources\BankAccounts\CreateColombiaAchInput;
use BlindPay\SDK\Resources\BankAccounts\CreateInternationalSwiftInput;
use BlindPay\SDK\Resources\BankAccounts\CreatePixInput;
use BlindPay\SDK\Resources\BankAccounts\CreateRtpInput;
use BlindPay\SDK\Resources\BankAccounts\CreateSpeiInput;
use BlindPay\SDK\Resources\BankAccounts\CreateWireInput;
use BlindPay\SDK\Resources\BankAccounts\DeleteBankAccountInput;
use BlindPay\SDK\Resources\BankAccounts\GetBankAccountInput;
use BlindPay\SDK\Resources\BankAccounts\SpeiProtocol;
use BlindPay\SDK\Types\AccountClass;
use BlindPay\SDK\Types\BankAccountType;
use BlindPay\SDK\Types\Country;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BankAccountsTest extends TestCase
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
    public function it_creates_a_pix_bank_account(): void
    {
        $mockedPixAccount = [
            'id' => 'ba_000000000000',
            'type' => 'pix',
            'name' => 'PIX Account',
            'pix_key' => '14947677768',
            'created_at' => '2021-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedPixAccount);

        $input = new CreatePixInput(
            receiverId: 're_000000000000',
            name: 'PIX Account',
            pixKey: '14947677768'
        );

        $response = $this->blindpay->receivers->bankAccounts->createPix($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('ba_000000000000', $response->data->id);
        $this->assertEquals('pix', $response->data->type);
        $this->assertEquals('PIX Account', $response->data->name);
        $this->assertEquals('14947677768', $response->data->pixKey);
    }

    #[Test]
    public function it_creates_an_argentina_transfers_bank_account(): void
    {
        $mockedArgentinaTransfersAccount = [
            'id' => 'ba_000000000000',
            'type' => 'transfers_bitso',
            'name' => 'Argentina Transfers Account',
            'beneficiary_name' => 'Individual full name or business name',
            'transfers_type' => 'CVU',
            'transfers_account' => 'BM123123123123',
            'created_at' => '2021-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedArgentinaTransfersAccount);

        $input = new CreateArgentinaTransfersInput(
            receiverId: 're_000000000000',
            name: 'Argentina Transfers Account',
            beneficiaryName: 'Individual full name or business name',
            transfersAccount: 'BM123123123123',
            transfersType: ArgentinaTransfers::CVU
        );

        $response = $this->blindpay->receivers->bankAccounts->createArgentinaTransfers($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('ba_000000000000', $response->data->id);
        $this->assertEquals('transfers_bitso', $response->data->type);
        $this->assertEquals('Argentina Transfers Account', $response->data->name);
        $this->assertEquals('Individual full name or business name', $response->data->beneficiaryName);
        $this->assertEquals(ArgentinaTransfers::CVU, $response->data->transfersType);
        $this->assertEquals('BM123123123123', $response->data->transfersAccount);
    }

    #[Test]
    public function it_creates_a_spei_bank_account(): void
    {
        $mockedSpeiAccount = [
            'id' => 'ba_000000000000',
            'type' => 'spei_bitso',
            'name' => 'SPEI Account',
            'beneficiary_name' => 'Individual full name or business name',
            'spei_protocol' => 'SPEI',
            'spei_institution_code' => '40002',
            'spei_clabe' => '5482347403740546',
            'created_at' => '2021-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedSpeiAccount);

        $input = new CreateSpeiInput(
            receiverId: 're_000000000000',
            beneficiaryName: 'Individual full name or business name',
            name: 'SPEI Account',
            speiClabe: '5482347403740546',
            speiInstitutionCode: '40002',
            speiProtocol: SpeiProtocol::SPEI
        );

        $response = $this->blindpay->receivers->bankAccounts->createSpei($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('ba_000000000000', $response->data->id);
        $this->assertEquals('spei_bitso', $response->data->type);
        $this->assertEquals('SPEI Account', $response->data->name);
        $this->assertEquals('Individual full name or business name', $response->data->beneficiaryName);
        $this->assertEquals('5482347403740546', $response->data->speiClabe);
        $this->assertEquals('40002', $response->data->speiInstitutionCode);
    }

    #[Test]
    public function it_creates_a_colombia_ach_bank_account(): void
    {
        $mockedColombiaAchAccount = [
            'id' => 'ba_000000000000',
            'type' => 'ach_cop_bitso',
            'name' => 'Colombia ACH Account',
            'account_type' => 'checking',
            'ach_cop_beneficiary_first_name' => 'Fernando',
            'ach_cop_beneficiary_last_name' => 'Guzman Alarcón',
            'ach_cop_document_id' => '1661105408',
            'ach_cop_document_type' => 'CC',
            'ach_cop_email' => 'fernando.guzman@gmail.com',
            'ach_cop_bank_code' => '051',
            'ach_cop_bank_account' => '12345678',
            'created_at' => '2021-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedColombiaAchAccount);

        $input = new CreateColombiaAchInput(
            receiverId: 're_000000000000',
            name: 'Colombia ACH Account',
            accountType: BankAccountType::CHECKING,
            achCopBeneficiaryFirstName: 'Fernando',
            achCopBeneficiaryLastName: 'Guzman Alarcón',
            achCopDocumentId: '1661105408',
            achCopDocumentType: AchCopDocument::CC,
            achCopEmail: 'fernando.guzman@gmail.com',
            achCopBankCode: '051',
            achCopBankAccount: '12345678'
        );

        $response = $this->blindpay->receivers->bankAccounts->createColombiaAch($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('ba_000000000000', $response->data->id);
        $this->assertEquals('ach_cop_bitso', $response->data->type);
        $this->assertEquals('Colombia ACH Account', $response->data->name);
        $this->assertEquals(BankAccountType::CHECKING, $response->data->accountType);
        $this->assertEquals('Fernando', $response->data->achCopBeneficiaryFirstName);
        $this->assertEquals('Guzman Alarcón', $response->data->achCopBeneficiaryLastName);
        $this->assertEquals('1661105408', $response->data->achCopDocumentId);
        $this->assertEquals(AchCopDocument::CC, $response->data->achCopDocumentType);
        $this->assertEquals('fernando.guzman@gmail.com', $response->data->achCopEmail);
        $this->assertEquals('051', $response->data->achCopBankCode);
        $this->assertEquals('12345678', $response->data->achCopBankAccount);
    }

    #[Test]
    public function it_creates_an_ach_bank_account(): void
    {
        $mockedAchAccount = [
            'id' => 'ba_000000000000',
            'type' => 'ach',
            'name' => 'ACH Account',
            'beneficiary_name' => 'Individual full name or business name',
            'routing_number' => '012345678',
            'account_number' => '1001001234',
            'account_type' => 'checking',
            'account_class' => 'individual',
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'state_province_region' => null,
            'country' => null,
            'postal_code' => null,
            'ach_cop_beneficiary_first_name' => null,
            'ach_cop_beneficiary_last_name' => null,
            'ach_cop_document_id' => null,
            'ach_cop_document_type' => null,
            'ach_cop_email' => null,
            'ach_cop_bank_code' => null,
            'ach_cop_bank_account' => null,
            'created_at' => '2021-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedAchAccount);

        $input = new CreateAchInput(
            receiverId: 're_000000000000',
            name: 'ACH Account',
            accountClass: AccountClass::INDIVIDUAL,
            accountNumber: '1001001234',
            accountType: BankAccountType::CHECKING,
            beneficiaryName: 'Individual full name or business name',
            routingNumber: '012345678'
        );

        $response = $this->blindpay->receivers->bankAccounts->createAch($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('ba_000000000000', $response->data->id);
        $this->assertEquals('ach', $response->data->type);
        $this->assertEquals('ACH Account', $response->data->name);
        $this->assertEquals('Individual full name or business name', $response->data->beneficiaryName);
        $this->assertEquals('012345678', $response->data->routingNumber);
        $this->assertEquals('1001001234', $response->data->accountNumber);
        $this->assertEquals(BankAccountType::CHECKING, $response->data->accountType);
        $this->assertEquals(AccountClass::INDIVIDUAL, $response->data->accountClass);
        $this->assertNull($response->data->addressLine1);
        $this->assertNull($response->data->addressLine2);
        $this->assertNull($response->data->city);
        $this->assertNull($response->data->stateProvinceRegion);
        $this->assertNull($response->data->country);
        $this->assertNull($response->data->postalCode);
    }

    #[Test]
    public function it_creates_a_wire_bank_account(): void
    {
        $mockedWireAccount = [
            'id' => 'ba_000000000000',
            'type' => 'wire',
            'name' => 'Wire Account',
            'beneficiary_name' => 'Individual full name or business name',
            'routing_number' => '012345678',
            'account_number' => '1001001234',
            'address_line_1' => 'Address line 1',
            'address_line_2' => 'Address line 2',
            'city' => 'City',
            'state_province_region' => 'State/Province/Region',
            'country' => 'US',
            'postal_code' => 'Postal code',
            'created_at' => '2021-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedWireAccount);

        $input = new CreateWireInput(
            receiverId: 're_000000000000',
            name: 'Wire Account',
            accountNumber: '1001001234',
            beneficiaryName: 'Individual full name or business name',
            routingNumber: '012345678',
            addressLine1: 'Address line 1',
            addressLine2: 'Address line 2',
            city: 'City',
            stateProvinceRegion: 'State/Province/Region',
            country: Country::US,
            postalCode: 'Postal code'
        );

        $response = $this->blindpay->receivers->bankAccounts->createWire($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('ba_000000000000', $response->data->id);
        $this->assertEquals('wire', $response->data->type);
        $this->assertEquals('Wire Account', $response->data->name);
        $this->assertEquals('Individual full name or business name', $response->data->beneficiaryName);
        $this->assertEquals('012345678', $response->data->routingNumber);
        $this->assertEquals('1001001234', $response->data->accountNumber);
        $this->assertEquals('Address line 1', $response->data->addressLine1);
        $this->assertEquals('Address line 2', $response->data->addressLine2);
        $this->assertEquals('City', $response->data->city);
        $this->assertEquals('State/Province/Region', $response->data->stateProvinceRegion);
        $this->assertEquals(Country::US, $response->data->country);
        $this->assertEquals('Postal code', $response->data->postalCode);
    }

    #[Test]
    public function it_creates_an_international_swift_bank_account(): void
    {
        $mockedInternationalSwiftAccount = [
            'id' => 'ba_000000000000',
            'type' => 'international_swift',
            'name' => 'International Swift Account',
            'beneficiary_name' => null,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'state_province_region' => null,
            'country' => null,
            'postal_code' => null,
            'swift_code_bic' => '123456789',
            'swift_account_holder_name' => 'John Doe',
            'swift_account_number_iban' => '123456789',
            'swift_beneficiary_address_line_1' => '123 Main Street, Suite 100, Downtown District, City Center CP 12345',
            'swift_beneficiary_address_line_2' => null,
            'swift_beneficiary_country' => 'MX',
            'swift_beneficiary_city' => 'City',
            'swift_beneficiary_state_province_region' => 'District',
            'swift_beneficiary_postal_code' => '11530',
            'swift_bank_name' => 'Banco Regional SA',
            'swift_bank_address_line_1' => '123 Main Street, Suite 100, Downtown District, City Center CP 12345',
            'swift_bank_address_line_2' => null,
            'swift_bank_country' => 'MX',
            'swift_bank_city' => 'City',
            'swift_bank_state_province_region' => 'District',
            'swift_bank_postal_code' => '11530',
            'swift_intermediary_bank_swift_code_bic' => null,
            'swift_intermediary_bank_account_number_iban' => null,
            'swift_intermediary_bank_name' => null,
            'swift_intermediary_bank_country' => null,
            'created_at' => '2021-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedInternationalSwiftAccount);

        $input = new CreateInternationalSwiftInput(
            receiverId: 're_000000000000',
            name: 'International Swift Account',
            swiftAccountHolderName: 'John Doe',
            swiftAccountNumberIban: '123456789',
            swiftBankAddressLine1: '123 Main Street, Suite 100, Downtown District, City Center CP 12345',
            swiftBankAddressLine2: null,
            swiftBankCity: 'City',
            swiftBankCountry: Country::MX,
            swiftBankName: 'Banco Regional SA',
            swiftBankPostalCode: '11530',
            swiftBankStateProvinceRegion: 'District',
            swiftBeneficiaryAddressLine1: '123 Main Street, Suite 100, Downtown District, City Center CP 12345',
            swiftBeneficiaryAddressLine2: null,
            swiftBeneficiaryCity: 'City',
            swiftBeneficiaryCountry: Country::MX,
            swiftBeneficiaryPostalCode: '11530',
            swiftBeneficiaryStateProvinceRegion: 'District',
            swiftCodeBic: '123456789',
            swiftIntermediaryBankAccountNumberIban: null,
            swiftIntermediaryBankCountry: null,
            swiftIntermediaryBankName: null,
            swiftIntermediaryBankSwiftCodeBic: null
        );

        $response = $this->blindpay->receivers->bankAccounts->createInternationalSwift($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('ba_000000000000', $response->data->id);
        $this->assertEquals('international_swift', $response->data->type);
        $this->assertEquals('International Swift Account', $response->data->name);
        $this->assertNull($response->data->beneficiaryName);
        $this->assertEquals('123456789', $response->data->swiftCodeBic);
        $this->assertEquals('John Doe', $response->data->swiftAccountHolderName);
        $this->assertEquals('123456789', $response->data->swiftAccountNumberIban);
        $this->assertEquals('123 Main Street, Suite 100, Downtown District, City Center CP 12345', $response->data->swiftBeneficiaryAddressLine1);
        $this->assertNull($response->data->swiftBeneficiaryAddressLine2);
        $this->assertEquals(Country::MX, $response->data->swiftBeneficiaryCountry);
        $this->assertEquals('City', $response->data->swiftBeneficiaryCity);
        $this->assertEquals('District', $response->data->swiftBeneficiaryStateProvinceRegion);
        $this->assertEquals('11530', $response->data->swiftBeneficiaryPostalCode);
        $this->assertEquals('Banco Regional SA', $response->data->swiftBankName);
        $this->assertEquals('123 Main Street, Suite 100, Downtown District, City Center CP 12345', $response->data->swiftBankAddressLine1);
        $this->assertNull($response->data->swiftBankAddressLine2);
        $this->assertEquals(Country::MX, $response->data->swiftBankCountry);
        $this->assertEquals('City', $response->data->swiftBankCity);
        $this->assertEquals('District', $response->data->swiftBankStateProvinceRegion);
        $this->assertEquals('11530', $response->data->swiftBankPostalCode);
        $this->assertNull($response->data->swiftIntermediaryBankSwiftCodeBic);
        $this->assertNull($response->data->swiftIntermediaryBankAccountNumberIban);
        $this->assertNull($response->data->swiftIntermediaryBankName);
        $this->assertNull($response->data->swiftIntermediaryBankCountry);
    }

    #[Test]
    public function it_creates_an_rtp_bank_account(): void
    {
        $mockedRtpAccount = [
            'id' => 'ba_JW5ZtlKMlgS1',
            'type' => 'rtp',
            'name' => 'John Doe RTP',
            'beneficiary_name' => 'John Doe',
            'routing_number' => '121000358',
            'account_number' => '325203027578',
            'address_line_1' => 'Street of the fools',
            'address_line_2' => null,
            'city' => 'Fools City',
            'state_province_region' => 'FL',
            'country' => 'US',
            'postal_code' => '22599',
            'created_at' => '2025-09-30T04:23:30.823Z',
        ];

        $this->mockResponse($mockedRtpAccount);

        $input = new CreateRtpInput(
            receiverId: 're_000000000000',
            name: 'John Doe RTP',
            beneficiaryName: 'John Doe',
            routingNumber: '121000358',
            accountNumber: '325203027578',
            addressLine1: 'Street of the fools',
            addressLine2: null,
            city: 'Fools City',
            stateProvinceRegion: 'FL',
            country: Country::US,
            postalCode: '22599'
        );

        $response = $this->blindpay->receivers->bankAccounts->createRtp($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('ba_JW5ZtlKMlgS1', $response->data->id);
        $this->assertEquals('rtp', $response->data->type);
        $this->assertEquals('John Doe RTP', $response->data->name);
        $this->assertEquals('John Doe', $response->data->beneficiaryName);
        $this->assertEquals('121000358', $response->data->routingNumber);
        $this->assertEquals('325203027578', $response->data->accountNumber);
        $this->assertEquals('Street of the fools', $response->data->addressLine1);
        $this->assertNull($response->data->addressLine2);
        $this->assertEquals('Fools City', $response->data->city);
        $this->assertEquals('FL', $response->data->stateProvinceRegion);
        $this->assertEquals(Country::US, $response->data->country);
        $this->assertEquals('22599', $response->data->postalCode);
    }

    #[Test]
    public function it_gets_a_bank_account(): void
    {
        $mockedBankAccount = [
            'id' => 'ba_000000000000',
            'receiver_id' => 'rcv_123',
            'account_holder_name' => 'Individual full name or business name',
            'account_number' => '1001001234',
            'routing_number' => '012345678',
            'account_type' => 'checking',
            'bank_name' => 'Bank Name',
            'swift_code' => '123456789',
            'iban' => null,
            'is_primary' => false,
            'created_at' => '2021-01-01T00:00:00Z',
            'updated_at' => '2021-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedBankAccount);

        $input = new GetBankAccountInput(
            receiverId: 're_000000000000',
            id: 'ba_000000000000'
        );

        $response = $this->blindpay->receivers->bankAccounts->get($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('ba_000000000000', $response->data->id);
        $this->assertEquals('rcv_123', $response->data->receiverId);
        $this->assertEquals('Individual full name or business name', $response->data->accountHolderName);
        $this->assertEquals('1001001234', $response->data->accountNumber);
        $this->assertEquals('012345678', $response->data->routingNumber);
        $this->assertEquals(BankAccountType::CHECKING, $response->data->accountType);
        $this->assertEquals('Bank Name', $response->data->bankName);
        $this->assertEquals('123456789', $response->data->swiftCode);
        $this->assertNull($response->data->iban);
        $this->assertFalse($response->data->isPrimary);
    }

    #[Test]
    public function it_lists_bank_accounts(): void
    {
        $mockedBankAccounts = [
            'data' => [
                [
                    'id' => 'ba_000000000000',
                    'type' => 'wire',
                    'name' => 'Bank Account Name',
                    'pix_key' => '14947677768',
                    'beneficiary_name' => 'Individual full name or business name',
                    'routing_number' => '012345678',
                    'account_number' => '1001001234',
                    'account_type' => 'checking',
                    'account_class' => 'individual',
                    'address_line_1' => 'Address line 1',
                    'address_line_2' => 'Address line 2',
                    'city' => 'City',
                    'state_province_region' => 'State/Province/Region',
                    'country' => 'US',
                    'postal_code' => 'Postal code',
                    'spei_protocol' => 'SPEI',
                    'spei_institution_code' => '40002',
                    'spei_clabe' => '5482347403740546',
                    'transfers_type' => 'CVU',
                    'transfers_account' => 'BM123123123123',
                    'ach_cop_beneficiary_first_name' => 'Fernando',
                    'ach_cop_beneficiary_last_name' => 'Guzman Alarcón',
                    'ach_cop_document_id' => '1661105408',
                    'ach_cop_document_type' => 'CC',
                    'ach_cop_email' => 'fernando.guzman@gmail.com',
                    'ach_cop_bank_code' => '051',
                    'ach_cop_bank_account' => '12345678',
                    'swift_code_bic' => '123456789',
                    'swift_account_holder_name' => 'John Doe',
                    'swift_account_number_iban' => '123456789',
                    'swift_beneficiary_address_line_1' => '123 Main Street, Suite 100, Downtown District, City Center CP 12345',
                    'swift_beneficiary_address_line_2' => '456 Oak Avenue, Building 7, Financial District, Business Center CP 54321',
                    'swift_beneficiary_country' => 'MX',
                    'swift_beneficiary_city' => 'City',
                    'swift_beneficiary_state_province_region' => 'District',
                    'swift_beneficiary_postal_code' => '11530',
                    'swift_bank_name' => 'Banco Regional SA',
                    'swift_bank_address_line_1' => '123 Main Street, Suite 100, Downtown District, City Center CP 12345',
                    'swift_bank_address_line_2' => '456 Oak Avenue, Building 7, Financial District, Business Center CP 54321',
                    'swift_bank_country' => 'MX',
                    'swift_bank_city' => 'City',
                    'swift_bank_state_province_region' => 'District',
                    'swift_bank_postal_code' => '11530',
                    'swift_intermediary_bank_swift_code_bic' => 'AEIBARB1',
                    'swift_intermediary_bank_account_number_iban' => '123456789',
                    'swift_intermediary_bank_name' => 'Banco Regional SA',
                    'swift_intermediary_bank_country' => 'US',
                    'tron_wallet_hash' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                    'offramp_wallets' => [
                        [
                            'address' => 'TALJN9zTTEL9TVBb4WuTt6wLvPqJZr3hvb',
                            'id' => 'ow_000000000000',
                            'network' => 'tron',
                            'external_id' => 'your_external_id',
                        ],
                    ],
                    'created_at' => '2021-01-01T00:00:00Z',
                ],
            ],
        ];

        $this->mockResponse($mockedBankAccounts);

        $response = $this->blindpay->receivers->bankAccounts->list('re_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data->data);
        $this->assertCount(1, $response->data->data);
        $this->assertEquals('ba_000000000000', $response->data->data[0]->id);
        $this->assertEquals('Bank Account Name', $response->data->data[0]->name);
        $this->assertEquals('14947677768', $response->data->data[0]->pixKey);
        $this->assertEquals('Individual full name or business name', $response->data->data[0]->beneficiaryName);
        $this->assertEquals('012345678', $response->data->data[0]->routingNumber);
        $this->assertEquals('1001001234', $response->data->data[0]->accountNumber);
        $this->assertEquals('Address line 1', $response->data->data[0]->addressLine1);
        $this->assertEquals('Address line 2', $response->data->data[0]->addressLine2);
        $this->assertEquals('City', $response->data->data[0]->city);
    }

    #[Test]
    public function it_deletes_a_bank_account(): void
    {
        $this->mockResponse(['data' => null]);

        $input = new DeleteBankAccountInput(
            receiverId: 're_000000000000',
            id: 'ba_000000000000'
        );

        $response = $this->blindpay->receivers->bankAccounts->delete($input);

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertArrayHasKey('data', $response->data);
        $this->assertNull($response->data['data']);
    }
}
