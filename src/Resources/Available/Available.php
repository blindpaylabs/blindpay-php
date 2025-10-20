<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BankDetail;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Rail;
use BlindPay\SDK\Types\RailInfo;

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

        if ($response->isSuccess()) {
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

        if ($response->isSuccess()) {
            $rails = array_map(
                fn (array $item) => RailInfo::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($rails);
        }

        return $response;
    }
}
