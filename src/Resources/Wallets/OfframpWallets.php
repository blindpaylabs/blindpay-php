<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Wallets;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\RequestOptions;
use DateTimeImmutable;

readonly class OfframpWallet
{
    public function __construct(
        public string $id,
        public ?string $externalId,
        public string $instanceId,
        public string $customerId,
        public string $bankAccountId,
        public string $network,
        public string $address,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public ?string $circleWalletId = null,
        public ?string $partnerFeeId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            externalId: $data['external_id'] ?? null,
            instanceId: $data['instance_id'],
            customerId: $data['customer_id'],
            bankAccountId: $data['bank_account_id'],
            network: $data['network'],
            address: $data['address'],
            createdAt: isset($data['created_at'])
                ? new DateTimeImmutable($data['created_at'])
                : null,
            updatedAt: isset($data['updated_at'])
                ? new DateTimeImmutable($data['updated_at'])
                : null,
            circleWalletId: $data['circle_wallet_id'] ?? null,
            partnerFeeId: $data['partner_fee_id'] ?? null,
        );
    }
}

readonly class ListOfframpWalletsInput
{
    public function __construct(
        public string $customerId,
        public string $bankAccountId
    ) {}
}

readonly class CreateOfframpWalletInput
{
    /**
     * @param  string|null  $externalId  your own reference for the wallet
     * @param  string  $network  tron, solana, arbitrum, base, ethereum, polygon, ... (the API validates)
     * @param  string|null  $partnerFeeId  a partner fee charged on payouts from this wallet
     */
    public function __construct(
        public string $customerId,
        public string $bankAccountId,
        public ?string $externalId,
        public string $network,
        public ?string $partnerFeeId = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'external_id' => $this->externalId,
            'network' => $this->network,
            'partner_fee_id' => $this->partnerFeeId,
        ], static fn ($value) => $value !== null);
    }
}

readonly class UpdateOfframpWalletInput
{
    /**
     * @param  string|null  $partnerFeeId  the partner fee to charge on payouts from this wallet;
     *                                     null removes the current one
     */
    public function __construct(
        public string $customerId,
        public string $bankAccountId,
        public string $id,
        public ?string $partnerFeeId,
    ) {}

    public function toArray(): array
    {
        return ['partner_fee_id' => $this->partnerFeeId];
    }
}

readonly class UpdateOfframpWalletResponse
{
    public function __construct(
        public bool $success,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(success: (bool) $data['success']);
    }
}

readonly class CreateOfframpWalletResponse
{
    public function __construct(
        public string $id,
        public ?string $externalId,
        public string $network,
        public string $address,
        public ?string $circleWalletId = null,
        public ?string $partnerFeeId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            externalId: $data['external_id'] ?? null,
            network: $data['network'],
            address: $data['address'],
            circleWalletId: $data['circle_wallet_id'] ?? null,
            partnerFeeId: $data['partner_fee_id'] ?? null,
        );
    }
}

readonly class GetOfframpWalletInput
{
    public function __construct(
        public string $customerId,
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
    public function list(
        ListOfframpWalletsInput $input,
        ?RequestOptions $options = null
    ): BlindPayApiResponse {
        $response = $this->client->get(
            Path::of('instances/%s/customers/%s/bank-accounts/%s/offramp-wallets', $this->instanceId, $input->customerId, $input->bankAccountId),
            $options
        );

        return $response->map(function (array $data) {
            $wallets = array_map(
                fn (array $item) => OfframpWallet::fromArray($item),
                $data
            );

            return $wallets;
        });
    }

    /**
     * Create offramp wallet
     *
     * @return BlindPayApiResponse<CreateOfframpWalletResponse>
     */
    public function create(CreateOfframpWalletInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/customers/%s/bank-accounts/%s/offramp-wallets', $this->instanceId, $input->customerId, $input->bankAccountId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => CreateOfframpWalletResponse::fromArray($data));
    }

    /**
     * Get offramp wallet
     *
     * @return BlindPayApiResponse<OfframpWallet>
     */
    public function get(GetOfframpWalletInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($input->id)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Offramp wallet ID cannot be empty')
            );
        }

        $response = $this->client->get(
            Path::of('instances/%s/customers/%s/bank-accounts/%s/offramp-wallets/%s', $this->instanceId, $input->customerId, $input->bankAccountId, $input->id),
            $options
        );

        return $response->map(fn (array $data) => OfframpWallet::fromArray($data));
    }

    /**
     * Set or remove (null) the partner fee charged on payouts from an offramp wallet.
     *
     * @return BlindPayApiResponse<UpdateOfframpWalletResponse>
     */
    public function update(UpdateOfframpWalletInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($input->id === '') {
            return BlindPayApiResponse::error(ErrorResponse::validation('Offramp wallet ID cannot be empty'));
        }

        return $this->client->put(
            Path::of('instances/%s/customers/%s/bank-accounts/%s/offramp-wallets/%s', $this->instanceId, $input->customerId, $input->bankAccountId, $input->id),
            $input->toArray(),
            $options
        )->map(fn (array $data) => UpdateOfframpWalletResponse::fromArray($data));
    }
}
