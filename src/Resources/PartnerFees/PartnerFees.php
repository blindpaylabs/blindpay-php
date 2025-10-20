<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\PartnerFees;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;

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
        public string $evmWalletAddress,
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
            evmWalletAddress: $data['evm_wallet_address'],
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
        public string $evmWalletAddress,
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
            evmWalletAddress: $data['evm_wallet_address'],
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
    public function list(): BlindPayApiResponse
    {
        $response = $this->client->get("/instances/{$this->instanceId}/partner-fees");

        if ($response->isSuccess() && is_array($response->data)) {
            $fees = array_map(
                fn (array $item) => PartnerFee::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($fees);
        }

        return $response;
    }

    /*
     * Create a new partner fee
     *
     * @param CreatePartnerFeeInput $input
     * @return BlindPayApiResponse<CreatePartnerFeeResponse>
     */
    public function create(CreatePartnerFeeInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/partner-fees",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreatePartnerFeeResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get a partner fee by ID
     *
     * @param string $id
     * @return BlindPayApiResponse<GetPartnerFeeResponse>
     */
    public function get(string $id): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('ID cannot be empty')
            );
        }

        $response = $this->client->get("/instances/{$this->instanceId}/partner-fees/{$id}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                GetPartnerFeeResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Delete a partner fee
     *
     * @param string $id
     * @return BlindPayApiResponse<null>
     */
    public function delete(string $id): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('ID cannot be empty')
            );
        }

        return $this->client->delete("/instances/{$this->instanceId}/partner-fees/{$id}");
    }
}
