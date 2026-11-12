<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\CustodialWallets;

use BlindPay\SDK\BlindPayApiException;
use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\AutoPager;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Internal\Query;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\ListResponse;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\PaginationParams;
use BlindPay\SDK\Types\RequestOptions;
use BlindPay\SDK\Types\StablecoinToken;
use DateTimeImmutable;
use Generator;

enum WalletDepositStatus: string
{
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case ON_HOLD = 'on_hold';
    case REFUNDED = 'refunded';
}

/** A deposit into a managed wallet. */
readonly class WalletDeposit
{
    /**
     * @param  int  $amount  in cents of the token (1000 = 10.00)
     * @param  string|null  $tokenAmount  the exact on-chain amount, as a decimal string in whole tokens
     */
    public function __construct(
        public string $id,
        public string $walletId,
        public Network|string $network,
        public StablecoinToken|string $token,
        public int $amount,
        public ?string $tokenAmount,
        public WalletDepositStatus|string $status,
        public string $transactionHash,
        public ?string $senderAddress,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            walletId: $data['wallet_id'],
            network: Decode::enum(Network::class, $data['network']),
            token: Decode::enum(StablecoinToken::class, $data['token']),
            amount: (int) $data['amount'],
            tokenAmount: isset($data['token_amount']) ? (string) $data['token_amount'] : null,
            status: Decode::enum(WalletDepositStatus::class, $data['status']),
            transactionHash: $data['transaction_hash'],
            senderAddress: $data['sender_address'] ?? null,
            createdAt: new DateTimeImmutable($data['created_at']),
        );
    }
}

/**
 * @extends ListResponse<WalletDeposit>
 */
readonly class ListWalletDepositsResponse extends ListResponse
{
    public static function fromArray(array $data): self
    {
        return self::decode($data, WalletDeposit::fromArray(...));
    }
}

/** Deposits of one managed wallet. `limit` accepts 5, 10, 50, 100 or 200 here. */
readonly class GetWalletDepositsInput extends PaginationParams
{
    public function __construct(
        public string $customerId,
        public string $walletId,
        public ?StablecoinToken $token = null,
        public ?WalletDepositStatus $status = null,
        ?int $limit = null,
        ?string $startingAfter = null,
        ?string $endingBefore = null,
    ) {
        parent::__construct($limit, null, $startingAfter, $endingBefore);
    }

    public function toArray(): array
    {
        return parent::toArray() + array_filter([
            'token' => $this->token?->value,
            'status' => $this->status?->value,
        ], static fn ($value) => $value !== null);
    }
}

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
        public ?DateTimeImmutable $createdAt,
        public ?string $name = null,
        public ?string $externalId = null,
        public ?string $yieldStatus = null,
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
                : null,
            name: $data['name'] ?? null,
            externalId: $data['external_id'] ?? null,
            yieldStatus: $data['yield_status'] ?? null,
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
        public string $address,
        public ?float $earningAmount = null,
        public ?float $totalAmount = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            token: $data['symbol'],
            address: $data['address'],
            earningAmount: isset($data['earning_amount']) ? (float) $data['earning_amount'] : null,
            totalAmount: isset($data['total_amount']) ? (float) $data['total_amount'] : null,
        );
    }
}

readonly class CustodialWalletBalanceResponse
{
    public function __construct(
        public ?WalletTokenBalance $usdc,
        public ?WalletTokenBalance $usdt,
        public ?WalletTokenBalance $usdb,
        public ?WalletTokenBalance $eurc = null,
        public ?WalletTokenBalance $eurb = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            usdc: isset($data['USDC']) ? WalletTokenBalance::fromArray($data['USDC']) : null,
            usdt: isset($data['USDT']) ? WalletTokenBalance::fromArray($data['USDT']) : null,
            usdb: isset($data['USDB']) ? WalletTokenBalance::fromArray($data['USDB']) : null,
            eurc: isset($data['EURC']) ? WalletTokenBalance::fromArray($data['EURC']) : null,
            eurb: isset($data['EURB']) ? WalletTokenBalance::fromArray($data['EURB']) : null,
        );
    }
}

readonly class CreateCustodialWalletInput
{
    /**
     * @param  string|null  $externalId  your own ID for the wallet (up to 255 characters)
     */
    public function __construct(
        public string $customerId,
        public Network $network,
        public string $name,
        public ?string $externalId = null
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'network' => $this->network->value,
            'name' => $this->name,
            'external_id' => $this->externalId,
        ], static fn ($value) => $value !== null);
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

    /**
     * List custodial wallets for a customer
     *
     * @return BlindPayApiResponse<CustodialWallet[]>
     */
    public function list(
        string $customerId,
        ?RequestOptions $options = null
    ): BlindPayApiResponse {
        if ($customerId === '') {
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

    /**
     * Get a custodial wallet
     *
     * @return BlindPayApiResponse<CustodialWallet>
     */
    public function get(GetCustodialWalletInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($input->id === '') {
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

    /**
     * Create a custodial wallet
     *
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

    /**
     * Get custodial wallet balance
     *
     * @return BlindPayApiResponse<CustodialWalletBalanceResponse>
     */
    public function getBalance(GetCustodialWalletInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($input->id === '') {
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

    /**
     * Delete a custodial wallet
     *
     * @return BlindPayApiResponse<null>
     */
    public function delete(DeleteCustodialWalletInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($input->id === '') {
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

    /**
     * One page of a managed wallet's deposits (the API always returns `{data, pagination}`).
     *
     * @return BlindPayApiResponse<ListWalletDepositsResponse>
     */
    public function getDeposits(GetWalletDepositsInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($input->walletId === '') {
            return BlindPayApiResponse::error(ErrorResponse::validation('Wallet ID cannot be empty'));
        }

        return $this->depositsPage($input, $input->toArray(), $options);
    }

    /**
     * Every deposit of a managed wallet, fetched lazily page by page (`limit` 100 unless you set
     * one). Forward only: `endingBefore` is rejected. A failed page throws BlindPayApiException.
     *
     * @return Generator<int, WalletDeposit>
     *
     * @throws BlindPayApiException
     */
    public function getDepositsAutoPaging(GetWalletDepositsInput $input, ?RequestOptions $options = null): Generator
    {
        return AutoPager::iterate($input->toArray(), fn (array $query) => $this->depositsPage($input, $query, $options));
    }

    /**
     * @param  array<string, string>  $query
     * @return BlindPayApiResponse<ListWalletDepositsResponse>
     */
    private function depositsPage(GetWalletDepositsInput $input, array $query, ?RequestOptions $options): BlindPayApiResponse
    {
        return $this->client->get(
            Path::of('instances/%s/customers/%s/wallets/%s/deposits', $this->instanceId, $input->customerId, $input->walletId).Query::build($query),
            $options
        )->map(fn (array $data) => ListWalletDepositsResponse::fromArray($data));
    }
}
