<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\CustodialWallets;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\RequestOptions;
use DateTimeImmutable;

readonly class CustodialWallet
{
    public function __construct(
        public string $id,
        /** @deprecated Not returned by the API (WalletOut has no customer_id); always null. */
        public ?string $customerId,
        /** @deprecated Not returned by the API (WalletOut has no instance_id); always null. */
        public ?string $instanceId,
        public Network|string $network,
        public ?string $address,
        public ?DateTimeImmutable $createdAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            customerId: $data['customer_id'] ?? null,
            instanceId: $data['instance_id'] ?? null,
            network: Decode::enum(Network::class, $data['network']),
            address: $data['address'] ?? null,
            createdAt: isset($data['created_at'])
                ? new DateTimeImmutable($data['created_at'])
                : null
        );
    }
}

readonly class WalletTokenBalance
{
    /**
     * @param  string  $token  the token symbol (wire key `symbol`), e.g. USDC
     */
    public function __construct(
        public float $amount,
        public string $token,
        public string $address
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            token: $data['symbol'],
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
        public string $customerId,
        public Network $network,
        public string $name
    ) {}

    public function toArray(): array
    {
        return [
            'network' => $this->network->value,
            'name' => $this->name,
        ];
    }
}

readonly class GetCustodialWalletInput
{
    public function __construct(
        public string $customerId,
        public string $id
    ) {}
}

readonly class DeleteCustodialWalletInput
{
    public function __construct(
        public string $customerId,
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
     * List custodial wallets for a customer
     *
     * @param string $customerId
     * @return BlindPayApiResponse<CustodialWallet[]>
     */
    public function list(
        string $customerId,
        ?RequestOptions $options = null
    ): BlindPayApiResponse {
        if (empty($customerId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Customer ID cannot be empty')
            );
        }

        $response = $this->client->get(
            Path::of('instances/%s/customers/%s/wallets', $this->instanceId, $customerId),
            $options
        );

        return $response->map(function (array $data) {
            $wallets = array_map(
                fn (array $item) => CustodialWallet::fromArray($item),
                $data
            );

            return $wallets;
        });
    }

    /*
     * Get a custodial wallet
     *
     * @param GetCustodialWalletInput $input
     * @return BlindPayApiResponse<CustodialWallet>
     */
    public function get(GetCustodialWalletInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($input->id)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Wallet ID cannot be empty')
            );
        }

        $response = $this->client->get(
            Path::of('instances/%s/customers/%s/wallets/%s', $this->instanceId, $input->customerId, $input->id),
            $options
        );

        return $response->map(fn (array $data) => CustodialWallet::fromArray($data));
    }

    /*
     * Create a custodial wallet
     *
     * @param CreateCustodialWalletInput $input
     * @return BlindPayApiResponse<CustodialWallet>
     */
    public function create(CreateCustodialWalletInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/customers/%s/wallets', $this->instanceId, $input->customerId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => CustodialWallet::fromArray($data));
    }

    /*
     * Get custodial wallet balance
     *
     * @param GetCustodialWalletInput $input
     * @return BlindPayApiResponse<CustodialWalletBalanceResponse>
     */
    public function getBalance(GetCustodialWalletInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($input->id)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Wallet ID cannot be empty')
            );
        }

        $response = $this->client->get(
            Path::of('instances/%s/customers/%s/wallets/%s/balance', $this->instanceId, $input->customerId, $input->id),
            $options
        );

        return $response->map(fn (array $data) => CustodialWalletBalanceResponse::fromArray($data));
    }

    /*
     * Delete a custodial wallet
     *
     * @param DeleteCustodialWalletInput $input
     * @return BlindPayApiResponse<null>
     */
    public function delete(DeleteCustodialWalletInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($input->id)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Wallet ID cannot be empty')
            );
        }

        return $this->client->delete(
            Path::of('instances/%s/customers/%s/wallets/%s', $this->instanceId, $input->customerId, $input->id),
            null,
            $options
        );
    }
}
