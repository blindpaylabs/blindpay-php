<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Wallets;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use DateTimeImmutable;

readonly class OfframpWallet
{
    public function __construct(
        public string $id,
        public string $externalId,
        public string $instanceId,
        public string $receiverId,
        public string $bankAccountId,
        public string $network,
        public string $address,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            externalId: $data['external_id'],
            instanceId: $data['instance_id'],
            receiverId: $data['receiver_id'],
            bankAccountId: $data['bank_account_id'],
            network: $data['network'],
            address: $data['address'],
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at'])
        );
    }
}

readonly class ListOfframpWalletsInput
{
    public function __construct(
        public string $receiverId,
        public string $bankAccountId
    ) {}
}

readonly class CreateOfframpWalletInput
{
    public function __construct(
        public string $receiverId,
        public string $bankAccountId,
        public string $externalId,
        public string $network
    ) {}

    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'network' => $this->network,
        ];
    }
}

readonly class CreateOfframpWalletResponse
{
    public function __construct(
        public string $id,
        public string $externalId,
        public string $network,
        public string $address
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            externalId: $data['external_id'],
            network: $data['network'],
            address: $data['address']
        );
    }
}

readonly class GetOfframpWalletInput
{
    public function __construct(
        public string $receiverId,
        public string $bankAccountId,
        public string $id
    ) {}
}

/**
 * Offramp Wallets resource
 */
class OfframpWallets
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /**
     * List offramp wallets
     *
     * @return BlindPayApiResponse<OfframpWallet[]>
     */
    public function list(ListOfframpWalletsInput $input): BlindPayApiResponse
    {
        $response = $this->client->get(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts/{$input->bankAccountId}/offramp-wallets"
        );

        if ($response->isSuccess() && is_array($response->data)) {
            $wallets = array_map(
                fn (array $item) => OfframpWallet::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($wallets);
        }

        return $response;
    }

    /**
     * Create offramp wallet
     *
     * @return BlindPayApiResponse<CreateOfframpWalletResponse>
     */
    public function create(CreateOfframpWalletInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts/{$input->bankAccountId}/offramp-wallets",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateOfframpWalletResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /**
     * Get offramp wallet
     *
     * @return BlindPayApiResponse<OfframpWallet>
     */
    public function get(GetOfframpWalletInput $input): BlindPayApiResponse
    {
        if (empty($input->id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Offramp wallet ID cannot be empty')
            );
        }

        $response = $this->client->get(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/bank-accounts/{$input->bankAccountId}/offramp-wallets/{$input->id}"
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                OfframpWallet::fromArray($response->data)
            );
        }

        return $response;
    }
}
