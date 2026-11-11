<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\PartnerFees;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\RequestOptions;

readonly class PartnerFee
{
    public function __construct(
        public string $id,
        public string $instanceId,
        public string $name,
        public float $payoutPercentageFee,
        public float $payoutFlatFee,
        public float $payinPercentageFee,
        public float $payinFlatFee,
        /** @deprecated Not returned by the API (no evm_wallet_address on partner fees); always null. */
        public ?string $evmWalletAddress,
        public ?string $stellarWalletAddress = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            instanceId: $data['instance_id'],
            name: $data['name'],
            payoutPercentageFee: (float) $data['payout_percentage_fee'],
            payoutFlatFee: (float) $data['payout_flat_fee'],
            payinPercentageFee: (float) $data['payin_percentage_fee'],
            payinFlatFee: (float) $data['payin_flat_fee'],
            evmWalletAddress: $data['evm_wallet_address'] ?? null,
            stellarWalletAddress: $data['stellar_wallet_address'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'instance_id' => $this->instanceId,
            'name' => $this->name,
            'payout_percentage_fee' => $this->payoutPercentageFee,
            'payout_flat_fee' => $this->payoutFlatFee,
            'payin_percentage_fee' => $this->payinPercentageFee,
            'payin_flat_fee' => $this->payinFlatFee,
            'evm_wallet_address' => $this->evmWalletAddress,
            'stellar_wallet_address' => $this->stellarWalletAddress,
        ];
    }
}

readonly class CreatePartnerFeeInput
{
    public function __construct(
        public string $evmWalletAddress,
        public string $name,
        public float $payinFlatFee,
        public float $payinPercentageFee,
        public float $payoutFlatFee,
        public float $payoutPercentageFee,
        public ?bool $virtualAccountSet = null,
        public ?string $stellarWalletAddress = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'evm_wallet_address' => $this->evmWalletAddress,
            'name' => $this->name,
            'payin_flat_fee' => $this->payinFlatFee,
            'payin_percentage_fee' => $this->payinPercentageFee,
            'payout_flat_fee' => $this->payoutFlatFee,
            'payout_percentage_fee' => $this->payoutPercentageFee,
        ];

        if ($this->virtualAccountSet !== null) {
            $data['virtual_account_set'] = $this->virtualAccountSet;
        }

        if ($this->stellarWalletAddress !== null) {
            $data['stellar_wallet_address'] = $this->stellarWalletAddress;
        }

        return $data;
    }
}

readonly class CreatePartnerFeeResponse
{
    public function __construct(
        public string $id,
        public string $instanceId,
        public string $name,
        public float $payoutPercentageFee,
        public float $payoutFlatFee,
        public float $payinPercentageFee,
        public float $payinFlatFee,
        public ?string $evmWalletAddress = null,
        public ?string $stellarWalletAddress = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            instanceId: $data['instance_id'],
            name: $data['name'],
            payoutPercentageFee: (float) $data['payout_percentage_fee'],
            payoutFlatFee: (float) $data['payout_flat_fee'],
            payinPercentageFee: (float) $data['payin_percentage_fee'],
            payinFlatFee: (float) $data['payin_flat_fee'],
            evmWalletAddress: $data['evm_wallet_address'] ?? null,
            stellarWalletAddress: $data['stellar_wallet_address'] ?? null
        );
    }
}

readonly class GetPartnerFeeResponse
{
    public function __construct(
        public string $id,
        public string $instanceId,
        /** @deprecated Not returned by the API (no evm_wallet_address on partner fees); always null. */
        public ?string $evmWalletAddress,
        public string $name,
        public float $payinFlatFee,
        public float $payinPercentageFee,
        public float $payoutFlatFee,
        public float $payoutPercentageFee,
        public ?string $stellarWalletAddress = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            instanceId: $data['instance_id'],
            evmWalletAddress: $data['evm_wallet_address'] ?? null,
            name: $data['name'],
            payinFlatFee: (float) $data['payin_flat_fee'],
            payinPercentageFee: (float) $data['payin_percentage_fee'],
            payoutFlatFee: (float) $data['payout_flat_fee'],
            payoutPercentageFee: (float) $data['payout_percentage_fee'],
            stellarWalletAddress: $data['stellar_wallet_address'] ?? null
        );
    }
}

class PartnerFees
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * List all partner fees
     *
     * @return BlindPayApiResponse<PartnerFee[]>
     */
    public function list(?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->get(Path::of('instances/%s/partner-fees', $this->instanceId), $options);

        return $response->map(function (array $data) {
            $fees = array_map(
                fn (array $item) => PartnerFee::fromArray($item),
                $data
            );

            return $fees;
        });
    }

    /*
     * Create a new partner fee
     *
     * @param CreatePartnerFeeInput $input
     * @return BlindPayApiResponse<CreatePartnerFeeResponse>
     */
    public function create(CreatePartnerFeeInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/partner-fees', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => CreatePartnerFeeResponse::fromArray($data));
    }

    /*
     * Get a partner fee by ID
     *
     * @param string $id
     * @return BlindPayApiResponse<GetPartnerFeeResponse>
     */
    public function get(string $id, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('ID cannot be empty')
            );
        }

        $response = $this->client->get(Path::of('instances/%s/partner-fees/%s', $this->instanceId, $id), $options);

        return $response->map(fn (array $data) => GetPartnerFeeResponse::fromArray($data));
    }

    /*
     * Delete a partner fee
     *
     * @param string $id
     * @return BlindPayApiResponse<null>
     */
    public function delete(string $id, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('ID cannot be empty')
            );
        }

        return $this->client->delete(Path::of('instances/%s/partner-fees/%s', $this->instanceId, $id), null, $options);
    }
}
