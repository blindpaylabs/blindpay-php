<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Available;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Rail;

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
        public bool $required
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
            required: $data['required']
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
    public function getBankDetails(Rail $rail): BlindPayApiResponse
    {
        $response = $this->client->get("available/bank-details?rail={$rail->value}");

        if ($response->isSuccess() && is_array($response->data)) {
            $bankDetails = array_map(
                fn (array $item) => BankDetail::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($bankDetails);
        }

        return $response;
    }

    /*
     * Get all available rails
     *
     * @return BlindPayApiResponse<RailInfo[]>
     */
    public function getRails(): BlindPayApiResponse
    {
        $response = $this->client->get('available/rails');

        if ($response->isSuccess() && is_array($response->data)) {
            $rails = array_map(
                fn (array $item) => RailInfo::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($rails);
        }

        return $response;
    }
}
