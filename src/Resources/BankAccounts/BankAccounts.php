<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\BankAccounts;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\AccountClass;
use BlindPay\SDK\Types\BankAccountType;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Country;
use BlindPay\SDK\Types\Rail;
use DateTimeImmutable;

enum ArgentinaTransfers: string
{
    case CVU = 'CVU';
    case CBU = 'CBU';
    case ALIAS = 'ALIAS';
}

/*
 * Colombia ACH document types
 */
enum AchCopDocument: string
{
    case CC = 'CC';
    case CE = 'CE';
    case NIT = 'NIT';
    case PASS = 'PASS';
    case PEP = 'PEP';
}

enum SpeiProtocol: string
{
    case SPEI = 'SPEI';
    case STP = 'STP';
}

readonly class BankAccountListItem
{
    /*
     * @param array<array{address: string, id: string, network: string, external_id: string}>|null $offrampWallets
     */
    public function __construct(
        public string $id,
        public Rail $type,
        public string $name,
        public ?string $pixKey,
        public ?string $beneficiaryName,
        public ?string $routingNumber,
        public ?string $accountNumber,
        public ?BankAccountType $accountType,
        public ?AccountClass $accountClass,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $city,
        public ?string $stateProvinceRegion,
        public ?Country $country,
        public ?string $postalCode,
        public ?string $speiProtocol,
        public ?string $speiInstitutionCode,
        public ?string $speiClabe,
        public ?ArgentinaTransfers $transfersType,
        public ?string $transfersAccount,
        public ?string $achCopBeneficiaryFirstName,
        public ?string $achCopBeneficiaryLastName,
        public ?string $achCopDocumentId,
        public ?AchCopDocument $achCopDocumentType,
        public ?string $achCopEmail,
        public ?string $achCopBankCode,
        public ?string $achCopBankAccount,
        public ?string $swiftCodeBic,
        public ?string $swiftAccountHolderName,
        public ?string $swiftAccountNumberIban,
        public ?string $swiftBeneficiaryAddressLine1,
        public ?string $swiftBeneficiaryAddressLine2,
        public ?Country $swiftBeneficiaryCountry,
        public ?string $swiftBeneficiaryCity,
        public ?string $swiftBeneficiaryStateProvinceRegion,
        public ?string $swiftBeneficiaryPostalCode,
        public ?string $swiftBankName,
        public ?string $swiftBankAddressLine1,
        public ?string $swiftBankAddressLine2,
        public ?Country $swiftBankCountry,
        public ?string $swiftBankCity,
        public ?string $swiftBankStateProvinceRegion,
        public ?string $swiftBankPostalCode,
        public ?string $swiftIntermediaryBankSwiftCodeBic,
        public ?string $swiftIntermediaryBankAccountNumberIban,
        public ?string $swiftIntermediaryBankName,
        public ?Country $swiftIntermediaryBankCountry,
        public ?string $tronWalletHash,
        public ?array $offrampWallets,
        public DateTimeImmutable $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: Rail::from($data['type']),
            name: $data['name'],
            pixKey: $data['pix_key'] ?? null,
            beneficiaryName: $data['beneficiary_name'] ?? null,
            routingNumber: $data['routing_number'] ?? null,
            accountNumber: $data['account_number'] ?? null,
            accountType: isset($data['account_type']) ? BankAccountType::from($data['account_type']) : null,
            accountClass: isset($data['account_class']) ? AccountClass::from($data['account_class']) : null,
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'] ?? null,
            stateProvinceRegion: $data['state_province_region'] ?? null,
            country: isset($data['country']) ? Country::from($data['country']) : null,
            postalCode: $data['postal_code'] ?? null,
            speiProtocol: $data['spei_protocol'] ?? null,
            speiInstitutionCode: $data['spei_institution_code'] ?? null,
            speiClabe: $data['spei_clabe'] ?? null,
            transfersType: isset($data['transfers_type']) ? ArgentinaTransfers::from($data['transfers_type']) : null,
            transfersAccount: $data['transfers_account'] ?? null,
            achCopBeneficiaryFirstName: $data['ach_cop_beneficiary_first_name'] ?? null,
            achCopBeneficiaryLastName: $data['ach_cop_beneficiary_last_name'] ?? null,
            achCopDocumentId: $data['ach_cop_document_id'] ?? null,
            achCopDocumentType: isset($data['ach_cop_document_type']) ? AchCopDocument::from($data['ach_cop_document_type']) : null,
            achCopEmail: $data['ach_cop_email'] ?? null,
            achCopBankCode: $data['ach_cop_bank_code'] ?? null,
            achCopBankAccount: $data['ach_cop_bank_account'] ?? null,
            swiftCodeBic: $data['swift_code_bic'] ?? null,
            swiftAccountHolderName: $data['swift_account_holder_name'] ?? null,
            swiftAccountNumberIban: $data['swift_account_number_iban'] ?? null,
            swiftBeneficiaryAddressLine1: $data['swift_beneficiary_address_line_1'] ?? null,
            swiftBeneficiaryAddressLine2: $data['swift_beneficiary_address_line_2'] ?? null,
            swiftBeneficiaryCountry: isset($data['swift_beneficiary_country']) ? Country::from($data['swift_beneficiary_country']) : null,
            swiftBeneficiaryCity: $data['swift_beneficiary_city'] ?? null,
            swiftBeneficiaryStateProvinceRegion: $data['swift_beneficiary_state_province_region'] ?? null,
            swiftBeneficiaryPostalCode: $data['swift_beneficiary_postal_code'] ?? null,
            swiftBankName: $data['swift_bank_name'] ?? null,
            swiftBankAddressLine1: $data['swift_bank_address_line_1'] ?? null,
            swiftBankAddressLine2: $data['swift_bank_address_line_2'] ?? null,
            swiftBankCountry: isset($data['swift_bank_country']) ? Country::from($data['swift_bank_country']) : null,
            swiftBankCity: $data['swift_bank_city'] ?? null,
            swiftBankStateProvinceRegion: $data['swift_bank_state_province_region'] ?? null,
            swiftBankPostalCode: $data['swift_bank_postal_code'] ?? null,
            swiftIntermediaryBankSwiftCodeBic: $data['swift_intermediary_bank_swift_code_bic'] ?? null,
            swiftIntermediaryBankAccountNumberIban: $data['swift_intermediary_bank_account_number_iban'] ?? null,
            swiftIntermediaryBankName: $data['swift_intermediary_bank_name'] ?? null,
            swiftIntermediaryBankCountry: isset($data['swift_intermediary_bank_country']) ? Country::from($data['swift_intermediary_bank_country']) : null,
            tronWalletHash: $data['tron_wallet_hash'] ?? null,
            offrampWallets: $data['offramp_wallets'] ?? null,
            createdAt: new DateTimeImmutable($data['created_at'])
        );
    }
}

readonly class ListBankAccountsResponse
{
    /*
     * @param BankAccountListItem[] $data
     */
    public function __construct(
        public array $data
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            data: array_map(
                fn (array $item) => BankAccountListItem::fromArray($item),
                $data['data']
            )
        );
    }
}

readonly class GetBankAccountInput
{
    public function __construct(
        public string $receiverId,
        public string $id
    ) {}
}

readonly class BankAccountResponse
{
    public function __construct(
        public string $id,
        public string $receiverId,
        public string $accountHolderName,
        public string $accountNumber,
        public string $routingNumber,
        public BankAccountType $accountType,
        public string $bankName,
        public ?string $swiftCode,
        public ?string $iban,
        public bool $isPrimary,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            receiverId: $data['receiver_id'],
            accountHolderName: $data['account_holder_name'],
            accountNumber: $data['account_number'],
            routingNumber: $data['routing_number'],
            accountType: BankAccountType::from($data['account_type']),
            bankName: $data['bank_name'],
            swiftCode: $data['swift_code'] ?? null,
            iban: $data['iban'] ?? null,
            isPrimary: $data['is_primary'],
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at'])
        );
    }
}

readonly class DeleteBankAccountInput
{
    public function __construct(
        public string $receiverId,
        public string $id
    ) {}
}

readonly class CreatePixInput
{
    public function __construct(
        public string $receiverId,
        public string $name,
        public string $pixKey
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'pix',
            'name' => $this->name,
            'pix_key' => $this->pixKey,
        ];
    }
}

readonly class CreatePixResponse
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $pixKey,
        public DateTimeImmutable $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            name: $data['name'],
            pixKey: $data['pix_key'],
            createdAt: new DateTimeImmutable($data['created_at'])
        );
    }
}

readonly class CreateArgentinaTransfersInput
{
    public function __construct(
        public string $receiverId,
        public string $name,
        public string $beneficiaryName,
        public string $transfersAccount,
        public ArgentinaTransfers $transfersType
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'transfers_bitso',
            'name' => $this->name,
            'beneficiary_name' => $this->beneficiaryName,
            'transfers_account' => $this->transfersAccount,
            'transfers_type' => $this->transfersType->value,
        ];
    }
}

readonly class CreateArgentinaTransfersResponse
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $beneficiaryName,
        public ArgentinaTransfers $transfersType,
        public string $transfersAccount,
        public DateTimeImmutable $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            name: $data['name'],
            beneficiaryName: $data['beneficiary_name'],
            transfersType: ArgentinaTransfers::from($data['transfers_type']),
            transfersAccount: $data['transfers_account'],
            createdAt: new DateTimeImmutable($data['created_at'])
        );
    }
}

readonly class CreateSpeiInput
{
    public function __construct(
        public string $receiverId,
        public string $beneficiaryName,
        public string $name,
        public string $speiClabe,
        public string $speiInstitutionCode,
        public SpeiProtocol $speiProtocol
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'spei_bitso',
            'beneficiary_name' => $this->beneficiaryName,
            'name' => $this->name,
            'spei_clabe' => $this->speiClabe,
            'spei_institution_code' => $this->speiInstitutionCode,
            'spei_protocol' => $this->speiProtocol->value,
        ];
    }
}

readonly class CreateSpeiResponse
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $beneficiaryName,
        public SpeiProtocol $speiProtocol,
        public string $speiInstitutionCode,
        public string $speiClabe,
        public DateTimeImmutable $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            name: $data['name'],
            beneficiaryName: $data['beneficiary_name'],
            speiProtocol: SpeiProtocol::from($data['spei_protocol']),
            speiInstitutionCode: $data['spei_institution_code'],
            speiClabe: $data['spei_clabe'],
            createdAt: new DateTimeImmutable($data['created_at'])
        );
    }
}

readonly class CreateColombiaAchInput
{
    public function __construct(
        public string $receiverId,
        public string $name,
        public BankAccountType $accountType,
        public string $achCopBeneficiaryFirstName,
        public string $achCopBeneficiaryLastName,
        public string $achCopDocumentId,
        public AchCopDocument $achCopDocumentType,
        public string $achCopEmail,
        public string $achCopBankCode,
        public string $achCopBankAccount
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'ach_cop_bitso',
            'name' => $this->name,
            'account_type' => $this->accountType->value,
            'ach_cop_beneficiary_first_name' => $this->achCopBeneficiaryFirstName,
            'ach_cop_beneficiary_last_name' => $this->achCopBeneficiaryLastName,
            'ach_cop_document_id' => $this->achCopDocumentId,
            'ach_cop_document_type' => $this->achCopDocumentType->value,
            'ach_cop_email' => $this->achCopEmail,
            'ach_cop_bank_code' => $this->achCopBankCode,
            'ach_cop_bank_account' => $this->achCopBankAccount,
        ];
    }
}

readonly class CreateColombiaAchResponse
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public BankAccountType $accountType,
        public string $achCopBeneficiaryFirstName,
        public string $achCopBeneficiaryLastName,
        public string $achCopDocumentId,
        public AchCopDocument $achCopDocumentType,
        public string $achCopEmail,
        public string $achCopBankCode,
        public string $achCopBankAccount,
        public DateTimeImmutable $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            name: $data['name'],
            accountType: BankAccountType::from($data['account_type']),
            achCopBeneficiaryFirstName: $data['ach_cop_beneficiary_first_name'],
            achCopBeneficiaryLastName: $data['ach_cop_beneficiary_last_name'],
            achCopDocumentId: $data['ach_cop_document_id'],
            achCopDocumentType: AchCopDocument::from($data['ach_cop_document_type']),
            achCopEmail: $data['ach_cop_email'],
            achCopBankCode: $data['ach_cop_bank_code'],
            achCopBankAccount: $data['ach_cop_bank_account'],
            createdAt: new DateTimeImmutable($data['created_at'])
        );
    }
}

readonly class CreateAchInput
{
    public function __construct(
        public string $receiverId,
        public string $name,
        public AccountClass $accountClass,
        public string $accountNumber,
        public BankAccountType $accountType,
        public string $beneficiaryName,
        public string $routingNumber
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'ach',
            'name' => $this->name,
            'account_class' => $this->accountClass->value,
            'account_number' => $this->accountNumber,
            'account_type' => $this->accountType->value,
            'beneficiary_name' => $this->beneficiaryName,
            'routing_number' => $this->routingNumber,
        ];
    }
}

readonly class CreateAchResponse
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $beneficiaryName,
        public string $routingNumber,
        public string $accountNumber,
        public BankAccountType $accountType,
        public AccountClass $accountClass,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $city,
        public ?string $stateProvinceRegion,
        public ?Country $country,
        public ?string $postalCode,
        public ?string $achCopBeneficiaryFirstName,
        public ?string $achCopBeneficiaryLastName,
        public ?string $achCopDocumentId,
        public ?AchCopDocument $achCopDocumentType,
        public ?string $achCopEmail,
        public ?string $achCopBankCode,
        public ?string $achCopBankAccount,
        public DateTimeImmutable $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            name: $data['name'],
            beneficiaryName: $data['beneficiary_name'],
            routingNumber: $data['routing_number'],
            accountNumber: $data['account_number'],
            accountType: BankAccountType::from($data['account_type']),
            accountClass: AccountClass::from($data['account_class']),
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'] ?? null,
            stateProvinceRegion: $data['state_province_region'] ?? null,
            country: isset($data['country']) ? Country::from($data['country']) : null,
            postalCode: $data['postal_code'] ?? null,
            achCopBeneficiaryFirstName: $data['ach_cop_beneficiary_first_name'] ?? null,
            achCopBeneficiaryLastName: $data['ach_cop_beneficiary_last_name'] ?? null,
            achCopDocumentId: $data['ach_cop_document_id'] ?? null,
            achCopDocumentType: isset($data['ach_cop_document_type']) ? AchCopDocument::from($data['ach_cop_document_type']) : null,
            achCopEmail: $data['ach_cop_email'] ?? null,
            achCopBankCode: $data['ach_cop_bank_code'] ?? null,
            achCopBankAccount: $data['ach_cop_bank_account'] ?? null,
            createdAt: new DateTimeImmutable($data['created_at'])
        );
    }
}

readonly class CreateWireInput
{
    public function __construct(
        public string $receiverId,
        public string $name,
        public string $accountNumber,
        public string $beneficiaryName,
        public string $routingNumber,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public string $stateProvinceRegion,
        public Country $country,
        public string $postalCode
    ) {}

    public function toArray(): array
    {
        $data = [
            'type' => 'wire',
            'name' => $this->name,
            'account_number' => $this->accountNumber,
            'beneficiary_name' => $this->beneficiaryName,
            'routing_number' => $this->routingNumber,
            'address_line_1' => $this->addressLine1,
            'city' => $this->city,
            'state_province_region' => $this->stateProvinceRegion,
            'country' => $this->country->value,
            'postal_code' => $this->postalCode,
        ];

        if ($this->addressLine2 !== null) {
            $data['address_line_2'] = $this->addressLine2;
        }

        return $data;
    }
}

readonly class CreateWireResponse
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $beneficiaryName,
        public string $routingNumber,
        public string $accountNumber,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public string $stateProvinceRegion,
        public Country $country,
        public string $postalCode,
        public DateTimeImmutable $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            name: $data['name'],
            beneficiaryName: $data['beneficiary_name'],
            routingNumber: $data['routing_number'],
            accountNumber: $data['account_number'],
            addressLine1: $data['address_line_1'],
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'],
            stateProvinceRegion: $data['state_province_region'],
            country: Country::from($data['country']),
            postalCode: $data['postal_code'],
            createdAt: new DateTimeImmutable($data['created_at'])
        );
    }
}

readonly class CreateInternationalSwiftInput
{
    public function __construct(
        public string $receiverId,
        public string $name,
        public string $swiftAccountHolderName,
        public string $swiftAccountNumberIban,
        public string $swiftBankAddressLine1,
        public ?string $swiftBankAddressLine2,
        public string $swiftBankCity,
        public Country $swiftBankCountry,
        public string $swiftBankName,
        public string $swiftBankPostalCode,
        public string $swiftBankStateProvinceRegion,
        public string $swiftBeneficiaryAddressLine1,
        public ?string $swiftBeneficiaryAddressLine2,
        public string $swiftBeneficiaryCity,
        public Country $swiftBeneficiaryCountry,
        public string $swiftBeneficiaryPostalCode,
        public string $swiftBeneficiaryStateProvinceRegion,
        public string $swiftCodeBic,
        public ?string $swiftIntermediaryBankAccountNumberIban,
        public ?Country $swiftIntermediaryBankCountry,
        public ?string $swiftIntermediaryBankName,
        public ?string $swiftIntermediaryBankSwiftCodeBic
    ) {}

    public function toArray(): array
    {
        $data = [
            'type' => 'international_swift',
            'name' => $this->name,
            'swift_account_holder_name' => $this->swiftAccountHolderName,
            'swift_account_number_iban' => $this->swiftAccountNumberIban,
            'swift_bank_address_line_1' => $this->swiftBankAddressLine1,
            'swift_bank_city' => $this->swiftBankCity,
            'swift_bank_country' => $this->swiftBankCountry->value,
            'swift_bank_name' => $this->swiftBankName,
            'swift_bank_postal_code' => $this->swiftBankPostalCode,
            'swift_bank_state_province_region' => $this->swiftBankStateProvinceRegion,
            'swift_beneficiary_address_line_1' => $this->swiftBeneficiaryAddressLine1,
            'swift_beneficiary_city' => $this->swiftBeneficiaryCity,
            'swift_beneficiary_country' => $this->swiftBeneficiaryCountry->value,
            'swift_beneficiary_postal_code' => $this->swiftBeneficiaryPostalCode,
            'swift_beneficiary_state_province_region' => $this->swiftBeneficiaryStateProvinceRegion,
            'swift_code_bic' => $this->swiftCodeBic,
            'swift_intermediary_bank_account_number_iban' => $this->swiftIntermediaryBankAccountNumberIban,
            'swift_intermediary_bank_country' => $this->swiftIntermediaryBankCountry?->value,
            'swift_intermediary_bank_name' => $this->swiftIntermediaryBankName,
            'swift_intermediary_bank_swift_code_bic' => $this->swiftIntermediaryBankSwiftCodeBic,
        ];

        if ($this->swiftBankAddressLine2 !== null) {
            $data['swift_bank_address_line_2'] = $this->swiftBankAddressLine2;
        }

        if ($this->swiftBeneficiaryAddressLine2 !== null) {
            $data['swift_beneficiary_address_line_2'] = $this->swiftBeneficiaryAddressLine2;
        }

        return $data;
    }
}

readonly class CreateInternationalSwiftResponse
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public ?string $beneficiaryName,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $city,
        public ?string $stateProvinceRegion,
        public ?Country $country,
        public ?string $postalCode,
        public string $swiftCodeBic,
        public string $swiftAccountHolderName,
        public string $swiftAccountNumberIban,
        public string $swiftBeneficiaryAddressLine1,
        public ?string $swiftBeneficiaryAddressLine2,
        public Country $swiftBeneficiaryCountry,
        public string $swiftBeneficiaryCity,
        public string $swiftBeneficiaryStateProvinceRegion,
        public string $swiftBeneficiaryPostalCode,
        public string $swiftBankName,
        public string $swiftBankAddressLine1,
        public ?string $swiftBankAddressLine2,
        public Country $swiftBankCountry,
        public string $swiftBankCity,
        public string $swiftBankStateProvinceRegion,
        public string $swiftBankPostalCode,
        public ?string $swiftIntermediaryBankSwiftCodeBic,
        public ?string $swiftIntermediaryBankAccountNumberIban,
        public ?string $swiftIntermediaryBankName,
        public ?Country $swiftIntermediaryBankCountry,
        public DateTimeImmutable $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            name: $data['name'],
            beneficiaryName: $data['beneficiary_name'] ?? null,
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'] ?? null,
            stateProvinceRegion: $data['state_province_region'] ?? null,
            country: isset($data['country']) ? Country::from($data['country']) : null,
            postalCode: $data['postal_code'] ?? null,
            swiftCodeBic: $data['swift_code_bic'],
            swiftAccountHolderName: $data['swift_account_holder_name'],
            swiftAccountNumberIban: $data['swift_account_number_iban'],
            swiftBeneficiaryAddressLine1: $data['swift_beneficiary_address_line_1'],
            swiftBeneficiaryAddressLine2: $data['swift_beneficiary_address_line_2'] ?? null,
            swiftBeneficiaryCountry: Country::from($data['swift_beneficiary_country']),
            swiftBeneficiaryCity: $data['swift_beneficiary_city'],
            swiftBeneficiaryStateProvinceRegion: $data['swift_beneficiary_state_province_region'],
            swiftBeneficiaryPostalCode: $data['swift_beneficiary_postal_code'],
            swiftBankName: $data['swift_bank_name'],
            swiftBankAddressLine1: $data['swift_bank_address_line_1'],
            swiftBankAddressLine2: $data['swift_bank_address_line_2'] ?? null,
            swiftBankCountry: Country::from($data['swift_bank_country']),
            swiftBankCity: $data['swift_bank_city'],
            swiftBankStateProvinceRegion: $data['swift_bank_state_province_region'],
            swiftBankPostalCode: $data['swift_bank_postal_code'],
            swiftIntermediaryBankSwiftCodeBic: $data['swift_intermediary_bank_swift_code_bic'] ?? null,
            swiftIntermediaryBankAccountNumberIban: $data['swift_intermediary_bank_account_number_iban'] ?? null,
            swiftIntermediaryBankName: $data['swift_intermediary_bank_name'] ?? null,
            swiftIntermediaryBankCountry: isset($data['swift_intermediary_bank_country']) ? Country::from($data['swift_intermediary_bank_country']) : null,
            createdAt: new DateTimeImmutable($data['created_at'])
        );
    }
}

readonly class CreateRtpInput
{
    public function __construct(
        public string $receiverId,
        public string $name,
        public string $beneficiaryName,
        public string $routingNumber,
        public string $accountNumber,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public string $stateProvinceRegion,
        public Country $country,
        public string $postalCode
    ) {}

    public function toArray(): array
    {
        $data = [
            'type' => 'rtp',
            'name' => $this->name,
            'beneficiary_name' => $this->beneficiaryName,
            'routing_number' => $this->routingNumber,
            'account_number' => $this->accountNumber,
            'address_line_1' => $this->addressLine1,
            'city' => $this->city,
            'state_province_region' => $this->stateProvinceRegion,
            'country' => $this->country->value,
            'postal_code' => $this->postalCode,
        ];

        if ($this->addressLine2 !== null) {
            $data['address_line_2'] = $this->addressLine2;
        }

        return $data;
    }
}

readonly class CreateRtpResponse
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $beneficiaryName,
        public string $routingNumber,
        public string $accountNumber,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public string $stateProvinceRegion,
        public Country $country,
        public string $postalCode,
        public DateTimeImmutable $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            name: $data['name'],
            beneficiaryName: $data['beneficiary_name'],
            routingNumber: $data['routing_number'],
            accountNumber: $data['account_number'],
            addressLine1: $data['address_line_1'],
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'],
            stateProvinceRegion: $data['state_province_region'],
            country: Country::from($data['country']),
            postalCode: $data['postal_code'],
            createdAt: new DateTimeImmutable($data['created_at'])
        );
    }
}

class BankAccounts
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * List bank accounts for a receiver
     *
     * @param string $receiverId
     * @return BlindPayApiResponse<ListBankAccountsResponse>
     */
    public function list(string $receiverId): BlindPayApiResponse
    {
        if (empty($receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        $response = $this->client->get(
            "/instances/{$this->instanceId}/receivers/{$receiverId}/bank-accounts"
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                ListBankAccountsResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get a specific bank account
     *
     * @param GetBankAccountInput $input
     * @return BlindPayApiResponse<BankAccountResponse>
     */
    public function get(GetBankAccountInput $input): BlindPayApiResponse
    {
        if (empty($input->receiverId) || empty($input->id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID and ID cannot be empty')
            );
        }

        $response = $this->client->get(
            "/instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts/{$input->id}"
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                BankAccountResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Delete a bank account
     *
     * @param DeleteBankAccountInput $input
     * @return BlindPayApiResponse<null>
     */
    public function delete(DeleteBankAccountInput $input): BlindPayApiResponse
    {
        if (empty($input->receiverId) || empty($input->id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID and ID cannot be empty')
            );
        }

        return $this->client->delete(
            "/instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts/{$input->id}"
        );
    }

    /*
     * Create a PIX bank account
     *
     * @param CreatePixInput $input
     * @return BlindPayApiResponse<CreatePixResponse>
     */
    public function createPix(CreatePixInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreatePixResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create Argentina transfers bank account
     *
     * @param CreateArgentinaTransfersInput $input
     * @return BlindPayApiResponse<CreateArgentinaTransfersResponse>
     */
    public function createArgentinaTransfers(CreateArgentinaTransfersInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateArgentinaTransfersResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create SPEI bank account
     *
     * @param CreateSpeiInput $input
     * @return BlindPayApiResponse<CreateSpeiResponse>
     */
    public function createSpei(CreateSpeiInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateSpeiResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create Colombia ACH bank account
     *
     * @param CreateColombiaAchInput $input
     * @return BlindPayApiResponse<CreateColombiaAchResponse>
     */
    public function createColombiaAch(CreateColombiaAchInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateColombiaAchResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create ACH bank account
     *
     * @param CreateAchInput $input
     * @return BlindPayApiResponse<CreateAchResponse>
     */
    public function createAch(CreateAchInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateAchResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create Wire bank account
     *
     * @param CreateWireInput $input
     * @return BlindPayApiResponse<CreateWireResponse>
     */
    public function createWire(CreateWireInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateWireResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create International SWIFT bank account
     *
     * @param CreateInternationalSwiftInput $input
     * @return BlindPayApiResponse<CreateInternationalSwiftResponse>
     */
    public function createInternationalSwift(CreateInternationalSwiftInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateInternationalSwiftResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create RTP bank account
     *
     * @param CreateRtpInput $input
     * @return BlindPayApiResponse<CreateRtpResponse>
     */
    public function createRtp(CreateRtpInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateRtpResponse::fromArray($response->data)
            );
        }

        return $response;
    }
}
