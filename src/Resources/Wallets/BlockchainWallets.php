<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Wallets;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Network;

readonly class BlockchainWallet
{
    public function __construct(
        public string $id,
        public string $name,
        public Network $network,
        public bool $isAccountAbstraction,
        public string $receiverId,
        public ?string $address = null,
        public ?string $signatureTxHash = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            network: Network::from($data['network']),
            isAccountAbstraction: $data['is_account_abstraction'],
            receiverId: $data['receiver_id'],
            address: $data['address'] ?? null,
            signatureTxHash: $data['signature_tx_hash'] ?? null
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
        public string $receiverId,
        public string $name,
        public Network $network,
        public string $address
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'network' => $this->network->value,
            'address' => $this->address,
            'is_account_abstraction' => true,
        ];
    }
}

readonly class CreateBlockchainWalletWithHashInput
{
    public function __construct(
        public string $receiverId,
        public string $name,
        public Network $network,
        public string $signatureTxHash
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'network' => $this->network->value,
            'signature_tx_hash' => $this->signatureTxHash,
            'is_account_abstraction' => false,
        ];
    }
}

readonly class GetBlockchainWalletInput
{
    public function __construct(
        public string $receiverId,
        public string $id
    ) {}
}

readonly class DeleteBlockchainWalletInput
{
    public function __construct(
        public string $receiverId,
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
    public function list(string $receiverId): BlindPayApiResponse
    {
        if (empty($receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        $response = $this->client->get(
            "instances/{$this->instanceId}/receivers/{$receiverId}/blockchain-wallets"
        );

        if ($response->isSuccess() && is_array($response->data)) {
            $wallets = array_map(
                fn (array $item) => BlockchainWallet::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($wallets);
        }

        return $response;
    }

    /**
     * Create blockchain wallet with address (account abstraction)
     *
     * @return BlindPayApiResponse<BlockchainWallet>
     */
    public function createWithAddress(CreateBlockchainWalletWithAddressInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/blockchain-wallets",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                BlockchainWallet::fromArray($response->data)
            );
        }

        return $response;
    }

    /**
     * Create blockchain wallet with hash (signature-based)
     *
     * @return BlindPayApiResponse<BlockchainWallet>
     */
    public function createWithHash(CreateBlockchainWalletWithHashInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/blockchain-wallets",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                BlockchainWallet::fromArray($response->data)
            );
        }

        return $response;
    }

    /**
     * Get blockchain wallet sign message
     *
     * @return BlindPayApiResponse<GetBlockchainWalletMessageResponse>
     */
    public function getMessage(string $receiverId): BlindPayApiResponse
    {
        if (empty($receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        $response = $this->client->get(
            "instances/{$this->instanceId}/receivers/{$receiverId}/blockchain-wallets/sign-message"
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                GetBlockchainWalletMessageResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /**
     * Get blockchain wallet
     *
     * @return BlindPayApiResponse<BlockchainWallet>
     */
    public function get(GetBlockchainWalletInput $input): BlindPayApiResponse
    {
        if (empty($input->id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Blockchain wallet ID cannot be empty')
            );
        }

        $response = $this->client->get(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/blockchain-wallets/{$input->id}"
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                BlockchainWallet::fromArray($response->data)
            );
        }

        return $response;
    }

    /**
     * Delete blockchain wallet
     *
     * @return BlindPayApiResponse<null>
     */
    public function delete(DeleteBlockchainWalletInput $input): BlindPayApiResponse
    {
        if (empty($input->id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Blockchain wallet ID cannot be empty')
            );
        }

        return $this->client->delete(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/blockchain-wallets/{$input->id}"
        );
    }

    /**
     * Create asset trustline
     *
     * @return BlindPayApiResponse<CreateAssetTrustlineResponse>
     */
    public function createAssetTrustline(string $address): BlindPayApiResponse
    {
        if (empty($address)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Address cannot be empty')
            );
        }

        $response = $this->client->post(
            "instances/{$this->instanceId}/create-asset-trustline",
            ['address' => $address]
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateAssetTrustlineResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /**
     * Mint USDB on Stellar
     *
     * @return BlindPayApiResponse<null>
     */
    public function mintUsdbStellar(MintUsdbStellarInput $input): BlindPayApiResponse
    {
        return $this->client->post(
            "instances/{$this->instanceId}/mint-usdb-stellar",
            $input->toArray()
        );
    }
}
