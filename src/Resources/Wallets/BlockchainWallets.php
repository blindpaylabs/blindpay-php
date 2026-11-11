<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Wallets;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\RequestOptions;

readonly class BlockchainWallet
{
    public function __construct(
        public string $id,
        public ?string $name,
        public Network|string|null $network,
        public ?bool $isAccountAbstraction,
        public string $customerId,
        public ?string $address = null,
        public ?string $signatureTxHash = null,
        /** null until set; a Brazilian customer's wallet must declare it (see setSelfCustody()) */
        public ?bool $isSelfCustody = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'] ?? null,
            network: Decode::enum(Network::class, $data['network'] ?? null),
            isAccountAbstraction: $data['is_account_abstraction'] ?? null,
            customerId: $data['customer_id'],
            address: $data['address'] ?? null,
            signatureTxHash: $data['signature_tx_hash'] ?? null,
            isSelfCustody: $data['is_self_custody'] ?? null,
        );
    }
}

readonly class GetBlockchainWalletMessageResponse
{
    public function __construct(
        public string $message
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            message: $data['message']
        );
    }
}

readonly class CreateBlockchainWalletWithAddressInput
{
    public function __construct(
        public string $customerId,
        public string $name,
        public Network $network,
        public string $address,
        /** whether the customer holds the wallet's keys; required for Brazilian customers */
        public ?bool $isSelfCustody = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'network' => $this->network->value,
            'address' => $this->address,
            'is_account_abstraction' => true,
        ];
        if ($this->isSelfCustody !== null) {
            $data['is_self_custody'] = $this->isSelfCustody;
        }

        return $data;
    }
}

readonly class CreateBlockchainWalletWithHashInput
{
    public function __construct(
        public string $customerId,
        public string $name,
        public Network $network,
        public string $signatureTxHash,
        /** whether the customer holds the wallet's keys; required for Brazilian customers */
        public ?bool $isSelfCustody = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'network' => $this->network->value,
            'signature_tx_hash' => $this->signatureTxHash,
            'is_account_abstraction' => false,
        ];
        if ($this->isSelfCustody !== null) {
            $data['is_self_custody'] = $this->isSelfCustody;
        }

        return $data;
    }
}

readonly class SetSelfCustodyInput
{
    public function __construct(
        public string $customerId,
        public string $id,
        public bool $isSelfCustody,
    ) {}

    public function toArray(): array
    {
        return ['is_self_custody' => $this->isSelfCustody];
    }
}

readonly class GetBlockchainWalletInput
{
    public function __construct(
        public string $customerId,
        public string $id
    ) {}
}

readonly class DeleteBlockchainWalletInput
{
    public function __construct(
        public string $customerId,
        public string $id
    ) {}
}

readonly class CreateAssetTrustlineResponse
{
    public function __construct(
        public string $xdr
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            xdr: $data['xdr']
        );
    }
}

readonly class MintUsdbStellarInput
{
    public function __construct(
        public string $address,
        public string $amount,
        public string $signedXdr
    ) {}

    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'amount' => $this->amount,
            'signedXdr' => $this->signedXdr,
        ];
    }
}

readonly class MintUsdbSolanaInput
{
    public function __construct(
        public string $address,
        public string $amount
    ) {}

    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'amount' => $this->amount,
        ];
    }
}

readonly class MintUsdbSolanaResponse
{
    public function __construct(
        public bool $success,
        public ?string $signature,
        public ?string $error
    ) {}

    /** The API returns either `signature` (on success) or `error`, never both. */
    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'],
            signature: $data['signature'] ?? null,
            error: $data['error'] ?? null
        );
    }
}

/**
 * Pass `quoteId` (recommended: the API takes the amount and token from the quote), or
 * `tokenAddress` and `amount` (whole tokens, e.g. "10.5").
 */
readonly class PrepareSolanaDelegationTransactionInput
{
    public function __construct(
        public ?string $tokenAddress,
        public ?string $amount,
        public string $ownerAddress,
        public ?string $quoteId = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'owner_address' => $this->ownerAddress,
            'quote_id' => $this->quoteId,
            'token_address' => $this->tokenAddress,
            'amount' => $this->amount,
        ], static fn ($value) => $value !== null);
    }
}

readonly class PrepareSolanaDelegationTransactionResponse
{
    public function __construct(
        public bool $success,
        /** the base64 transaction to sign, when success is true */
        public ?string $transaction = null,
        public mixed $debug = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'],
            transaction: $data['transaction'] ?? null,
            debug: $data['debug'] ?? null,
        );
    }
}

/**
 * Blockchain Wallets resource
 */
class BlockchainWallets
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /**
     * List blockchain wallets
     *
     * @return BlindPayApiResponse<BlockchainWallet[]>
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
            Path::of('instances/%s/customers/%s/blockchain-wallets', $this->instanceId, $customerId),
            $options
        );

        return $response->map(function (array $data) {
            $wallets = array_map(
                fn (array $item) => BlockchainWallet::fromArray($item),
                $data
            );

            return $wallets;
        });
    }

    /**
     * Create blockchain wallet with address (account abstraction)
     *
     * @return BlindPayApiResponse<BlockchainWallet>
     */
    public function createWithAddress(CreateBlockchainWalletWithAddressInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/customers/%s/blockchain-wallets', $this->instanceId, $input->customerId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => BlockchainWallet::fromArray($data));
    }

    /**
     * Create blockchain wallet with hash (signature-based)
     *
     * @return BlindPayApiResponse<BlockchainWallet>
     */
    public function createWithHash(CreateBlockchainWalletWithHashInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/customers/%s/blockchain-wallets', $this->instanceId, $input->customerId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => BlockchainWallet::fromArray($data));
    }

    /**
     * Get blockchain wallet sign message
     *
     * @return BlindPayApiResponse<GetBlockchainWalletMessageResponse>
     */
    public function getMessage(string $customerId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($customerId === '') {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Customer ID cannot be empty')
            );
        }

        $response = $this->client->get(
            Path::of('instances/%s/customers/%s/blockchain-wallets/sign-message', $this->instanceId, $customerId),
            $options
        );

        return $response->map(fn (array $data) => GetBlockchainWalletMessageResponse::fromArray($data));
    }

    /**
     * Get blockchain wallet
     *
     * @return BlindPayApiResponse<BlockchainWallet>
     */
    public function get(GetBlockchainWalletInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($input->id === '') {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Blockchain wallet ID cannot be empty')
            );
        }

        $response = $this->client->get(
            Path::of('instances/%s/customers/%s/blockchain-wallets/%s', $this->instanceId, $input->customerId, $input->id),
            $options
        );

        return $response->map(fn (array $data) => BlockchainWallet::fromArray($data));
    }

    /**
     * Delete blockchain wallet
     *
     * @return BlindPayApiResponse<null>
     */
    public function delete(DeleteBlockchainWalletInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($input->id === '') {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Blockchain wallet ID cannot be empty')
            );
        }

        return $this->client->delete(
            Path::of('instances/%s/customers/%s/blockchain-wallets/%s', $this->instanceId, $input->customerId, $input->id),
            null,
            $options
        );
    }

    /**
     * Create asset trustline
     *
     * @return BlindPayApiResponse<CreateAssetTrustlineResponse>
     */
    public function createAssetTrustline(string $address, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($address === '') {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Address cannot be empty')
            );
        }

        $response = $this->client->post(
            Path::of('instances/%s/create-asset-trustline', $this->instanceId),
            ['address' => $address],
            $options
        );

        return $response->map(fn (array $data) => CreateAssetTrustlineResponse::fromArray($data));
    }

    /**
     * Mint USDB on Stellar
     *
     * @return BlindPayApiResponse<null>
     */
    public function mintUsdbStellar(MintUsdbStellarInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->client->post(
            Path::of('instances/%s/mint-usdb-stellar', $this->instanceId),
            $input->toArray(),
            $options
        );
    }

    /**
     * Mint USDB on Solana
     *
     * @return BlindPayApiResponse<MintUsdbSolanaResponse>
     */
    public function mintUsdbSolana(MintUsdbSolanaInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/mint-usdb-solana', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => MintUsdbSolanaResponse::fromArray($data));
    }

    /**
     * Prepare Solana delegation transaction
     *
     * @return BlindPayApiResponse<PrepareSolanaDelegationTransactionResponse>
     */
    public function prepareSolanaDelegationTransaction(PrepareSolanaDelegationTransactionInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/prepare-delegate-solana', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => PrepareSolanaDelegationTransactionResponse::fromArray($data));
    }

    /**
     * Declare whether the customer holds the wallet's keys. It can be set once, while the wallet's
     * value is still null; a second call fails with 409 WALLETS_SELF_CUSTODY_ALREADY_SET.
     *
     * @return BlindPayApiResponse<BlockchainWallet>
     */
    public function setSelfCustody(SetSelfCustodyInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($input->id === '') {
            return BlindPayApiResponse::error(ErrorResponse::validation('Blockchain wallet ID cannot be empty'));
        }

        return $this->client->patch(
            Path::of('instances/%s/customers/%s/blockchain-wallets/%s', $this->instanceId, $input->customerId, $input->id),
            $input->toArray(),
            $options
        )->map(fn (array $data) => BlockchainWallet::fromArray($data));
    }
}
