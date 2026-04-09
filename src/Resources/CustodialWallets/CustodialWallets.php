<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\CustodialWallets;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Network;
use DateTimeImmutable;

readonly class CustodialWallet
{
    public function __construct(
        public string $id,
        public string $receiverId,
        public string $instanceId,
        public Network $network,
        public string $address,
        public DateTimeImmutable $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            receiverId: $data['receiver_id'],
            instanceId: $data['instance_id'],
            network: Network::from($data['network']),
            address: $data['address'],
            createdAt: new DateTimeImmutable($data['created_at'])
        );
    }
}

readonly class WalletTokenBalance
{
    public function __construct(
        public float $amount,
        public string $token,
        public string $address
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            token: $data['token'],
            address: $data['address']
        );
    }
}

readonly class CustodialWalletBalanceResponse
{
    public function __construct(
        public ?WalletTokenBalance $usdc,
        public ?WalletTokenBalance $usdt,
        public ?WalletTokenBalance $usdb
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            usdc: isset($data['USDC']) ? WalletTokenBalance::fromArray($data['USDC']) : null,
            usdt: isset($data['USDT']) ? WalletTokenBalance::fromArray($data['USDT']) : null,
            usdb: isset($data['USDB']) ? WalletTokenBalance::fromArray($data['USDB']) : null
        );
    }
}

readonly class CreateCustodialWalletInput
{
    public function __construct(
        public string $receiverId,
        public Network $network
    ) {}

    public function toArray(): array
    {
        return [
            'network' => $this->network->value,
        ];
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
