<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Available;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Rail;
use BlindPay\SDK\Types\RequestOptions;

readonly class BankDetailItem
{
    public function __construct(
        public string $label,
        public string $value,
        public ?bool $isActive = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'],
            value: $data['value'],
            isActive: $data['is_active'] ?? null
        );
    }
}

readonly class RailInfo
{
    public function __construct(
        public string $label,
        public string $value,
        public string $country
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'],
            value: $data['value'],
            country: $data['country']
        );
    }
}

readonly class BankDetail
{
    /*
     * @param BankDetailItem[] $items
     */
    public function __construct(
        public string $label,
        public string $regex,
        public string $key,
        public array $items,
        public ?bool $required
    ) {}

    public static function fromArray(array $data): self
    {
        $items = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $items[] = BankDetailItem::fromArray($item);
            }
        }

        return new self(
            label: $data['label'],
            regex: $data['regex'],
            key: $data['key'],
            items: $items,
            required: $data['required'] ?? null
        );
    }
}

readonly class SwiftCodeBankDetails
{
    public function __construct(
        public string $id,
        public string $bank,
        public string $city,
        public string $branch,
        public string $swiftCode,
        public string $swiftCodeLink,
        public string $country,
        public string $countrySlug
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            bank: $data['bank'],
            city: $data['city'],
            branch: $data['branch'],
            swiftCode: $data['swiftCode'],
            swiftCodeLink: $data['swiftCodeLink'],
            country: $data['country'],
            countrySlug: $data['countrySlug']
        );
    }
}

readonly class NaicsCode
{
    /**
     * @param  string  $code  the NAICS code (wire key `value`), e.g. "339910"
     * @param  string  $title  the display label (wire key `label`), e.g. "(339910) Jewelry and Silverware Manufacturing"
     */
    public function __construct(
        public string $code,
        public string $title
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['value'],
            title: $data['label']
        );
    }
}

class Available
{
    public function __construct(
        private readonly ApiClientInterface $client
    ) {}

    /*
     * Get bank details for a specific rail
     *
     * @param Rail $rail The rail type to get bank details for
     * @return BlindPayApiResponse<BankDetail[]>
     */
    public function getBankDetails(
        Rail $rail,
        ?RequestOptions $options = null
    ): BlindPayApiResponse {
        $response = $this->client->get("available/bank-details?rail={$rail->value}", $options);

        return $response->map(function (array $data) {
            $bankDetails = array_map(
                fn (array $item) => BankDetail::fromArray($item),
                $data
            );

            return $bankDetails;
        });
    }

    /*
     * Get all available rails
     *
     * @return BlindPayApiResponse<RailInfo[]>
     */
    public function getRails(?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->get('available/rails', $options);

        return $response->map(function (array $data) {
            $rails = array_map(
                fn (array $item) => RailInfo::fromArray($item),
                $data
            );

            return $rails;
        });
    }

    /*
     * Get bank details for a specific swift code
     *
     * @param string $swift The swift code to get bank details for
     * @return BlindPayApiResponse<SwiftCodeBankDetails[]>
     */
    public function getSwiftCodeBankDetails(string $swift, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->get(Path::of('available/swift/%s', $swift), $options);

        return $response->map(function (array $data) {
            $swiftCodeBankDetails = array_map(
                fn (array $item) => SwiftCodeBankDetails::fromArray($item),
                $data
            );

            return $swiftCodeBankDetails;
        });
    }

    /*
     * Get available NAICS business industry codes
     *
     * @return BlindPayApiResponse<NaicsCode[]>
     */
    public function getNaicsCodes(?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->get('available/naics', $options);

        return $response->map(function (array $data) {
            $naicsCodes = array_map(
                fn (array $item) => NaicsCode::fromArray($item),
                $data
            );

            return $naicsCodes;
        });
    }
}
