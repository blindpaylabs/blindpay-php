<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\CustodialWallets;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\StablecoinToken;
use DateTimeImmutable;

readonly class CustodialWallet
{
    public function __construct(
        public string $id,
        public string $name,
        public Network $network,
        public ?string $externalId = null,
        public ?string $address = null,
        public ?DateTimeImmutable $createdAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            network: Network::from($data['network']),
            externalId: $data['external_id'] ?? null,
            address: $data['address'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null
        );
    }
}

readonly class WalletTokenBalance
{
    public function __construct(
        public string $address,
        public string $id,
        public StablecoinToken $symbol,
        public float $amount
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            address: $data['address'],
            id: $data['id'],
            symbol: StablecoinToken::from($data['symbol']),
            amount: (float) $data['amount']
        );
    }
}

readonly class CustodialWalletBalanceResponse
{
    public function __construct(
        public WalletTokenBalance $usdc,
        public WalletTokenBalance $usdt,
        public WalletTokenBalance $usdb
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            usdc: WalletTokenBalance::fromArray($data['USDC']),
            usdt: WalletTokenBalance::fromArray($data['USDT']),
            usdb: WalletTokenBalance::fromArray($data['USDB'])
        );
    }
}

readonly class CreateCustodialWalletInput
{
    public function __construct(
        public string $receiverId,
        public Network $network,
        public string $name,
        public ?string $externalId = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'network' => $this->network->value,
            'name' => $this->name,
        ];

        if ($this->externalId !== null) {
            $data['external_id'] = $this->externalId;
        }

        return $data;
    }
}

readonly class GetCustodialWalletInput
{
    public function __construct(
        public string $receiverId,
        public string $id
    ) {}
}

readonly class DeleteCustodialWalletInput
{
    public function __construct(
        public string $receiverId,
        public string $id
    ) {}
}

class CustodialWallets
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * List custodial wallets for a receiver
     *
     * @param string $receiverId
     * @return BlindPayApiResponse<CustodialWallet[]>
     */
    public function list(string $receiverId): BlindPayApiResponse
    {
        if (empty($receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        $response = $this->client->get(
            "instances/{$this->instanceId}/receivers/{$receiverId}/wallets"
        );

        if ($response->isSuccess() && is_array($response->data)) {
            $wallets = array_map(
                fn (array $item) => CustodialWallet::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($wallets);
        }

        return $response;
    }

    /*
     * Get a custodial wallet
     *
     * @param GetCustodialWalletInput $input
     * @return BlindPayApiResponse<CustodialWallet>
     */
    public function get(GetCustodialWalletInput $input): BlindPayApiResponse
    {
        if (empty($input->id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Wallet ID cannot be empty')
            );
        }

        $response = $this->client->get(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/wallets/{$input->id}"
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CustodialWallet::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create a custodial wallet
     *
     * @param CreateCustodialWalletInput $input
     * @return BlindPayApiResponse<CustodialWallet>
     */
    public function create(CreateCustodialWalletInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/wallets",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CustodialWallet::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get custodial wallet balance
     *
     * @param GetCustodialWalletInput $input
     * @return BlindPayApiResponse<CustodialWalletBalanceResponse>
     */
    public function getBalance(GetCustodialWalletInput $input): BlindPayApiResponse
    {
        if (empty($input->id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Wallet ID cannot be empty')
            );
        }

        $response = $this->client->get(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/wallets/{$input->id}/balance"
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CustodialWalletBalanceResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Delete a custodial wallet
     *
     * @param DeleteCustodialWalletInput $input
     * @return BlindPayApiResponse<null>
     */
    public function delete(DeleteCustodialWalletInput $input): BlindPayApiResponse
    {
        if (empty($input->id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Wallet ID cannot be empty')
            );
        }

        return $this->client->delete(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/wallets/{$input->id}"
        );
    }
}
