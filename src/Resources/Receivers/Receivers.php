<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Receivers;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\AccountClass;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Country;
use BlindPay\SDK\Types\PaginationMetadata;
use BlindPay\SDK\Types\PaginationParams;
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

enum KycStatus: string
{
    case VERIFYING = 'verifying';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case DEPRECATED = 'deprecated';
    case PENDING_REVIEW = 'pending_review';
}

enum OwnerRole: string
{
    case BENEFICIAL_CONTROLLING = 'beneficial_controlling';
    case BENEFICIAL_OWNER = 'beneficial_owner';
    case CONTROLLING_PERSON = 'controlling_person';
}

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

enum AccountPurpose: string
{
    case CHARITABLE_DONATIONS = 'charitable_donations';
    case ECOMMERCE_RETAIL_PAYMENTS = 'ecommerce_retail_payments';
    case INVESTMENT_PURPOSES = 'investment_purposes';
    case BUSINESS_EXPENSES = 'business_expenses';
    case PAYMENTS_TO_FRIENDS_OR_FAMILY_ABROAD = 'payments_to_friends_or_family_abroad';
    case PERSONAL_OR_LIVING_EXPENSES = 'personal_or_living_expenses';
    case PROTECT_WEALTH = 'protect_wealth';
    case PURCHASE_GOODS_AND_SERVICES = 'purchase_goods_and_services';
    case RECEIVE_PAYMENTS_FOR_GOODS_AND_SERVICES = 'receive_payments_for_goods_and_services';
    case TAX_OPTIMIZATION = 'tax_optimization';
    case THIRD_PARTY_MONEY_TRANSMISSION = 'third_party_money_transmission';
    case PAYROLL = 'payroll';
    case TREASURY_MANAGEMENT = 'treasury_management';
    case OTHER = 'other';
}

enum BusinessType: string
{
    case CORPORATION = 'corporation';
    case LLC = 'llc';
    case PARTNERSHIP = 'partnership';
    case SOLE_PROPRIETORSHIP = 'sole_proprietorship';
    case TRUST = 'trust';
    case NON_PROFIT = 'non_profit';
}

enum BusinessIndustry: string
{
    case N111998 = '111998';
    case N112120 = '112120';
    case N113310 = '113310';
    case N115114 = '115114';
    case N541211 = '541211';
    case N541810 = '541810';
    case N541430 = '541430';
    case N541715 = '541715';
    case N541930 = '541930';
    case N561422 = '561422';
    case N561311 = '561311';
    case N561612 = '561612';
    case N561740 = '561740';
    case N561730 = '561730';
    case N236115 = '236115';
    case N236220 = '236220';
    case N237310 = '237310';
    case N238210 = '238210';
    case N811111 = '811111';
    case N812111 = '812111';
    case N812112 = '812112';
    case N532111 = '532111';
    case N624410 = '624410';
    case N541922 = '541922';
    case N811210 = '811210';
    case N812199 = '812199';
    case N611110 = '611110';
    case N611310 = '611310';
    case N611410 = '611410';
    case N611710 = '611710';
    case N211120 = '211120';
    case N212114 = '212114';
    case N221310 = '221310';
    case N562111 = '562111';
    case N562920 = '562920';
    case N213112 = '213112';
    case N522110 = '522110';
    case N522210 = '522210';
    case N522320 = '522320';
    case N523150 = '523150';
    case N523940 = '523940';
    case N523999 = '523999';
    case N524113 = '524113';
    case N813110 = '813110';
    case N813211 = '813211';
    case N813219 = '813219';
    case N551112 = '551112';
    case N721110 = '721110';
    case N722511 = '722511';
    case N722513 = '722513';
    case N561510 = '561510';
    case N713110 = '713110';
    case N713210 = '713210';
    case N712110 = '712110';
    case N711110 = '711110';
    case N711211 = '711211';
    case N621111 = '621111';
    case N621210 = '621210';
    case N622110 = '622110';
    case N623110 = '623110';
    case N621511 = '621511';
    case N623220 = '623220';
    case N541940 = '541940';
    case N621399 = '621399';
    case N621910 = '621910';
    case N541110 = '541110';
    case N311421 = '311421';
    case N337121 = '337121';
    case N322220 = '322220';
    case N339920 = '339920';
    case N334210 = '334210';
    case N339930 = '339930';
    case N312130 = '312130';
    case N334111 = '334111';
    case N334118 = '334118';
    case N325412 = '325412';
    case N339112 = '339112';
    case N336110 = '336110';
    case N336390 = '336390';
    case N315990 = '315990';
    case N313110 = '313110';
    case N339910 = '339910';
    case N516120 = '516120';
    case N513130 = '513130';
    case N512250 = '512250';
    case N519130 = '519130';
    case N711410 = '711410';
    case N711510 = '711510';
    case N531110 = '531110';
    case N531120 = '531120';
    case N531130 = '531130';
    case N531190 = '531190';
    case N531210 = '531210';
    case N531311 = '531311';
    case N531312 = '531312';
    case N531320 = '531320';
    case N531390 = '531390';
    case N454110 = '454110';
    case N445110 = '445110';
    case N455110 = '455110';
    case N457110 = '457110';
    case N449210 = '449210';
    case N444110 = '444110';
    case N459210 = '459210';
    case N459120 = '459120';
    case N445320 = '445320';
    case N458110 = '458110';
    case N458210 = '458210';
    case N458310 = '458310';
    case N455219 = '455219';
    case N424210 = '424210';
    case N456110 = '456110';
    case N541511 = '541511';
    case N541512 = '541512';
    case N541519 = '541519';
    case N518210 = '518210';
    case N511210 = '511210';
    case N517111 = '517111';
    case N517112 = '517112';
    case N517410 = '517410';
    case N481111 = '481111';
    case N483111 = '483111';
    case N485210 = '485210';
    case N488510 = '488510';
    case N484121 = '484121';
    case N493110 = '493110';
    case N423430 = '423430';
    case N423690 = '423690';
    case N423110 = '423110';
    case N423830 = '423830';
    case N423840 = '423840';
    case N423510 = '423510';
    case N424690 = '424690';
    case N424990 = '424990';
    case N424410 = '424410';
    case N424480 = '424480';
    case N423940 = '423940';
    case N541611 = '541611';
    case N541618 = '541618';
    case N541330 = '541330';
    case N541990 = '541990';
    case N541214 = '541214';
    case N561499 = '561499';
}

enum EstimatedAnnualRevenue: string
{
    case RANGE_0_TO_99999 = '0_99999';
    case RANGE_100000_TO_999999 = '100000_999999';
    case RANGE_1000000_TO_9999999 = '1000000_9999999';
    case RANGE_10000000_TO_49999999 = '10000000_49999999';
    case RANGE_50000000_TO_249999999 = '50000000_249999999';
    case RANGE_2500000000_PLUS = '2500000000_plus';
}

enum SourceOfWealth: string
{
    case BUSINESS_DIVIDENDS_OR_PROFITS = 'business_dividends_or_profits';
    case INVESTMENTS = 'investments';
    case ASSET_SALES = 'asset_sales';
    case CLIENT_INVESTOR_CONTRIBUTIONS = 'client_investor_contributions';
    case GAMBLING = 'gambling';
    case CHARITABLE_CONTRIBUTIONS = 'charitable_contributions';
    case INHERITANCE = 'inheritance';
    case AFFILIATE_OR_ROYALTY_INCOME = 'affiliate_or_royalty_income';
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

readonly class FraudWarning
{
    public function __construct(
        public ?string $id,
        public ?string $name,
        public ?string $operation,
        public ?float $score
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            operation: $data['operation'] ?? null,
            score: isset($data['score']) ? (float) $data['score'] : null
        );
    }
}

readonly class AmlHits
{
    public function __construct(
        public bool $hasSanctionMatch = false,
        public bool $hasPepMatch = false,
        public bool $hasWatchlistMatch = false,
        public bool $hasCrimelistMatch = false,
        public bool $hasAdversemediaMatch = false
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            hasSanctionMatch: $data['has_sanction_match'] ?? false,
            hasPepMatch: $data['has_pep_match'] ?? false,
            hasWatchlistMatch: $data['has_watchlist_match'] ?? false,
            hasCrimelistMatch: $data['has_crimelist_match'] ?? false,
            hasAdversemediaMatch: $data['has_adversemedia_match'] ?? false
        );
    }
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

readonly class Limit
{
    public function __construct(
        public ?float $perTransaction,
        public ?float $daily,
        public ?float $monthly
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            perTransaction: $data['per_transaction'] !== null ? (float) $data['per_transaction'] : null,
            daily: $data['daily'] !== null ? (float) $data['daily'] : null,
            monthly: $data['monthly'] !== null ? (float) $data['monthly'] : null
        );
    }
}

readonly class ReceiverOut
{
    /**
     * @param  KycWarning[]|null  $kycWarnings
     * @param  FraudWarning[]|null  $fraudWarnings
     * @param  Owner[]|null  $owners
     */
    public function __construct(
        public string $id,
        public AccountClass $type,
        public KycType $kycType,
        public KycStatus $kycStatus,
        public string $email,
        public Country $country,
        public string $instanceId,
        public Limit $limit,
        public ?array $kycWarnings = null,
        public ?array $fraudWarnings = null,
        public ?string $taxId = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $city = null,
        public ?string $stateProvinceRegion = null,
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
        public ?string $selfieFile = null,
        public ?PurposeOfTransactions $purposeOfTransactions = null,
        public ?string $purposeOfTransactionsExplanation = null,
        public ?bool $isFbo = null,
        public ?AccountPurpose $accountPurpose = null,
        public ?string $accountPurposeOther = null,
        public ?BusinessType $businessType = null,
        public ?string $businessDescription = null,
        public ?BusinessIndustry $businessIndustry = null,
        public ?EstimatedAnnualRevenue $estimatedAnnualRevenue = null,
        public ?SourceOfWealth $sourceOfWealth = null,
        public ?bool $publiclyTraded = null,
        public ?string $occupation = null,
        public ?string $externalId = null,
        public ?string $tosId = null,
        public ?string $amlStatus = null,
        public ?AmlHits $amlHits = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?bool $isTosAccepted = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: AccountClass::from($data['type']),
            kycType: KycType::from($data['kyc_type']),
            kycStatus: KycStatus::from($data['kyc_status']),
            email: $data['email'],
            country: Country::from($data['country']),
            instanceId: $data['instance_id'],
            limit: Limit::fromArray($data['limit']),
            kycWarnings: isset($data['kyc_warnings']) ? array_map(
                fn (array $w) => KycWarning::fromArray($w),
                $data['kyc_warnings']
            ) : null,
            fraudWarnings: isset($data['fraud_warnings']) ? array_map(
                fn (array $w) => FraudWarning::fromArray($w),
                $data['fraud_warnings']
            ) : null,
            taxId: $data['tax_id'] ?? null,
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'] ?? null,
            stateProvinceRegion: $data['state_province_region'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            ipAddress: $data['ip_address'] ?? null,
            imageUrl: $data['image_url'] ?? null,
            phoneNumber: $data['phone_number'] ?? null,
            proofOfAddressDocType: isset($data['proof_of_address_doc_type']) ? ProofOfAddressDocType::from($data['proof_of_address_doc_type']) : null,
            proofOfAddressDocFile: $data['proof_of_address_doc_file'] ?? null,
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            dateOfBirth: $data['date_of_birth'] ?? null,
            idDocCountry: isset($data['id_doc_country']) ? Country::from($data['id_doc_country']) : null,
            idDocType: isset($data['id_doc_type']) ? IdentificationDocument::from($data['id_doc_type']) : null,
            idDocFrontFile: $data['id_doc_front_file'] ?? null,
            idDocBackFile: $data['id_doc_back_file'] ?? null,
            legalName: $data['legal_name'] ?? null,
            alternateName: $data['alternate_name'] ?? null,
            formationDate: $data['formation_date'] ?? null,
            website: $data['website'] ?? null,
            owners: $data['owners'] ?? null,
            incorporationDocFile: $data['incorporation_doc_file'] ?? null,
            proofOfOwnershipDocFile: $data['proof_of_ownership_doc_file'] ?? null,
            sourceOfFundsDocType: isset($data['source_of_funds_doc_type']) ? SourceOfFundsDocType::from($data['source_of_funds_doc_type']) : null,
            sourceOfFundsDocFile: $data['source_of_funds_doc_file'] ?? null,
            selfieFile: $data['selfie_file'] ?? null,
            purposeOfTransactions: isset($data['purpose_of_transactions']) ? PurposeOfTransactions::from($data['purpose_of_transactions']) : null,
            purposeOfTransactionsExplanation: $data['purpose_of_transactions_explanation'] ?? null,
            isFbo: $data['is_fbo'] ?? null,
            accountPurpose: isset($data['account_purpose']) ? AccountPurpose::from($data['account_purpose']) : null,
            accountPurposeOther: $data['account_purpose_other'] ?? null,
            businessType: isset($data['business_type']) ? BusinessType::from($data['business_type']) : null,
            businessDescription: $data['business_description'] ?? null,
            businessIndustry: isset($data['business_industry']) ? BusinessIndustry::from($data['business_industry']) : null,
            estimatedAnnualRevenue: isset($data['estimated_annual_revenue']) ? EstimatedAnnualRevenue::from($data['estimated_annual_revenue']) : null,
            sourceOfWealth: isset($data['source_of_wealth']) ? SourceOfWealth::from($data['source_of_wealth']) : null,
            publiclyTraded: $data['publicly_traded'] ?? null,
            occupation: $data['occupation'] ?? null,
            externalId: $data['external_id'] ?? null,
            tosId: $data['tos_id'] ?? null,
            amlStatus: $data['aml_status'] ?? null,
            amlHits: isset($data['aml_hits']) ? AmlHits::fromArray($data['aml_hits']) : null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
            isTosAccepted: $data['is_tos_accepted'] ?? null
        );
    }
}

readonly class ListReceiversInput extends PaginationParams
{
    public function __construct(
        public ?string $fullName = null,
        public ?string $receiverName = null,
        public ?KycStatus $status = null,
        public ?string $receiverId = null,
        public ?string $bankAccountId = null,
        public ?Country $country = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $startingAfter = null,
        ?string $endingBefore = null
    ) {
        parent::__construct($limit, $offset, $startingAfter, $endingBefore);
    }

    public function toArray(): array
    {
        $params = parent::toArray();

        if ($this->fullName !== null) {
            $params['full_name'] = $this->fullName;
        }

        if ($this->receiverName !== null) {
            $params['receiver_name'] = $this->receiverName;
        }

        if ($this->status !== null) {
            $params['status'] = $this->status->value;
        }

        if ($this->receiverId !== null) {
            $params['receiver_id'] = $this->receiverId;
        }

        if ($this->bankAccountId !== null) {
            $params['bank_account_id'] = $this->bankAccountId;
        }

        if ($this->country !== null) {
            $params['country'] = $this->country->value;
        }

        return $params;
    }
}

readonly class ListReceiversResponse
{
    /**
     * @param  ReceiverOut[]  $data
     */
    public function __construct(
        public array $data,
        public PaginationMetadata $pagination
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            data: array_map(
                fn (array $item) => ReceiverOut::fromArray($item),
                $data['data']
            ),
            pagination: PaginationMetadata::fromArray($data['pagination'])
        );
    }
}

readonly class CreateReceiverInput
{
    public function __construct(
        public AccountClass $type,
        public KycType $kycType,
        public string $email,
        public Country $country,
        public ?string $taxId = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $city = null,
        public ?string $stateProvinceRegion = null,
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
        public ?string $selfieFile = null,
        public ?PurposeOfTransactions $purposeOfTransactions = null,
        public ?string $purposeOfTransactionsExplanation = null,
        public ?AccountPurpose $accountPurpose = null,
        public ?string $accountPurposeOther = null,
        public ?BusinessType $businessType = null,
        public ?string $businessDescription = null,
        public ?BusinessIndustry $businessIndustry = null,
        public ?EstimatedAnnualRevenue $estimatedAnnualRevenue = null,
        public ?SourceOfWealth $sourceOfWealth = null,
        public ?bool $publiclyTraded = null,
        public ?string $occupation = null,
        public ?string $externalId = null,
        public ?string $tosId = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'type' => $this->type->value,
            'kyc_type' => $this->kycType->value,
            'email' => $this->email,
            'country' => $this->country->value,
        ];

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

        if ($this->selfieFile !== null) {
            $data['selfie_file'] = $this->selfieFile;
        }

        if ($this->purposeOfTransactions !== null) {
            $data['purpose_of_transactions'] = $this->purposeOfTransactions->value;
        }

        if ($this->purposeOfTransactionsExplanation !== null) {
            $data['purpose_of_transactions_explanation'] = $this->purposeOfTransactionsExplanation;
        }

        if ($this->accountPurpose !== null) {
            $data['account_purpose'] = $this->accountPurpose->value;
        }

        if ($this->accountPurposeOther !== null) {
            $data['account_purpose_other'] = $this->accountPurposeOther;
        }

        if ($this->businessType !== null) {
            $data['business_type'] = $this->businessType->value;
        }

        if ($this->businessDescription !== null) {
            $data['business_description'] = $this->businessDescription;
        }

        if ($this->businessIndustry !== null) {
            $data['business_industry'] = $this->businessIndustry->value;
        }

        if ($this->estimatedAnnualRevenue !== null) {
            $data['estimated_annual_revenue'] = $this->estimatedAnnualRevenue->value;
        }

        if ($this->sourceOfWealth !== null) {
            $data['source_of_wealth'] = $this->sourceOfWealth->value;
        }

        if ($this->publiclyTraded !== null) {
            $data['publicly_traded'] = $this->publiclyTraded;
        }

        if ($this->occupation !== null) {
            $data['occupation'] = $this->occupation;
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
        public string $email,
        public Country $country,
        public ?string $taxId = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $city = null,
        public ?string $stateProvinceRegion = null,
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
        public ?string $selfieFile = null,
        public ?PurposeOfTransactions $purposeOfTransactions = null,
        public ?string $purposeOfTransactionsExplanation = null,
        public ?AccountPurpose $accountPurpose = null,
        public ?string $accountPurposeOther = null,
        public ?BusinessType $businessType = null,
        public ?string $businessDescription = null,
        public ?BusinessIndustry $businessIndustry = null,
        public ?EstimatedAnnualRevenue $estimatedAnnualRevenue = null,
        public ?SourceOfWealth $sourceOfWealth = null,
        public ?bool $publiclyTraded = null,
        public ?string $occupation = null,
        public ?string $externalId = null,
        public ?string $tosId = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'email' => $this->email,
            'country' => $this->country->value,
        ];

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

        if ($this->selfieFile !== null) {
            $data['selfie_file'] = $this->selfieFile;
        }

        if ($this->purposeOfTransactions !== null) {
            $data['purpose_of_transactions'] = $this->purposeOfTransactions->value;
        }

        if ($this->purposeOfTransactionsExplanation !== null) {
            $data['purpose_of_transactions_explanation'] = $this->purposeOfTransactionsExplanation;
        }

        if ($this->accountPurpose !== null) {
            $data['account_purpose'] = $this->accountPurpose->value;
        }

        if ($this->accountPurposeOther !== null) {
            $data['account_purpose_other'] = $this->accountPurposeOther;
        }

        if ($this->businessType !== null) {
            $data['business_type'] = $this->businessType->value;
        }

        if ($this->businessDescription !== null) {
            $data['business_description'] = $this->businessDescription;
        }

        if ($this->businessIndustry !== null) {
            $data['business_industry'] = $this->businessIndustry->value;
        }

        if ($this->estimatedAnnualRevenue !== null) {
            $data['estimated_annual_revenue'] = $this->estimatedAnnualRevenue->value;
        }

        if ($this->sourceOfWealth !== null) {
            $data['source_of_wealth'] = $this->sourceOfWealth->value;
        }

        if ($this->publiclyTraded !== null) {
            $data['publicly_traded'] = $this->publiclyTraded;
        }

        if ($this->occupation !== null) {
            $data['occupation'] = $this->occupation;
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
        public ?int $perTransaction = null,
        public ?int $daily = null,
        public ?int $monthly = null,
        public ?int $approvedPerTransaction = null,
        public ?int $approvedDaily = null,
        public ?int $approvedMonthly = null,
        public ?LimitIncreaseRequestSupportingDocumentType $supportingDocumentType = null,
        public ?string $supportingDocumentFile = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            receiverId: $data['receiver_id'],
            status: LimitIncreaseRequestStatus::from($data['status']),
            perTransaction: isset($data['per_transaction']) ? (int) $data['per_transaction'] : null,
            daily: isset($data['daily']) ? (int) $data['daily'] : null,
            monthly: isset($data['monthly']) ? (int) $data['monthly'] : null,
            approvedPerTransaction: isset($data['approved_per_transaction']) ? (int) $data['approved_per_transaction'] : null,
            approvedDaily: isset($data['approved_daily']) ? (int) $data['approved_daily'] : null,
            approvedMonthly: isset($data['approved_monthly']) ? (int) $data['approved_monthly'] : null,
            supportingDocumentType: isset($data['supporting_document_type']) ? LimitIncreaseRequestSupportingDocumentType::from($data['supporting_document_type']) : null,
            supportingDocumentFile: $data['supporting_document_file'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null
        );
    }
}

readonly class RequestLimitIncreaseInput
{
    public function __construct(
        public string $receiverId,
        public ?int $perTransaction,
        public ?int $daily,
        public ?int $monthly,
        public string $supportingDocumentFile,
        public LimitIncreaseRequestSupportingDocumentType $supportingDocumentType
    ) {}

    public function toArray(): array
    {
        return [
            'per_transaction' => $this->perTransaction,
            'daily' => $this->daily,
            'monthly' => $this->monthly,
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
     * List receivers with optional filters and pagination
     *
     * @param ListReceiversInput|null $params Optional filters and pagination
     * @return BlindPayApiResponse<ListReceiversResponse>
     */
    public function list(?ListReceiversInput $params = null): BlindPayApiResponse
    {
        $queryParams = $params ? $params->toQueryString() : '';
        $response = $this->client->get("instances/{$this->instanceId}/receivers{$queryParams}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                ListReceiversResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create a receiver
     *
     * @param CreateReceiverInput $input
     * @return BlindPayApiResponse<CreateReceiverResponse>
     */
    public function create(CreateReceiverInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "instances/{$this->instanceId}/receivers",
            $input->toArray()
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
     * @return BlindPayApiResponse<ReceiverOut>
     */
    public function get(string $receiverId): BlindPayApiResponse
    {
        if (empty($receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        $response = $this->client->get("instances/{$this->instanceId}/receivers/{$receiverId}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                ReceiverOut::fromArray($response->data)
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

        return $this->client->put(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}",
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

        return $this->client->delete("instances/{$this->instanceId}/receivers/{$receiverId}");
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

        $response = $this->client->get("instances/{$this->instanceId}/limits/receivers/{$receiverId}");

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

        $response = $this->client->get("instances/{$this->instanceId}/receivers/{$receiverId}/limit-increase");

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
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/limit-increase",
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
