<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\BankAccounts;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Internal\Query;
use BlindPay\SDK\Types\AccountClass;
use BlindPay\SDK\Types\BankAccountType;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Country;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\Rail;
use BlindPay\SDK\Types\RecipientRelationship;
use BlindPay\SDK\Types\RequestOptions;
use DateTimeImmutable;

enum ArgentinaTransfers: string
{
    case CVU = 'CVU';
    case CBU = 'CBU';
    case ALIAS = 'ALIAS';
}

/**
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
    case CLABE = 'clabe';
    case DEBITCARD = 'debitcard';
    case PHONENUM = 'phonenum';
}

readonly class BankAccountListItem
{
    /**
     * @param  array<array{address: string, id: string, network: string, external_id: string}>|null  $offrampWallets
     */
    public function __construct(
        public string $id,
        public Rail|string $type,
        public string $name,
        public ?string $pixKey,
        public ?string $beneficiaryName,
        public ?string $routingNumber,
        public ?string $accountNumber,
        public BankAccountType|string|null $accountType,
        public AccountClass|string|null $accountClass,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $city,
        public ?string $stateProvinceRegion,
        public Country|string|null $country,
        public ?string $postalCode,
        public ?string $speiProtocol,
        public ?string $speiInstitutionCode,
        public ?string $speiClabe,
        public ArgentinaTransfers|string|null $transfersType,
        public ?string $transfersAccount,
        public ?string $achCopBeneficiaryFirstName,
        public ?string $achCopBeneficiaryLastName,
        public ?string $achCopDocumentId,
        public AchCopDocument|string|null $achCopDocumentType,
        public ?string $achCopEmail,
        public ?string $achCopBankCode,
        public ?string $achCopBankAccount,
        public ?string $swiftCodeBic,
        public ?string $swiftAccountHolderName,
        public ?string $swiftAccountNumberIban,
        public ?string $swiftBeneficiaryAddressLine1,
        public ?string $swiftBeneficiaryAddressLine2,
        public Country|string|null $swiftBeneficiaryCountry,
        public ?string $swiftBeneficiaryCity,
        public ?string $swiftBeneficiaryStateProvinceRegion,
        public ?string $swiftBeneficiaryPostalCode,
        public ?string $swiftBankName,
        public ?string $swiftBankAddressLine1,
        public ?string $swiftBankAddressLine2,
        public Country|string|null $swiftBankCountry,
        public ?string $swiftBankCity,
        public ?string $swiftBankStateProvinceRegion,
        public ?string $swiftBankPostalCode,
        public ?string $swiftIntermediaryBankSwiftCodeBic,
        public ?string $swiftIntermediaryBankAccountNumberIban,
        public ?string $swiftIntermediaryBankName,
        public Country|string|null $swiftIntermediaryBankCountry,
        public ?string $tronWalletHash,
        public ?array $offrampWallets,
        public ?DateTimeImmutable $createdAt,
        public ?string $tedBankCode = null,
        public ?string $tedBranchCode = null,
        public ?string $tedCpfCnpj = null,
        public ?string $sepaBeneficiaryBic = null,
        public ?string $swiftIfscBranchCode = null,
        public ?string $swiftIntermediaryBankRoutingNumber = null,
        public ?string $sepaIban = null,
        public ?string $sepaBeneficiaryLegalName = null,
        public ?string $sepaBeneficiaryAddressLine1 = null,
        public ?string $sepaBeneficiaryAddressLine2 = null,
        public ?string $sepaBeneficiaryCity = null,
        public ?string $sepaBeneficiaryStateProvinceRegion = null,
        public ?string $sepaBeneficiaryPostalCode = null,
        public Country|string|null $sepaBeneficiaryCountry = null,
        public ?string $fasterPaymentsSortCode = null,
        public ?string $fasterPaymentsAccountNumber = null,
        public ?string $fasterPaymentsBeneficiaryLegalName = null,
        public ?string $fasterPaymentsBeneficiaryAddressLine1 = null,
        public ?string $fasterPaymentsBeneficiaryAddressLine2 = null,
        public ?string $fasterPaymentsBeneficiaryCity = null,
        public ?string $fasterPaymentsBeneficiaryStateProvinceRegion = null,
        public ?string $fasterPaymentsBeneficiaryPostalCode = null,
        public Country|string|null $fasterPaymentsBeneficiaryCountry = null,
        public ?string $businessIndustry = null,
        public ?string $phoneNumber = null,
        public ?string $taxId = null,
        public ?string $dateOfBirth = null,
        public ?string $pixSafeBankCode = null,
        public ?string $pixSafeBranchCode = null,
        public ?string $pixSafeCpfCnpj = null,
        public ?string $status = null,
        public RecipientRelationship|string|null $recipientRelationship = null,
        public ?string $swiftPaymentCode = null,
        public ?string $plaidConnectedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: Decode::enum(Rail::class, $data['type']),
            name: $data['name'],
            pixKey: $data['pix_key'] ?? null,
            beneficiaryName: $data['beneficiary_name'] ?? null,
            routingNumber: $data['routing_number'] ?? null,
            accountNumber: $data['account_number'] ?? null,
            accountType: Decode::enum(BankAccountType::class, $data['account_type'] ?? null),
            accountClass: Decode::enum(AccountClass::class, $data['account_class'] ?? null),
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'] ?? null,
            stateProvinceRegion: $data['state_province_region'] ?? null,
            country: Decode::enum(Country::class, $data['country'] ?? null),
            postalCode: $data['postal_code'] ?? null,
            speiProtocol: $data['spei_protocol'] ?? null,
            speiInstitutionCode: $data['spei_institution_code'] ?? null,
            speiClabe: $data['spei_clabe'] ?? null,
            transfersType: Decode::enum(ArgentinaTransfers::class, $data['transfers_type'] ?? null),
            transfersAccount: $data['transfers_account'] ?? null,
            achCopBeneficiaryFirstName: $data['ach_cop_beneficiary_first_name'] ?? null,
            achCopBeneficiaryLastName: $data['ach_cop_beneficiary_last_name'] ?? null,
            achCopDocumentId: $data['ach_cop_document_id'] ?? null,
            achCopDocumentType: Decode::enum(AchCopDocument::class, $data['ach_cop_document_type'] ?? null),
            achCopEmail: $data['ach_cop_email'] ?? null,
            achCopBankCode: $data['ach_cop_bank_code'] ?? null,
            achCopBankAccount: $data['ach_cop_bank_account'] ?? null,
            swiftCodeBic: $data['swift_code_bic'] ?? null,
            swiftAccountHolderName: $data['swift_account_holder_name'] ?? null,
            swiftAccountNumberIban: $data['swift_account_number_iban'] ?? null,
            swiftBeneficiaryAddressLine1: $data['swift_beneficiary_address_line_1'] ?? null,
            swiftBeneficiaryAddressLine2: $data['swift_beneficiary_address_line_2'] ?? null,
            swiftBeneficiaryCountry: Decode::enum(Country::class, $data['swift_beneficiary_country'] ?? null),
            swiftBeneficiaryCity: $data['swift_beneficiary_city'] ?? null,
            swiftBeneficiaryStateProvinceRegion: $data['swift_beneficiary_state_province_region'] ?? null,
            swiftBeneficiaryPostalCode: $data['swift_beneficiary_postal_code'] ?? null,
            swiftBankName: $data['swift_bank_name'] ?? null,
            swiftBankAddressLine1: $data['swift_bank_address_line_1'] ?? null,
            swiftBankAddressLine2: $data['swift_bank_address_line_2'] ?? null,
            swiftBankCountry: Decode::enum(Country::class, $data['swift_bank_country'] ?? null),
            swiftBankCity: $data['swift_bank_city'] ?? null,
            swiftBankStateProvinceRegion: $data['swift_bank_state_province_region'] ?? null,
            swiftBankPostalCode: $data['swift_bank_postal_code'] ?? null,
            swiftIntermediaryBankSwiftCodeBic: $data['swift_intermediary_bank_swift_code_bic'] ?? null,
            swiftIntermediaryBankAccountNumberIban: $data['swift_intermediary_bank_account_number_iban'] ?? null,
            swiftIntermediaryBankName: $data['swift_intermediary_bank_name'] ?? null,
            swiftIntermediaryBankCountry: Decode::enum(Country::class, $data['swift_intermediary_bank_country'] ?? null),
            tronWalletHash: $data['tron_wallet_hash'] ?? null,
            offrampWallets: $data['offramp_wallets'] ?? null,
            createdAt: isset($data['created_at'])
                ? new DateTimeImmutable($data['created_at'])
                : null,
            tedBankCode: $data['ted_bank_code'] ?? null,
            tedBranchCode: $data['ted_branch_code'] ?? null,
            tedCpfCnpj: $data['ted_cpf_cnpj'] ?? null,
            sepaBeneficiaryBic: $data['sepa_beneficiary_bic'] ?? null,
            swiftIfscBranchCode: $data['swift_ifsc_branch_code'] ?? null,
            swiftIntermediaryBankRoutingNumber: $data['swift_intermediary_bank_routing_number'] ?? null,
            sepaIban: $data['sepa_iban'] ?? null,
            sepaBeneficiaryLegalName: $data['sepa_beneficiary_legal_name'] ?? null,
            sepaBeneficiaryAddressLine1: $data['sepa_beneficiary_address_line_1'] ?? null,
            sepaBeneficiaryAddressLine2: $data['sepa_beneficiary_address_line_2'] ?? null,
            sepaBeneficiaryCity: $data['sepa_beneficiary_city'] ?? null,
            sepaBeneficiaryStateProvinceRegion: $data['sepa_beneficiary_state_province_region'] ?? null,
            sepaBeneficiaryPostalCode: $data['sepa_beneficiary_postal_code'] ?? null,
            sepaBeneficiaryCountry: Decode::enum(Country::class, $data['sepa_beneficiary_country'] ?? null),
            fasterPaymentsSortCode: $data['faster_payments_sort_code'] ?? null,
            fasterPaymentsAccountNumber: $data['faster_payments_account_number'] ?? null,
            fasterPaymentsBeneficiaryLegalName: $data['faster_payments_beneficiary_legal_name'] ?? null,
            fasterPaymentsBeneficiaryAddressLine1: $data['faster_payments_beneficiary_address_line_1'] ?? null,
            fasterPaymentsBeneficiaryAddressLine2: $data['faster_payments_beneficiary_address_line_2'] ?? null,
            fasterPaymentsBeneficiaryCity: $data['faster_payments_beneficiary_city'] ?? null,
            fasterPaymentsBeneficiaryStateProvinceRegion: $data['faster_payments_beneficiary_state_province_region'] ?? null,
            fasterPaymentsBeneficiaryPostalCode: $data['faster_payments_beneficiary_postal_code'] ?? null,
            fasterPaymentsBeneficiaryCountry: Decode::enum(Country::class, $data['faster_payments_beneficiary_country'] ?? null),
            businessIndustry: $data['business_industry'] ?? null,
            phoneNumber: $data['phone_number'] ?? null,
            taxId: $data['tax_id'] ?? null,
            dateOfBirth: $data['date_of_birth'] ?? null,
            pixSafeBankCode: $data['pix_safe_bank_code'] ?? null,
            pixSafeBranchCode: $data['pix_safe_branch_code'] ?? null,
            pixSafeCpfCnpj: $data['pix_safe_cpf_cnpj'] ?? null,
            status: $data['status'] ?? null,
            recipientRelationship: Decode::enum(RecipientRelationship::class, $data['recipient_relationship'] ?? null),
            swiftPaymentCode: $data['swift_payment_code'] ?? null,
            plaidConnectedAt: $data['plaid_connected_at'] ?? null,
        );
    }
}

/** Filters for a customer's bank accounts (the API returns every match; no pagination). */
readonly class ListBankAccountsInput
{
    /**
     * @param  string|null  $status  verifying, approved, rejected or deprecated
     */
    public function __construct(
        public ?string $status = null,
        public ?Rail $type = null,
        public ?string $name = null,
        public ?string $bankAccountId = null,
        public ?string $country = null,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'type' => $this->type?->value,
            'name' => $this->name,
            'bank_account_id' => $this->bankAccountId,
            'country' => $this->country,
        ], static fn ($value) => $value !== null);
    }
}

readonly class ListBankAccountsResponse
{
    /**
     * @param  BankAccountListItem[]  $data
     */
    public function __construct(
        public array $data
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            data: array_map(
                fn (array $item) => BankAccountListItem::fromArray($item),
                $data
            )
        );
    }
}

readonly class CreatePlaidLinkInput
{
    /**
     * @param  string|null  $redirectUrl  where Plaid sends the customer back once the account is linked
     */
    public function __construct(
        public string $customerId,
        public ?string $redirectUrl = null,
    ) {}

    public function toArray(): array
    {
        return array_filter(['redirect_url' => $this->redirectUrl], static fn ($value) => $value !== null);
    }
}

readonly class PlaidLinkResponse
{
    public function __construct(
        /** open it in the customer's browser to link a US bank account through Plaid */
        public string $url,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(url: $data['url']);
    }
}

readonly class GetBankAccountInput
{
    public function __construct(
        public string $customerId,
        public string $id
    ) {}
}

/**
 * @deprecated Never matched the API (BankAccountOut has none of customer_id, account_holder_name,
 *             bank_name, is_primary or updated_at), so decoding always failed. get() returns a
 *             BankAccountListItem, the type the API's BankAccountOut maps to.
 */
readonly class BankAccountResponse
{
    public function __construct(
        public string $id,
        public string $customerId,
        public string $accountHolderName,
        public string $accountNumber,
        public string $routingNumber,
        public BankAccountType|string $accountType,
        public string $bankName,
        public ?string $swiftCode,
        public ?string $iban,
        public bool $isPrimary,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?string $sepaBeneficiaryBic = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            customerId: $data['customer_id'],
            accountHolderName: $data['account_holder_name'],
            accountNumber: $data['account_number'],
            routingNumber: $data['routing_number'],
            accountType: Decode::enum(BankAccountType::class, $data['account_type']),
            bankName: $data['bank_name'],
            swiftCode: $data['swift_code'] ?? null,
            iban: $data['iban'] ?? null,
            isPrimary: $data['is_primary'],
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
            sepaBeneficiaryBic: $data['sepa_beneficiary_bic'] ?? null
        );
    }
}

readonly class DeleteBankAccountInput
{
    public function __construct(
        public string $customerId,
        public string $id
    ) {}
}

readonly class CreatePixInput
{
    public function __construct(
        public string $customerId,
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

readonly class CreateArgentinaTransfersInput
{
    public function __construct(
        public string $customerId,
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

readonly class CreateSpeiInput
{
    public function __construct(
        public string $customerId,
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

readonly class CreateColombiaAchInput
{
    public function __construct(
        public string $customerId,
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

readonly class CreateAchInput
{
    public function __construct(
        public string $customerId,
        public string $name,
        public AccountClass $accountClass,
        public string $accountNumber,
        public BankAccountType $accountType,
        public string $beneficiaryName,
        public string $routingNumber,
        public RecipientRelationship $recipientRelationship,
        public string $addressLine1,
        public string $city,
        public string $stateProvinceRegion,
        public Country $country,
        public string $postalCode,
        public ?string $addressLine2 = null,
        public ?string $businessIndustry = null,
        public ?string $phoneNumber = null,
        public ?string $taxId = null,
        public ?string $dateOfBirth = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'type' => 'ach',
            'name' => $this->name,
            'account_class' => $this->accountClass->value,
            'account_number' => $this->accountNumber,
            'account_type' => $this->accountType->value,
            'beneficiary_name' => $this->beneficiaryName,
            'routing_number' => $this->routingNumber,
            'recipient_relationship' => $this->recipientRelationship->value,
            'address_line_1' => $this->addressLine1,
            'city' => $this->city,
            'state_province_region' => $this->stateProvinceRegion,
            'country' => $this->country->value,
            'postal_code' => $this->postalCode,
        ];

        if ($this->addressLine2 !== null) {
            $data['address_line_2'] = $this->addressLine2;
        }

        if ($this->businessIndustry !== null) {
            $data['business_industry'] = $this->businessIndustry;
        }

        if ($this->phoneNumber !== null) {
            $data['phone_number'] = $this->phoneNumber;
        }

        if ($this->taxId !== null) {
            $data['tax_id'] = $this->taxId;
        }

        if ($this->dateOfBirth !== null) {
            $data['date_of_birth'] = $this->dateOfBirth;
        }

        return $data;
    }
}

readonly class CreateWireInput
{
    public function __construct(
        public string $customerId,
        public string $name,
        public string $accountNumber,
        public string $beneficiaryName,
        public string $routingNumber,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public string $stateProvinceRegion,
        public Country $country,
        public string $postalCode,
        public AccountClass $accountClass,
        public RecipientRelationship $recipientRelationship,
        public ?string $businessIndustry = null,
        public ?string $phoneNumber = null,
        public ?string $taxId = null,
        public ?string $dateOfBirth = null
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
            'account_class' => $this->accountClass->value,
            'recipient_relationship' => $this->recipientRelationship->value,
        ];

        if ($this->addressLine2 !== null) {
            $data['address_line_2'] = $this->addressLine2;
        }

        if ($this->businessIndustry !== null) {
            $data['business_industry'] = $this->businessIndustry;
        }

        if ($this->phoneNumber !== null) {
            $data['phone_number'] = $this->phoneNumber;
        }

        if ($this->taxId !== null) {
            $data['tax_id'] = $this->taxId;
        }

        if ($this->dateOfBirth !== null) {
            $data['date_of_birth'] = $this->dateOfBirth;
        }

        return $data;
    }
}

readonly class CreateInternationalSwiftInput
{
    public function __construct(
        public string $customerId,
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
        public ?string $swiftIntermediaryBankSwiftCodeBic,
        public AccountClass $accountClass,
        public RecipientRelationship $recipientRelationship,
        public ?string $swiftPaymentCode = null,
        public ?string $businessIndustry = null,
        public ?string $phoneNumber = null,
        public ?string $taxId = null,
        public ?string $dateOfBirth = null,
        public ?string $tedBankCode = null,
        public ?string $tedBranchCode = null,
        public ?string $tedCpfCnpj = null,
        public ?string $sepaBeneficiaryBic = null
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
            'account_class' => $this->accountClass->value,
            'recipient_relationship' => $this->recipientRelationship->value,
        ];

        if ($this->swiftBankAddressLine2 !== null) {
            $data['swift_bank_address_line_2'] = $this->swiftBankAddressLine2;
        }

        if ($this->swiftBeneficiaryAddressLine2 !== null) {
            $data['swift_beneficiary_address_line_2'] = $this->swiftBeneficiaryAddressLine2;
        }

        if ($this->swiftPaymentCode !== null) {
            $data['swift_payment_code'] = $this->swiftPaymentCode;
        }

        if ($this->businessIndustry !== null) {
            $data['business_industry'] = $this->businessIndustry;
        }

        if ($this->phoneNumber !== null) {
            $data['phone_number'] = $this->phoneNumber;
        }

        if ($this->taxId !== null) {
            $data['tax_id'] = $this->taxId;
        }

        if ($this->dateOfBirth !== null) {
            $data['date_of_birth'] = $this->dateOfBirth;
        }

        if ($this->tedBankCode !== null) {
            $data['ted_bank_code'] = $this->tedBankCode;
        }

        if ($this->tedBranchCode !== null) {
            $data['ted_branch_code'] = $this->tedBranchCode;
        }

        if ($this->tedCpfCnpj !== null) {
            $data['ted_cpf_cnpj'] = $this->tedCpfCnpj;
        }

        if ($this->sepaBeneficiaryBic !== null) {
            $data['sepa_beneficiary_bic'] = $this->sepaBeneficiaryBic;
        }

        return $data;
    }
}

readonly class CreateRtpInput
{
    public function __construct(
        public string $customerId,
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
        public AccountClass $accountClass,
        public RecipientRelationship $recipientRelationship,
        public ?string $businessIndustry = null,
        public ?string $phoneNumber = null,
        public ?string $taxId = null,
        public ?string $dateOfBirth = null
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
            'account_class' => $this->accountClass->value,
            'recipient_relationship' => $this->recipientRelationship->value,
        ];

        if ($this->addressLine2 !== null) {
            $data['address_line_2'] = $this->addressLine2;
        }

        if ($this->businessIndustry !== null) {
            $data['business_industry'] = $this->businessIndustry;
        }

        if ($this->phoneNumber !== null) {
            $data['phone_number'] = $this->phoneNumber;
        }

        if ($this->taxId !== null) {
            $data['tax_id'] = $this->taxId;
        }

        if ($this->dateOfBirth !== null) {
            $data['date_of_birth'] = $this->dateOfBirth;
        }

        return $data;
    }
}

readonly class CreatePixSafeInput
{
    public function __construct(
        public string $customerId,
        public string $name,
        public string $accountNumber,
        public BankAccountType $accountType,
        public string $pixSafeBankCode,
        public string $pixSafeBranchCode,
        public string $pixSafeCpfCnpj
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'pix_safe',
            'name' => $this->name,
            'account_number' => $this->accountNumber,
            'account_type' => $this->accountType->value,
            'pix_safe_bank_code' => $this->pixSafeBankCode,
            'pix_safe_branch_code' => $this->pixSafeBranchCode,
            'pix_safe_cpf_cnpj' => $this->pixSafeCpfCnpj,
        ];
    }
}

class BankAccounts
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /**
     * List bank accounts for a customer
     *
     * @return BlindPayApiResponse<ListBankAccountsResponse>
     */
    public function list(
        string $customerId,
        ?ListBankAccountsInput $params = null,
        ?RequestOptions $options = null
    ): BlindPayApiResponse {
        if ($customerId === '') {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Customer ID cannot be empty')
            );
        }

        $response = $this->client->get(
            Path::of('instances/%s/customers/%s/bank-accounts', $this->instanceId, $customerId).Query::build($params?->toArray() ?? []),
            $options
        );

        return $response->map(fn (array $data) => ListBankAccountsResponse::fromArray($data));
    }

    /**
     * Get a specific bank account
     *
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function get(GetBankAccountInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($input->customerId === '' || $input->id === '') {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Customer ID and ID cannot be empty')
            );
        }

        $response = $this->client->get(
            Path::of('instances/%s/customers/%s/bank-accounts/%s', $this->instanceId, $input->customerId, $input->id),
            $options
        );

        return $response->map(fn (array $data) => BankAccountListItem::fromArray($data));
    }

    /**
     * Delete a bank account
     *
     * @return BlindPayApiResponse<null>
     */
    public function delete(DeleteBankAccountInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($input->customerId === '' || $input->id === '') {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Customer ID and ID cannot be empty')
            );
        }

        return $this->client->delete(
            Path::of('instances/%s/customers/%s/bank-accounts/%s', $this->instanceId, $input->customerId, $input->id),
            null,
            $options
        );
    }

    /**
     * Create a PIX bank account
     *
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function createPix(CreatePixInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->createAccount($input->customerId, $input->toArray(), $options);
    }

    /**
     * Create Argentina transfers bank account
     *
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function createArgentinaTransfers(CreateArgentinaTransfersInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->createAccount($input->customerId, $input->toArray(), $options);
    }

    /**
     * Create SPEI bank account
     *
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function createSpei(CreateSpeiInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->createAccount($input->customerId, $input->toArray(), $options);
    }

    /**
     * Create Colombia ACH bank account
     *
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function createColombiaAch(CreateColombiaAchInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->createAccount($input->customerId, $input->toArray(), $options);
    }

    /**
     * Create ACH bank account
     *
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function createAch(CreateAchInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->createAccount($input->customerId, $input->toArray(), $options);
    }

    /**
     * Create Wire bank account
     *
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function createWire(CreateWireInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->createAccount($input->customerId, $input->toArray(), $options);
    }

    /**
     * Create International SWIFT bank account
     *
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function createInternationalSwift(CreateInternationalSwiftInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->createAccount($input->customerId, $input->toArray(), $options);
    }

    /**
     * Create RTP bank account
     *
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function createRtp(CreateRtpInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->createAccount($input->customerId, $input->toArray(), $options);
    }

    /**
     * Create PIX Safe bank account
     *
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function createPixSafe(CreatePixSafeInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->createAccount($input->customerId, $input->toArray(), $options);
    }

    /**
     * Create SEPA bank account
     *
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function createSepa(CreateSepaInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->createAccount($input->customerId, $input->toArray(), $options);
    }

    /**
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function createTed(CreateTedInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->createAccount($input->customerId, $input->toArray(), $options);
    }

    /**
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    public function createFasterPayments(CreateFasterPaymentsInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->createAccount($input->customerId, $input->toArray(), $options);
    }

    /**
     * Start a Plaid Link session to add a customer's US bank account; send the customer to the
     * returned URL.
     *
     * @return BlindPayApiResponse<PlaidLinkResponse>
     */
    public function createPlaidLink(CreatePlaidLinkInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($input->customerId === '') {
            return BlindPayApiResponse::error(ErrorResponse::validation('Customer ID cannot be empty'));
        }

        return $this->client->post(
            Path::of('instances/%s/customers/%s/bank-accounts/plaid', $this->instanceId, $input->customerId),
            $input->toArray(),
            $options
        )->map(fn (array $data) => PlaidLinkResponse::fromArray($data));
    }

    /**
     * POST one bank account (every rail shares the route and the response type).
     *
     * @param  array<string, mixed>  $body
     * @return BlindPayApiResponse<BankAccountListItem>
     */
    private function createAccount(string $customerId, array $body, ?RequestOptions $options): BlindPayApiResponse
    {
        if ($customerId === '') {
            return BlindPayApiResponse::error(ErrorResponse::validation('Customer ID cannot be empty'));
        }

        return $this->client->post(Path::of('instances/%s/customers/%s/bank-accounts', $this->instanceId, $customerId), $body, $options)
            ->map(fn (array $data) => BankAccountListItem::fromArray($data));
    }
}

/** A Brazilian bank account paid by TED. */
readonly class CreateTedInput
{
    /**
     * @param  string  $accountNumber  1-12 digits, a hyphen and the verifier digit (e.g. 12345-2)
     * @param  string  $tedBankCode  the bank's COMPE code (3-5 digits)
     * @param  string  $tedBranchCode  4 digits, optionally with a verifier digit (e.g. 0001-9)
     */
    public function __construct(
        public string $customerId,
        public string $name,
        public string $beneficiaryName,
        public string $accountNumber,
        public BankAccountType $accountType,
        public string $tedBankCode,
        public string $tedBranchCode,
        public string $tedCpfCnpj,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'ted',
            'name' => $this->name,
            'beneficiary_name' => $this->beneficiaryName,
            'account_number' => $this->accountNumber,
            'account_type' => $this->accountType->value,
            'ted_bank_code' => $this->tedBankCode,
            'ted_branch_code' => $this->tedBranchCode,
            'ted_cpf_cnpj' => $this->tedCpfCnpj,
        ];
    }
}

/** A UK bank account paid by Faster Payments (GBP). */
readonly class CreateFasterPaymentsInput
{
    public function __construct(
        public string $customerId,
        public string $name,
        public AccountClass $accountClass,
        public string $fasterPaymentsSortCode,
        public string $fasterPaymentsAccountNumber,
        public string $fasterPaymentsBeneficiaryLegalName,
        public string $fasterPaymentsBeneficiaryAddressLine1,
        public string $fasterPaymentsBeneficiaryCity,
        public Country $fasterPaymentsBeneficiaryCountry,
        public ?string $fasterPaymentsBeneficiaryAddressLine2 = null,
        public ?string $fasterPaymentsBeneficiaryStateProvinceRegion = null,
        public ?string $fasterPaymentsBeneficiaryPostalCode = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'type' => 'faster_payments',
            'name' => $this->name,
            'account_class' => $this->accountClass->value,
            'faster_payments_sort_code' => $this->fasterPaymentsSortCode,
            'faster_payments_account_number' => $this->fasterPaymentsAccountNumber,
            'faster_payments_beneficiary_legal_name' => $this->fasterPaymentsBeneficiaryLegalName,
            'faster_payments_beneficiary_address_line_1' => $this->fasterPaymentsBeneficiaryAddressLine1,
            'faster_payments_beneficiary_address_line_2' => $this->fasterPaymentsBeneficiaryAddressLine2,
            'faster_payments_beneficiary_city' => $this->fasterPaymentsBeneficiaryCity,
            'faster_payments_beneficiary_state_province_region' => $this->fasterPaymentsBeneficiaryStateProvinceRegion,
            'faster_payments_beneficiary_postal_code' => $this->fasterPaymentsBeneficiaryPostalCode,
            'faster_payments_beneficiary_country' => $this->fasterPaymentsBeneficiaryCountry->value,
        ], static fn ($value) => $value !== null);
    }
}

readonly class CreateSepaInput
{
    public function __construct(
        public string $customerId,
        public string $name,
        public AccountClass $accountClass,
        public string $sepaIban,
        public string $sepaBeneficiaryBic,
        public string $sepaBeneficiaryLegalName,
        public string $sepaBeneficiaryAddressLine1,
        public string $sepaBeneficiaryCity,
        public string $sepaBeneficiaryPostalCode,
        public Country $sepaBeneficiaryCountry,
        public ?string $sepaBeneficiaryAddressLine2 = null,
        public ?string $sepaBeneficiaryStateProvinceRegion = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'type' => 'sepa',
            'name' => $this->name,
            'account_class' => $this->accountClass->value,
            'sepa_iban' => $this->sepaIban,
            'sepa_beneficiary_bic' => $this->sepaBeneficiaryBic,
            'sepa_beneficiary_legal_name' => $this->sepaBeneficiaryLegalName,
            'sepa_beneficiary_address_line_1' => $this->sepaBeneficiaryAddressLine1,
            'sepa_beneficiary_city' => $this->sepaBeneficiaryCity,
            'sepa_beneficiary_postal_code' => $this->sepaBeneficiaryPostalCode,
            'sepa_beneficiary_country' => $this->sepaBeneficiaryCountry->value,
        ];

        if ($this->sepaBeneficiaryAddressLine2 !== null) {
            $data['sepa_beneficiary_address_line_2'] = $this->sepaBeneficiaryAddressLine2;
        }
        if ($this->sepaBeneficiaryStateProvinceRegion !== null) {
            $data['sepa_beneficiary_state_province_region'] = $this->sepaBeneficiaryStateProvinceRegion;
        }

        return $data;
    }
}
