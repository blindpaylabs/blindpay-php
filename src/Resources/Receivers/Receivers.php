<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Receivers;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\AccountClass;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Country;
use DateTimeImmutable;

enum ProofOfAddressDocType: string
{
    case UTILITY_BILL = 'UTILITY_BILL';
    case BANK_STATEMENT = 'BANK_STATEMENT';
    case RENTAL_AGREEMENT = 'RENTAL_AGREEMENT';
    case TAX_DOCUMENT = 'TAX_DOCUMENT';
    case GOVERNMENT_CORRESPONDENCE = 'GOVERNMENT_CORRESPONDENCE';
}

enum PurposeOfTransactions: string
{
    case BUSINESS_TRANSACTIONS = 'business_transactions';
    case CHARITABLE_DONATIONS = 'charitable_donations';
    case INVESTMENT_PURPOSES = 'investment_purposes';
    case PAYMENTS_TO_FRIENDS_OR_FAMILY_ABROAD = 'payments_to_friends_or_family_abroad';
    case PERSONAL_OR_LIVING_EXPENSES = 'personal_or_living_expenses';
    case PROTECT_WEALTH = 'protect_wealth';
    case PURCHASE_GOOD_AND_SERVICES = 'purchase_good_and_services';
    case RECEIVE_PAYMENT_FOR_FREELANCING = 'receive_payment_for_freelancing';
    case RECEIVE_SALARY = 'receive_salary';
    case OTHER = 'other';
}

enum SourceOfFundsDocType: string
{
    case BUSINESS_INCOME = 'business_income';
    case GAMBLING_PROCEEDS = 'gambling_proceeds';
    case GIFTS = 'gifts';
    case GOVERNMENT_BENEFITS = 'government_benefits';
    case INHERITANCE = 'inheritance';
    case INVESTMENT_LOANS = 'investment_loans';
    case PENSION_RETIREMENT = 'pension_retirement';
    case SALARY = 'salary';
    case SALE_OF_ASSETS_REAL_ESTATE = 'sale_of_assets_real_estate';
    case SAVINGS = 'savings';
    case ESOPS = 'esops';
    case INVESTMENT_PROCEEDS = 'investment_proceeds';
    case SOMEONE_ELSE_FUNDS = 'someone_else_funds';
}

enum IdentificationDocument: string
{
    case PASSPORT = 'PASSPORT';
    case ID_CARD = 'ID_CARD';
    case DRIVERS = 'DRIVERS';
}

enum KycType: string
{
    case LIGHT = 'light';
    case STANDARD = 'standard';
    case ENHANCED = 'enhanced';
}

enum OwnerRole: string
{
    case BENEFICIAL_CONTROLLING = 'beneficial_controlling';
    case BENEFICIAL_OWNER = 'beneficial_owner';
    case CONTROLLING_PERSON = 'controlling_person';
}

/*
 * Limit increase request status
 */
enum LimitIncreaseRequestStatus: string
{
    case IN_REVIEW = 'in_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}

enum LimitIncreaseRequestSupportingDocumentType: string
{
    case INDIVIDUAL_BANK_STATEMENT = 'individual_bank_statement';
    case INDIVIDUAL_TAX_RETURN = 'individual_tax_return';
    case INDIVIDUAL_PROOF_OF_INCOME = 'individual_proof_of_income';
    case BUSINESS_BANK_STATEMENT = 'business_bank_statement';
    case BUSINESS_FINANCIAL_STATEMENTS = 'business_financial_statements';
    case BUSINESS_TAX_RETURN = 'business_tax_return';
}

readonly class Owner
{
    public function __construct(
        public string $id,
        public string $instanceId,
        public string $receiverId,
        public OwnerRole $role,
        public string $firstName,
        public string $lastName,
        public string $dateOfBirth,
        public string $taxId,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public string $stateProvinceRegion,
        public Country $country,
        public string $postalCode,
        public Country $idDocCountry,
        public IdentificationDocument $idDocType,
        public string $idDocFrontFile,
        public ?string $idDocBackFile,
        public ProofOfAddressDocType $proofOfAddressDocType,
        public string $proofOfAddressDocFile
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            instanceId: $data['instance_id'],
            receiverId: $data['receiver_id'],
            role: OwnerRole::from($data['role']),
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            dateOfBirth: $data['date_of_birth'],
            taxId: $data['tax_id'],
            addressLine1: $data['address_line_1'],
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'],
            stateProvinceRegion: $data['state_province_region'],
            country: Country::from($data['country']),
            postalCode: $data['postal_code'],
            idDocCountry: Country::from($data['id_doc_country']),
            idDocType: IdentificationDocument::from($data['id_doc_type']),
            idDocFrontFile: $data['id_doc_front_file'],
            idDocBackFile: $data['id_doc_back_file'] ?? null,
            proofOfAddressDocType: ProofOfAddressDocType::from($data['proof_of_address_doc_type']),
            proofOfAddressDocFile: $data['proof_of_address_doc_file']
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'date_of_birth' => $this->dateOfBirth,
            'tax_id' => $this->taxId,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'city' => $this->city,
            'state_province_region' => $this->stateProvinceRegion,
            'country' => $this->country->value,
            'postal_code' => $this->postalCode,
            'id_doc_country' => $this->idDocCountry->value,
            'id_doc_type' => $this->idDocType->value,
            'id_doc_front_file' => $this->idDocFrontFile,
            'id_doc_back_file' => $this->idDocBackFile,
            'proof_of_address_doc_type' => $this->proofOfAddressDocType->value,
            'proof_of_address_doc_file' => $this->proofOfAddressDocFile,
        ];
    }
}

readonly class KycWarning
{
    public function __construct(
        public ?string $code,
        public ?string $message,
        public ?string $resolutionStatus,
        public ?string $warningId
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'] ?? null,
            message: $data['message'] ?? null,
            resolutionStatus: $data['resolution_status'] ?? null,
            warningId: $data['warning_id'] ?? null
        );
    }
}

readonly class Limit
{
    public function __construct(
        public float $perTransaction,
        public float $daily,
        public float $monthly
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            perTransaction: (float) $data['per_transaction'],
            daily: (float) $data['daily'],
            monthly: (float) $data['monthly']
        );
    }
}

abstract readonly class BaseReceiver
{
    public function __construct(
        public string $id,
        public AccountClass $type,
        public KycType $kycType,
        public string $kycStatus,
        public ?array $kycWarnings,
        public string $email,
        public string $taxId,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $city,
        public string $stateProvinceRegion,
        public Country $country,
        public string $postalCode,
        public ?string $ipAddress,
        public ?string $imageUrl,
        public ?string $phoneNumber,
        public ProofOfAddressDocType $proofOfAddressDocType,
        public string $proofOfAddressDocFile,
        public string $instanceId,
        public ?string $tosId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public Limit $limit
    ) {}
}

readonly class IndividualWithStandardKYC extends BaseReceiver
{
    public function __construct(
        string $id,
        string $kycStatus,
        ?array $kycWarnings,
        string $email,
        string $taxId,
        string $addressLine1,
        ?string $addressLine2,
        string $city,
        string $stateProvinceRegion,
        Country $country,
        string $postalCode,
        ?string $ipAddress,
        ?string $imageUrl,
        ?string $phoneNumber,
        ProofOfAddressDocType $proofOfAddressDocType,
        string $proofOfAddressDocFile,
        string $instanceId,
        ?string $tosId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        Limit $limit,
        public string $firstName,
        public string $lastName,
        public string $dateOfBirth,
        public Country $idDocCountry,
        public IdentificationDocument $idDocType,
        public string $idDocFrontFile,
        public string $idDocBackFile,
        public string $aipriseValidationKey
    ) {
        parent::__construct(
            $id,
            AccountClass::INDIVIDUAL,
            KycType::STANDARD,
            $kycStatus,
            $kycWarnings,
            $email,
            $taxId,
            $addressLine1,
            $addressLine2,
            $city,
            $stateProvinceRegion,
            $country,
            $postalCode,
            $ipAddress,
            $imageUrl,
            $phoneNumber,
            $proofOfAddressDocType,
            $proofOfAddressDocFile,
            $instanceId,
            $tosId,
            $createdAt,
            $updatedAt,
            $limit
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            kycStatus: $data['kyc_status'],
            kycWarnings: isset($data['kyc_warnings']) ? array_map(
                fn ($w) => KycWarning::fromArray($w),
                $data['kyc_warnings']
            ) : null,
            email: $data['email'],
            taxId: $data['tax_id'],
            addressLine1: $data['address_line_1'],
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'],
            stateProvinceRegion: $data['state_province_region'],
            country: Country::from($data['country']),
            postalCode: $data['postal_code'],
            ipAddress: $data['ip_address'] ?? null,
            imageUrl: $data['image_url'] ?? null,
            phoneNumber: $data['phone_number'] ?? null,
            proofOfAddressDocType: ProofOfAddressDocType::from($data['proof_of_address_doc_type']),
            proofOfAddressDocFile: $data['proof_of_address_doc_file'],
            instanceId: $data['instance_id'],
            tosId: $data['tos_id'] ?? null,
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
            limit: Limit::fromArray($data['limit']),
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            dateOfBirth: $data['date_of_birth'],
            idDocCountry: Country::from($data['id_doc_country']),
            idDocType: IdentificationDocument::from($data['id_doc_type']),
            idDocFrontFile: $data['id_doc_front_file'],
            idDocBackFile: $data['id_doc_back_file'],
            aipriseValidationKey: $data['aiprise_validation_key']
        );
    }
}

readonly class IndividualWithEnhancedKYC extends BaseReceiver
{
    public function __construct(
        string $id,
        string $kycStatus,
        ?array $kycWarnings,
        string $email,
        string $taxId,
        string $addressLine1,
        ?string $addressLine2,
        string $city,
        string $stateProvinceRegion,
        Country $country,
        string $postalCode,
        ?string $ipAddress,
        ?string $imageUrl,
        ?string $phoneNumber,
        ProofOfAddressDocType $proofOfAddressDocType,
        string $proofOfAddressDocFile,
        string $instanceId,
        ?string $tosId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        Limit $limit,
        public string $firstName,
        public string $lastName,
        public string $dateOfBirth,
        public Country $idDocCountry,
        public IdentificationDocument $idDocType,
        public string $idDocFrontFile,
        public ?string $idDocBackFile,
        public string $aipriseValidationKey,
        public string $sourceOfFundsDocType,
        public string $sourceOfFundsDocFile,
        public string $individualHoldingDocFrontFile,
        public PurposeOfTransactions $purposeOfTransactions,
        public ?string $purposeOfTransactionsExplanation
    ) {
        parent::__construct(
            $id,
            AccountClass::INDIVIDUAL,
            KycType::ENHANCED,
            $kycStatus,
            $kycWarnings,
            $email,
            $taxId,
            $addressLine1,
            $addressLine2,
            $city,
            $stateProvinceRegion,
            $country,
            $postalCode,
            $ipAddress,
            $imageUrl,
            $phoneNumber,
            $proofOfAddressDocType,
            $proofOfAddressDocFile,
            $instanceId,
            $tosId,
            $createdAt,
            $updatedAt,
            $limit
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            kycStatus: $data['kyc_status'],
            kycWarnings: isset($data['kyc_warnings']) ? array_map(
                fn ($w) => KycWarning::fromArray($w),
                $data['kyc_warnings']
            ) : null,
            email: $data['email'],
            taxId: $data['tax_id'],
            addressLine1: $data['address_line_1'],
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'],
            stateProvinceRegion: $data['state_province_region'],
            country: Country::from($data['country']),
            postalCode: $data['postal_code'],
            ipAddress: $data['ip_address'] ?? null,
            imageUrl: $data['image_url'] ?? null,
            phoneNumber: $data['phone_number'] ?? null,
            proofOfAddressDocType: ProofOfAddressDocType::from($data['proof_of_address_doc_type']),
            proofOfAddressDocFile: $data['proof_of_address_doc_file'],
            instanceId: $data['instance_id'],
            tosId: $data['tos_id'] ?? null,
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
            limit: Limit::fromArray($data['limit']),
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            dateOfBirth: $data['date_of_birth'],
            idDocCountry: Country::from($data['id_doc_country']),
            idDocType: IdentificationDocument::from($data['id_doc_type']),
            idDocFrontFile: $data['id_doc_front_file'],
            idDocBackFile: $data['id_doc_back_file'] ?? null,
            aipriseValidationKey: $data['aiprise_validation_key'],
            sourceOfFundsDocType: $data['source_of_funds_doc_type'],
            sourceOfFundsDocFile: $data['source_of_funds_doc_file'],
            individualHoldingDocFrontFile: $data['individual_holding_doc_front_file'],
            purposeOfTransactions: PurposeOfTransactions::from($data['purpose_of_transactions']),
            purposeOfTransactionsExplanation: $data['purpose_of_transactions_explanation'] ?? null
        );
    }
}

readonly class BusinessWithStandardKYB extends BaseReceiver
{
    public function __construct(
        string $id,
        string $kycStatus,
        ?array $kycWarnings,
        string $email,
        string $taxId,
        string $addressLine1,
        ?string $addressLine2,
        string $city,
        string $stateProvinceRegion,
        Country $country,
        string $postalCode,
        ?string $ipAddress,
        ?string $imageUrl,
        ?string $phoneNumber,
        ProofOfAddressDocType $proofOfAddressDocType,
        string $proofOfAddressDocFile,
        string $instanceId,
        ?string $tosId,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        Limit $limit,
        public string $legalName,
        public ?string $alternateName,
        public string $formationDate,
        public ?string $website,
        public array $owners,
        public string $incorporationDocFile,
        public string $proofOfOwnershipDocFile,
        public ?string $externalId,
        public string $aipriseValidationKey
    ) {
        parent::__construct(
            $id,
            AccountClass::BUSINESS,
            KycType::STANDARD,
            $kycStatus,
            $kycWarnings,
            $email,
            $taxId,
            $addressLine1,
            $addressLine2,
            $city,
            $stateProvinceRegion,
            $country,
            $postalCode,
            $ipAddress,
            $imageUrl,
            $phoneNumber,
            $proofOfAddressDocType,
            $proofOfAddressDocFile,
            $instanceId,
            $tosId,
            $createdAt,
            $updatedAt,
            $limit
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            kycStatus: $data['kyc_status'],
            kycWarnings: isset($data['kyc_warnings']) ? array_map(
                fn ($w) => KycWarning::fromArray($w),
                $data['kyc_warnings']
            ) : null,
            email: $data['email'],
            taxId: $data['tax_id'],
            addressLine1: $data['address_line_1'],
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'],
            stateProvinceRegion: $data['state_province_region'],
            country: Country::from($data['country']),
            postalCode: $data['postal_code'],
            ipAddress: $data['ip_address'] ?? null,
            imageUrl: $data['image_url'] ?? null,
            phoneNumber: $data['phone_number'] ?? null,
            proofOfAddressDocType: ProofOfAddressDocType::from($data['proof_of_address_doc_type']),
            proofOfAddressDocFile: $data['proof_of_address_doc_file'],
            instanceId: $data['instance_id'],
            tosId: $data['tos_id'] ?? null,
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
            limit: Limit::fromArray($data['limit']),
            legalName: $data['legal_name'],
            alternateName: $data['alternate_name'] ?? null,
            formationDate: $data['formation_date'],
            website: $data['website'] ?? null,
            owners: array_map(fn ($o) => Owner::fromArray($o), $data['owners']),
            incorporationDocFile: $data['incorporation_doc_file'],
            proofOfOwnershipDocFile: $data['proof_of_ownership_doc_file'],
            externalId: $data['external_id'] ?? null,
            aipriseValidationKey: $data['aiprise_validation_key']
        );
    }
}

readonly class CreateIndividualWithStandardKYCInput
{
    public function __construct(
        public string $addressLine1,
        public string $city,
        public Country $country,
        public string $dateOfBirth,
        public string $email,
        public string $firstName,
        public ?string $phoneNumber,
        public Country $idDocCountry,
        public string $idDocFrontFile,
        public IdentificationDocument $idDocType,
        public ?string $idDocBackFile,
        public string $lastName,
        public string $postalCode,
        public string $proofOfAddressDocFile,
        public ProofOfAddressDocType $proofOfAddressDocType,
        public string $stateProvinceRegion,
        public string $taxId,
        public string $tosId,
        public ?string $addressLine2 = null,
        public ?string $externalId = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'kyc_type' => 'standard',
            'type' => 'individual',
            'address_line_1' => $this->addressLine1,
            'city' => $this->city,
            'country' => $this->country->value,
            'date_of_birth' => $this->dateOfBirth,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'phone_number' => $this->phoneNumber,
            'id_doc_country' => $this->idDocCountry->value,
            'id_doc_front_file' => $this->idDocFrontFile,
            'id_doc_type' => $this->idDocType->value,
            'id_doc_back_file' => $this->idDocBackFile,
            'last_name' => $this->lastName,
            'postal_code' => $this->postalCode,
            'proof_of_address_doc_file' => $this->proofOfAddressDocFile,
            'proof_of_address_doc_type' => $this->proofOfAddressDocType->value,
            'state_province_region' => $this->stateProvinceRegion,
            'tax_id' => $this->taxId,
            'tos_id' => $this->tosId,
        ];

        if ($this->addressLine2 !== null) {
            $data['address_line_2'] = $this->addressLine2;
        }

        if ($this->externalId !== null) {
            $data['external_id'] = $this->externalId;
        }

        return $data;
    }
}

readonly class CreateIndividualWithEnhancedKYCInput
{
    public function __construct(
        public string $addressLine1,
        public string $city,
        public Country $country,
        public string $dateOfBirth,
        public string $email,
        public string $firstName,
        public Country $idDocCountry,
        public string $idDocFrontFile,
        public IdentificationDocument $idDocType,
        public ?string $idDocBackFile,
        public string $individualHoldingDocFrontFile,
        public string $lastName,
        public string $postalCode,
        public ?string $phoneNumber,
        public string $proofOfAddressDocFile,
        public ProofOfAddressDocType $proofOfAddressDocType,
        public PurposeOfTransactions $purposeOfTransactions,
        public string $sourceOfFundsDocFile,
        public SourceOfFundsDocType $sourceOfFundsDocType,
        public ?string $purposeOfTransactionsExplanation,
        public string $stateProvinceRegion,
        public string $taxId,
        public string $tosId,
        public ?string $addressLine2 = null,
        public ?string $externalId = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'kyc_type' => 'enhanced',
            'type' => 'individual',
            'address_line_1' => $this->addressLine1,
            'city' => $this->city,
            'country' => $this->country->value,
            'date_of_birth' => $this->dateOfBirth,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'id_doc_country' => $this->idDocCountry->value,
            'id_doc_front_file' => $this->idDocFrontFile,
            'id_doc_type' => $this->idDocType->value,
            'id_doc_back_file' => $this->idDocBackFile,
            'individual_holding_doc_front_file' => $this->individualHoldingDocFrontFile,
            'last_name' => $this->lastName,
            'postal_code' => $this->postalCode,
            'phone_number' => $this->phoneNumber,
            'proof_of_address_doc_file' => $this->proofOfAddressDocFile,
            'proof_of_address_doc_type' => $this->proofOfAddressDocType->value,
            'purpose_of_transactions' => $this->purposeOfTransactions->value,
            'source_of_funds_doc_file' => $this->sourceOfFundsDocFile,
            'source_of_funds_doc_type' => $this->sourceOfFundsDocType->value,
            'purpose_of_transactions_explanation' => $this->purposeOfTransactionsExplanation,
            'state_province_region' => $this->stateProvinceRegion,
            'tax_id' => $this->taxId,
            'tos_id' => $this->tosId,
        ];

        if ($this->addressLine2 !== null) {
            $data['address_line_2'] = $this->addressLine2;
        }

        if ($this->externalId !== null) {
            $data['external_id'] = $this->externalId;
        }

        return $data;
    }
}

readonly class CreateBusinessWithStandardKYBInput
{
    public function __construct(
        public string $addressLine1,
        public string $city,
        public Country $country,
        public string $email,
        public string $formationDate,
        public string $incorporationDocFile,
        public string $legalName,
        public array $owners,
        public string $postalCode,
        public string $proofOfAddressDocFile,
        public ProofOfAddressDocType $proofOfAddressDocType,
        public string $proofOfOwnershipDocFile,
        public string $stateProvinceRegion,
        public string $taxId,
        public string $tosId,
        public ?string $website,
        public ?string $addressLine2 = null,
        public ?string $alternateName = null,
        public ?string $externalId = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'kyc_type' => 'standard',
            'type' => 'business',
            'address_line_1' => $this->addressLine1,
            'city' => $this->city,
            'country' => $this->country->value,
            'email' => $this->email,
            'formation_date' => $this->formationDate,
            'incorporation_doc_file' => $this->incorporationDocFile,
            'legal_name' => $this->legalName,
            'owners' => array_map(fn ($o) => $o->toArray(), $this->owners),
            'postal_code' => $this->postalCode,
            'proof_of_address_doc_file' => $this->proofOfAddressDocFile,
            'proof_of_address_doc_type' => $this->proofOfAddressDocType->value,
            'proof_of_ownership_doc_file' => $this->proofOfOwnershipDocFile,
            'state_province_region' => $this->stateProvinceRegion,
            'tax_id' => $this->taxId,
            'tos_id' => $this->tosId,
            'website' => $this->website,
        ];

        if ($this->addressLine2 !== null) {
            $data['address_line_2'] = $this->addressLine2;
        }

        if ($this->alternateName !== null) {
            $data['alternate_name'] = $this->alternateName;
        }

        if ($this->externalId !== null) {
            $data['external_id'] = $this->externalId;
        }

        return $data;
    }
}

readonly class CreateReceiverResponse
{
    public function __construct(
        public string $id
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id']
        );
    }
}

readonly class UpdateReceiverInput
{
    public function __construct(
        public string $receiverId,
        public ?string $email = null,
        public ?string $taxId = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $city = null,
        public ?string $stateProvinceRegion = null,
        public ?Country $country = null,
        public ?string $postalCode = null,
        public ?string $ipAddress = null,
        public ?string $imageUrl = null,
        public ?string $phoneNumber = null,
        public ?ProofOfAddressDocType $proofOfAddressDocType = null,
        public ?string $proofOfAddressDocFile = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $dateOfBirth = null,
        public ?Country $idDocCountry = null,
        public ?IdentificationDocument $idDocType = null,
        public ?string $idDocFrontFile = null,
        public ?string $idDocBackFile = null,
        public ?string $legalName = null,
        public ?string $alternateName = null,
        public ?string $formationDate = null,
        public ?string $website = null,
        public ?array $owners = null,
        public ?string $incorporationDocFile = null,
        public ?string $proofOfOwnershipDocFile = null,
        public ?SourceOfFundsDocType $sourceOfFundsDocType = null,
        public ?string $sourceOfFundsDocFile = null,
        public ?string $individualHoldingDocFrontFile = null,
        public ?PurposeOfTransactions $purposeOfTransactions = null,
        public ?string $purposeOfTransactionsExplanation = null,
        public ?string $externalId = null,
        public ?string $tosId = null
    ) {}

    public function toArray(): array
    {
        $data = [];

        if ($this->email !== null) {
            $data['email'] = $this->email;
        }
        if ($this->taxId !== null) {
            $data['tax_id'] = $this->taxId;
        }
        if ($this->addressLine1 !== null) {
            $data['address_line_1'] = $this->addressLine1;
        }
        if ($this->addressLine2 !== null) {
            $data['address_line_2'] = $this->addressLine2;
        }
        if ($this->city !== null) {
            $data['city'] = $this->city;
        }
        if ($this->stateProvinceRegion !== null) {
            $data['state_province_region'] = $this->stateProvinceRegion;
        }
        if ($this->country !== null) {
            $data['country'] = $this->country->value;
        }
        if ($this->postalCode !== null) {
            $data['postal_code'] = $this->postalCode;
        }
        if ($this->ipAddress !== null) {
            $data['ip_address'] = $this->ipAddress;
        }
        if ($this->imageUrl !== null) {
            $data['image_url'] = $this->imageUrl;
        }
        if ($this->phoneNumber !== null) {
            $data['phone_number'] = $this->phoneNumber;
        }
        if ($this->proofOfAddressDocType !== null) {
            $data['proof_of_address_doc_type'] = $this->proofOfAddressDocType->value;
        }
        if ($this->proofOfAddressDocFile !== null) {
            $data['proof_of_address_doc_file'] = $this->proofOfAddressDocFile;
        }
        if ($this->firstName !== null) {
            $data['first_name'] = $this->firstName;
        }
        if ($this->lastName !== null) {
            $data['last_name'] = $this->lastName;
        }
        if ($this->dateOfBirth !== null) {
            $data['date_of_birth'] = $this->dateOfBirth;
        }
        if ($this->idDocCountry !== null) {
            $data['id_doc_country'] = $this->idDocCountry->value;
        }
        if ($this->idDocType !== null) {
            $data['id_doc_type'] = $this->idDocType->value;
        }
        if ($this->idDocFrontFile !== null) {
            $data['id_doc_front_file'] = $this->idDocFrontFile;
        }
        if ($this->idDocBackFile !== null) {
            $data['id_doc_back_file'] = $this->idDocBackFile;
        }
        if ($this->legalName !== null) {
            $data['legal_name'] = $this->legalName;
        }
        if ($this->alternateName !== null) {
            $data['alternate_name'] = $this->alternateName;
        }
        if ($this->formationDate !== null) {
            $data['formation_date'] = $this->formationDate;
        }
        if ($this->website !== null) {
            $data['website'] = $this->website;
        }
        if ($this->owners !== null) {
            $data['owners'] = $this->owners;
        }
        if ($this->incorporationDocFile !== null) {
            $data['incorporation_doc_file'] = $this->incorporationDocFile;
        }
        if ($this->proofOfOwnershipDocFile !== null) {
            $data['proof_of_ownership_doc_file'] = $this->proofOfOwnershipDocFile;
        }
        if ($this->sourceOfFundsDocType !== null) {
            $data['source_of_funds_doc_type'] = $this->sourceOfFundsDocType->value;
        }
        if ($this->sourceOfFundsDocFile !== null) {
            $data['source_of_funds_doc_file'] = $this->sourceOfFundsDocFile;
        }
        if ($this->individualHoldingDocFrontFile !== null) {
            $data['individual_holding_doc_front_file'] = $this->individualHoldingDocFrontFile;
        }
        if ($this->purposeOfTransactions !== null) {
            $data['purpose_of_transactions'] = $this->purposeOfTransactions->value;
        }
        if ($this->purposeOfTransactionsExplanation !== null) {
            $data['purpose_of_transactions_explanation'] = $this->purposeOfTransactionsExplanation;
        }
        if ($this->externalId !== null) {
            $data['external_id'] = $this->externalId;
        }
        if ($this->tosId !== null) {
            $data['tos_id'] = $this->tosId;
        }

        return $data;
    }
}

/*
 * Get receiver limits response
 */
readonly class GetReceiverLimitsResponse
{
    public function __construct(
        public array $limits
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            limits: $data['limits']
        );
    }
}

readonly class LimitIncreaseRequest
{
    public function __construct(
        public string $id,
        public string $receiverId,
        public LimitIncreaseRequestStatus $status,
        public float $daily,
        public float $monthly,
        public float $perTransaction,
        public string $supportingDocumentFile,
        public LimitIncreaseRequestSupportingDocumentType $supportingDocumentType,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            receiverId: $data['receiver_id'],
            status: LimitIncreaseRequestStatus::from($data['status']),
            daily: (float) $data['daily'],
            monthly: (float) $data['monthly'],
            perTransaction: (float) $data['per_transaction'],
            supportingDocumentFile: $data['supporting_document_file'],
            supportingDocumentType: LimitIncreaseRequestSupportingDocumentType::from($data['supporting_document_type']),
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at'])
        );
    }
}

readonly class RequestLimitIncreaseInput
{
    public function __construct(
        public string $receiverId,
        public float $daily,
        public float $monthly,
        public float $perTransaction,
        public string $supportingDocumentFile,
        public LimitIncreaseRequestSupportingDocumentType $supportingDocumentType
    ) {}

    public function toArray(): array
    {
        return [
            'daily' => $this->daily,
            'monthly' => $this->monthly,
            'per_transaction' => $this->perTransaction,
            'supporting_document_file' => $this->supportingDocumentFile,
            'supporting_document_type' => $this->supportingDocumentType->value,
        ];
    }
}

readonly class RequestLimitIncreaseResponse
{
    public function __construct(
        public string $id
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id']
        );
    }
}

class Receivers
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * List all receivers
     *
     * @return BlindPayApiResponse<array<IndividualWithStandardKYC|IndividualWithEnhancedKYC|BusinessWithStandardKYB>>
     */
    public function list(): BlindPayApiResponse
    {
        $response = $this->client->get("/instances/{$this->instanceId}/receivers");

        if ($response->isSuccess() && is_array($response->data)) {
            $receivers = array_map(function (array $item) {
                $type = $item['type'] ?? null;
                $kycType = $item['kyc_type'] ?? null;

                if ($type === 'individual' && $kycType === 'standard') {
                    return IndividualWithStandardKYC::fromArray($item);
                } elseif ($type === 'individual' && $kycType === 'enhanced') {
                    return IndividualWithEnhancedKYC::fromArray($item);
                } elseif ($type === 'business' && $kycType === 'standard') {
                    return BusinessWithStandardKYB::fromArray($item);
                }

                throw new \InvalidArgumentException("Unknown receiver type: {$type}/{$kycType}");
            }, $response->data);

            return BlindPayApiResponse::success($receivers);
        }

        return $response;
    }

    /*
     * Create individual with standard KYC
     *
     * @param CreateIndividualWithStandardKYCInput $data
     * @return BlindPayApiResponse<CreateReceiverResponse>
     */
    public function createIndividualWithStandardKYC(CreateIndividualWithStandardKYCInput $data): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/receivers",
            $data->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateReceiverResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create individual with enhanced KYC
     *
     * @param CreateIndividualWithEnhancedKYCInput $data
     * @return BlindPayApiResponse<CreateReceiverResponse>
     */
    public function createIndividualWithEnhancedKYC(CreateIndividualWithEnhancedKYCInput $data): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/receivers",
            $data->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateReceiverResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create business with standard KYB
     *
     * @param CreateBusinessWithStandardKYBInput $data
     * @return BlindPayApiResponse<CreateReceiverResponse>
     */
    public function createBusinessWithStandardKYB(CreateBusinessWithStandardKYBInput $data): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/receivers",
            $data->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateReceiverResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get a receiver by ID
     *
     * @param string $receiverId
     * @return BlindPayApiResponse<IndividualWithStandardKYC|IndividualWithEnhancedKYC|BusinessWithStandardKYB>
     */
    public function get(string $receiverId): BlindPayApiResponse
    {
        if (empty($receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        $response = $this->client->get("/instances/{$this->instanceId}/receivers/{$receiverId}");

        if ($response->isSuccess() && is_array($response->data)) {
            $type = $response->data['type'] ?? null;
            $kycType = $response->data['kyc_type'] ?? null;

            if ($type === 'individual' && $kycType === 'standard') {
                return BlindPayApiResponse::success(
                    IndividualWithStandardKYC::fromArray($response->data)
                );
            } elseif ($type === 'individual' && $kycType === 'enhanced') {
                return BlindPayApiResponse::success(
                    IndividualWithEnhancedKYC::fromArray($response->data)
                );
            } elseif ($type === 'business' && $kycType === 'standard') {
                return BlindPayApiResponse::success(
                    BusinessWithStandardKYB::fromArray($response->data)
                );
            }

            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse("Unknown receiver type: {$type}/{$kycType}")
            );
        }

        return $response;
    }

    /*
     * Update a receiver
     *
     * @param UpdateReceiverInput $input
     * @return BlindPayApiResponse<null>
     */
    public function update(UpdateReceiverInput $input): BlindPayApiResponse
    {
        if (empty($input->receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        return $this->client->patch(
            "/instances/{$this->instanceId}/receivers/{$input->receiverId}",
            $input->toArray()
        );
    }

    /*
     * Delete a receiver
     *
     * @param string $receiverId
     * @return BlindPayApiResponse<null>
     */
    public function delete(string $receiverId): BlindPayApiResponse
    {
        if (empty($receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        return $this->client->delete("/instances/{$this->instanceId}/receivers/{$receiverId}");
    }

    /*
     * Get receiver limits
     *
     * @param string $receiverId
     * @return BlindPayApiResponse<GetReceiverLimitsResponse>
     */
    public function getLimits(string $receiverId): BlindPayApiResponse
    {
        if (empty($receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        $response = $this->client->get("/instances/{$this->instanceId}/limits/receivers/{$receiverId}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                GetReceiverLimitsResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get limit increase requests
     *
     * @param string $receiverId
     * @return BlindPayApiResponse<LimitIncreaseRequest[]>
     */
    public function getLimitIncreaseRequests(string $receiverId): BlindPayApiResponse
    {
        if (empty($receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        $response = $this->client->get("/instances/{$this->instanceId}/receivers/{$receiverId}/limit-increase");

        if ($response->isSuccess() && is_array($response->data)) {
            $requests = array_map(
                fn (array $item) => LimitIncreaseRequest::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($requests);
        }

        return $response;
    }

    /*
     * Request limit increase
     *
     * @param RequestLimitIncreaseInput $input
     * @return BlindPayApiResponse<RequestLimitIncreaseResponse>
     */
    public function requestLimitIncrease(RequestLimitIncreaseInput $input): BlindPayApiResponse
    {
        if (empty($input->receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        $response = $this->client->post(
            "/instances/{$this->instanceId}/receivers/{$input->receiverId}/limit-increase",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                RequestLimitIncreaseResponse::fromArray($response->data)
            );
        }

        return $response;
    }
}
