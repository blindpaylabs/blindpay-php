<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Fees;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;

readonly class FeeOptions
{
    public function __construct(
        public ?float $payinFlat,
        public ?float $payinPercentage,
        public ?float $payoutFlat,
        public ?float $payoutPercentage
    ) {}

    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return new self(
            payinFlat: isset($data['payin_flat']) ? (float) $data['payin_flat'] : null,
            payinPercentage: isset($data['payin_percentage']) ? (float) $data['payin_percentage'] : null,
            payoutFlat: isset($data['payout_flat']) ? (float) $data['payout_flat'] : null,
            payoutPercentage: isset($data['payout_percentage']) ? (float) $data['payout_percentage'] : null
        );
    }
}

readonly class FeesResponse
{
    public function __construct(
        public ?FeeOptions $ach,
        public ?FeeOptions $domesticWire,
        public ?FeeOptions $rtp,
        public ?FeeOptions $internationalSwift,
        public ?FeeOptions $pix,
        public ?FeeOptions $pixSafe,
        public ?FeeOptions $achColombia,
        public ?FeeOptions $transfers3,
        public ?FeeOptions $spei,
        public ?FeeOptions $tron,
        public ?FeeOptions $ethereum,
        public ?FeeOptions $polygon,
        public ?FeeOptions $base,
        public ?FeeOptions $arbitrum,
        public ?FeeOptions $stellar,
        public ?FeeOptions $solana
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            ach: FeeOptions::fromArray($data['ach'] ?? null),
            domesticWire: FeeOptions::fromArray($data['domestic_wire'] ?? null),
            rtp: FeeOptions::fromArray($data['rtp'] ?? null),
            internationalSwift: FeeOptions::fromArray($data['international_swift'] ?? null),
            pix: FeeOptions::fromArray($data['pix'] ?? null),
            pixSafe: FeeOptions::fromArray($data['pix_safe'] ?? null),
            achColombia: FeeOptions::fromArray($data['ach_colombia'] ?? null),
            transfers3: FeeOptions::fromArray($data['transfers_3'] ?? null),
            spei: FeeOptions::fromArray($data['spei'] ?? null),
            tron: FeeOptions::fromArray($data['tron'] ?? null),
            ethereum: FeeOptions::fromArray($data['ethereum'] ?? null),
            polygon: FeeOptions::fromArray($data['polygon'] ?? null),
            base: FeeOptions::fromArray($data['base'] ?? null),
            arbitrum: FeeOptions::fromArray($data['arbitrum'] ?? null),
            stellar: FeeOptions::fromArray($data['stellar'] ?? null),
            solana: FeeOptions::fromArray($data['solana'] ?? null)
        );
    }
}

class Fees
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * Get all instance fees
     *
     * @return BlindPayApiResponse<FeesResponse>
     */
    public function get(): BlindPayApiResponse
    {
        $response = $this->client->get("instances/{$this->instanceId}/billing/fees");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                FeesResponse::fromArray($response->data)
            );
        }

        return $response;
    }
}
