<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Instances;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Resources\Customers\OwnerRole;
use BlindPay\SDK\Resources\Customers\ProofOfAddressDocType;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Country;
use BlindPay\SDK\Types\RequestOptions;

// Your instance's own onboarding (KYB) data, as submitted in the dashboard. Every field is
// optional: it is empty until that onboarding step is filled in.

readonly class OnboardingBusinessDetails
{
    /**
     * @param  string[]|null  $tradingNames
     * @param  string|null  $entityType  cooperative, corporation, company, partnership, sole_proprietor, trust or other
     */
    public function __construct(
        public ?string $legalName = null,
        public ?string $taxId = null,
        public ?string $formationDate = null,
        public ?array $tradingNames = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $city = null,
        public ?string $stateProvinceRegion = null,
        public ?string $postalCode = null,
        public Country|string|null $country = null,
        public ?string $entityType = null,
        public ?string $stateOfIncorporation = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            legalName: $data['legal_name'] ?? null,
            taxId: $data['tax_id'] ?? null,
            formationDate: $data['formation_date'] ?? null,
            tradingNames: $data['trading_names'] ?? null,
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'] ?? null,
            stateProvinceRegion: $data['state_province_region'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            country: Decode::enum(Country::class, $data['country'] ?? null),
            entityType: $data['entity_type'] ?? null,
            stateOfIncorporation: $data['state_of_incorporation'] ?? null,
        );
    }
}

readonly class OnboardingBusinessProfile
{
    /**
     * @param  string|null  $scenario  custodial, custodial_mtl, non_custodial or other
     * @param  string|null  $businessIndustry  wallet, exchange, dapp, neo_bank, gaming, marketplace, saas, social, infra, gambling or other
     * @param  string[]|null  $topVolumeByCountry  ISO 3166-1 alpha-2 codes
     * @param  string|null  $estimatedVolumePerMonth  up_to_100, up_to_500, up_to_1000, up_to_10000 or more_than_10000
     */
    public function __construct(
        public ?string $scenario = null,
        public ?string $scenarioDescription = null,
        public ?bool $hasKycScreening = null,
        public ?bool $isRegulated = null,
        public ?string $businessIndustry = null,
        public ?string $website = null,
        public ?array $topVolumeByCountry = null,
        public ?string $estimatedVolumePerMonth = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            scenario: $data['scenario'] ?? null,
            scenarioDescription: $data['scenario_description'] ?? null,
            hasKycScreening: $data['has_kyc_screening'] ?? null,
            isRegulated: $data['is_regulated'] ?? null,
            businessIndustry: $data['business_industry'] ?? null,
            website: $data['website'] ?? null,
            topVolumeByCountry: $data['top_volume_by_country'] ?? null,
            estimatedVolumePerMonth: $data['estimated_volume_per_month'] ?? null,
        );
    }
}

readonly class OnboardingOwner
{
    public function __construct(
        public ?string $id = null,
        public ?string $instanceId = null,
        public OwnerRole|string|null $role = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        /** 0 to 100 */
        public ?float $ownershipPercentage = null,
        public ?string $dateOfBirth = null,
        public ?string $taxId = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $city = null,
        public ?string $stateProvinceRegion = null,
        public Country|string|null $country = null,
        public ?string $postalCode = null,
        /** every other field of the owner, as returned */
        public array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            instanceId: $data['instance_id'] ?? null,
            role: Decode::enum(OwnerRole::class, $data['role'] ?? null),
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            ownershipPercentage: isset($data['ownership_percentage']) ? (float) $data['ownership_percentage'] : null,
            dateOfBirth: $data['date_of_birth'] ?? null,
            taxId: $data['tax_id'] ?? null,
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'] ?? null,
            stateProvinceRegion: $data['state_province_region'] ?? null,
            country: Decode::enum(Country::class, $data['country'] ?? null),
            postalCode: $data['postal_code'] ?? null,
            raw: $data,
        );
    }
}

readonly class OnboardingOwnershipDocuments
{
    /**
     * @param  OnboardingOwner[]|null  $owners
     */
    public function __construct(
        public ?string $incorporationDocFile = null,
        public ?string $proofOfOwnershipDocFile = null,
        public ProofOfAddressDocType|string|null $proofOfAddressDocType = null,
        public ?string $proofOfAddressDocFile = null,
        public ?array $owners = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            incorporationDocFile: $data['incorporation_doc_file'] ?? null,
            proofOfOwnershipDocFile: $data['proof_of_ownership_doc_file'] ?? null,
            proofOfAddressDocType: Decode::enum(ProofOfAddressDocType::class, $data['proof_of_address_doc_type'] ?? null),
            proofOfAddressDocFile: $data['proof_of_address_doc_file'] ?? null,
            owners: isset($data['owners']) ? array_map(static fn (array $owner) => OnboardingOwner::fromArray($owner), $data['owners']) : null,
        );
    }
}

/** `$blindpay->instances->onboarding`: read your instance's onboarding (KYB) data. */
class Onboarding
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client,
    ) {}

    /** @return BlindPayApiResponse<OnboardingBusinessDetails> */
    public function getBusinessDetails(?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->client->get(Path::of('instances/%s/onboarding/business_details', $this->instanceId), $options)
            ->map(fn (array $data) => OnboardingBusinessDetails::fromArray($data));
    }

    /** @return BlindPayApiResponse<OnboardingBusinessProfile> */
    public function getBusinessProfile(?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->client->get(Path::of('instances/%s/onboarding/business_profile', $this->instanceId), $options)
            ->map(fn (array $data) => OnboardingBusinessProfile::fromArray($data));
    }

    /** @return BlindPayApiResponse<OnboardingOwnershipDocuments> */
    public function getOwnershipDocuments(?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->client->get(Path::of('instances/%s/onboarding/ownership_documents', $this->instanceId), $options)
            ->map(fn (array $data) => OnboardingOwnershipDocuments::fromArray($data));
    }
}
