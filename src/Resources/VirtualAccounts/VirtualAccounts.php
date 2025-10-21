<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\VirtualAccounts;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\StablecoinToken;

readonly class VirtualAccountUsDetails
{
    public function __construct(
        public VirtualAccountUsAch $ach,
        public VirtualAccountUsWire $wire,
        public VirtualAccountUsRtp $rtp,
        public string $swiftBicCode,
        public string $accountType,
        public VirtualAccountBeneficiary $beneficiary,
        public VirtualAccountReceivingBank $receivingBank
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            ach: VirtualAccountUsAch::fromArray($data['ach']),
            wire: VirtualAccountUsWire::fromArray($data['wire']),
            rtp: VirtualAccountUsRtp::fromArray($data['rtp']),
            swiftBicCode: $data['swift_bic_code'],
            accountType: $data['account_type'],
            beneficiary: VirtualAccountBeneficiary::fromArray($data['beneficiary']),
            receivingBank: VirtualAccountReceivingBank::fromArray($data['receiving_bank'])
        );
    }

    public function toArray(): array
    {
        return [
            'ach' => $this->ach->toArray(),
            'wire' => $this->wire->toArray(),
            'rtp' => $this->rtp->toArray(),
            'swift_bic_code' => $this->swiftBicCode,
            'account_type' => $this->accountType,
            'beneficiary' => $this->beneficiary->toArray(),
            'receiving_bank' => $this->receivingBank->toArray(),
        ];
    }
}

readonly class VirtualAccountUsAch
{
    public function __construct(
        public string $routingNumber,
        public string $accountNumber
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            routingNumber: $data['routing_number'],
            accountNumber: $data['account_number']
        );
    }

    public function toArray(): array
    {
        return [
            'routing_number' => $this->routingNumber,
            'account_number' => $this->accountNumber,
        ];
    }
}

readonly class VirtualAccountUsWire
{
    public function __construct(
        public string $routingNumber,
        public string $accountNumber
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            routingNumber: $data['routing_number'],
            accountNumber: $data['account_number']
        );
    }

    public function toArray(): array
    {
        return [
            'routing_number' => $this->routingNumber,
            'account_number' => $this->accountNumber,
        ];
    }
}

readonly class VirtualAccountUsRtp
{
    public function __construct(
        public string $routingNumber,
        public string $accountNumber
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            routingNumber: $data['routing_number'],
            accountNumber: $data['account_number']
        );
    }

    public function toArray(): array
    {
        return [
            'routing_number' => $this->routingNumber,
            'account_number' => $this->accountNumber,
        ];
    }
}

readonly class VirtualAccountBeneficiary
{
    public function __construct(
        public string $name,
        public string $addressLine1,
        public string $addressLine2
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            addressLine1: $data['address_line_1'],
            addressLine2: $data['address_line_2']
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
        ];
    }
}

readonly class VirtualAccountReceivingBank
{
    public function __construct(
        public string $name,
        public string $addressLine1,
        public string $addressLine2
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            addressLine1: $data['address_line_1'],
            addressLine2: $data['address_line_2']
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
        ];
    }
}

readonly class VirtualAccount
{
    public function __construct(
        public string $id,
        public VirtualAccountUsDetails $us,
        public StablecoinToken $token,
        public string $blockchainWalletId
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            us: VirtualAccountUsDetails::fromArray($data['us']),
            token: StablecoinToken::from($data['token']),
            blockchainWalletId: $data['blockchain_wallet_id']
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'us' => $this->us->toArray(),
            'token' => $this->token->value,
            'blockchain_wallet_id' => $this->blockchainWalletId,
        ];
    }
}

readonly class CreateVirtualAccountInput
{
    public function __construct(
        public string $receiverId,
        public string $blockchainWalletId,
        public StablecoinToken $token
    ) {}

    public function toArray(): array
    {
        return [
            'blockchain_wallet_id' => $this->blockchainWalletId,
            'token' => $this->token->value,
        ];
    }
}

readonly class UpdateVirtualAccountInput
{
    public function __construct(
        public string $receiverId,
        public string $blockchainWalletId,
        public StablecoinToken $token
    ) {}

    public function toArray(): array
    {
        return [
            'blockchain_wallet_id' => $this->blockchainWalletId,
            'token' => $this->token->value,
        ];
    }
}

class VirtualAccounts
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /**
     * Create a virtual account
     *
     * @return BlindPayApiResponse<VirtualAccount>
     */
    public function create(CreateVirtualAccountInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/virtual-accounts",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                VirtualAccount::fromArray($response->data)
            );
        }

        return $response;
    }

    /**
     * Update a virtual account
     *
     * @return BlindPayApiResponse<null>
     */
    public function update(UpdateVirtualAccountInput $input): BlindPayApiResponse
    {
        return $this->client->put(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/virtual-accounts",
            $input->toArray()
        );
    }

    /**
     * Get a virtual account
     *
     * @return BlindPayApiResponse<VirtualAccount>
     */
    public function get(string $receiverId): BlindPayApiResponse
    {
        if (empty($receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        $response = $this->client->get(
            "instances/{$this->instanceId}/receivers/{$receiverId}/virtual-accounts"
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                VirtualAccount::fromArray($response->data)
            );
        }

        return $response;
    }
}
