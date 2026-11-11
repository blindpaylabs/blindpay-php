<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\VirtualAccounts;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BankingPartner;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\RequestOptions;
use BlindPay\SDK\Types\SoleProprietorDocType;
use BlindPay\SDK\Types\StablecoinToken;

readonly class VirtualAccountUsDetails
{
    public function __construct(
        public ?VirtualAccountUsAch $ach,
        public VirtualAccountUsWire $wire,
        public ?VirtualAccountUsRtp $rtp,
        public ?string $swiftBicCode,
        public ?string $accountType,
        public ?VirtualAccountBeneficiary $beneficiary,
        public ?VirtualAccountReceivingBank $receivingBank,
        public ?string $swiftAccountNumber = null,
        public ?VirtualAccountSwiftReceivingBank $swiftReceivingBank = null,
        public ?array $swiftIntermediaryBank = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            ach: isset($data['ach']) ? VirtualAccountUsAch::fromArray($data['ach']) : null,
            wire: VirtualAccountUsWire::fromArray($data['wire']),
            rtp: isset($data['rtp']) ? VirtualAccountUsRtp::fromArray($data['rtp']) : null,
            swiftBicCode: $data['swift_bic_code'] ?? null,
            accountType: $data['account_type'] ?? null,
            beneficiary: isset($data['beneficiary']) ? VirtualAccountBeneficiary::fromArray($data['beneficiary']) : null,
            receivingBank: isset($data['receiving_bank']) ? VirtualAccountReceivingBank::fromArray($data['receiving_bank']) : null,
            swiftAccountNumber: $data['swift_account_number'] ?? null,
            swiftReceivingBank: isset($data['swift_receiving_bank']) ? VirtualAccountSwiftReceivingBank::fromArray($data['swift_receiving_bank']) : null,
            swiftIntermediaryBank: $data['swift_intermediary_bank'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [
            'ach' => $this->ach?->toArray(),
            'wire' => $this->wire->toArray(),
            'rtp' => $this->rtp?->toArray(),
            'swift_bic_code' => $this->swiftBicCode,
            'account_type' => $this->accountType,
            'beneficiary' => $this->beneficiary?->toArray(),
            'receiving_bank' => $this->receivingBank?->toArray(),
        ];

        if ($this->swiftAccountNumber !== null) {
            $data['swift_account_number'] = $this->swiftAccountNumber;
        }

        if ($this->swiftReceivingBank !== null) {
            $data['swift_receiving_bank'] = $this->swiftReceivingBank->toArray();
        }

        if ($this->swiftIntermediaryBank !== null) {
            $data['swift_intermediary_bank'] = $this->swiftIntermediaryBank;
        }

        return $data;
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
        public ?string $addressLine2
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            addressLine1: $data['address_line_1'],
            addressLine2: $data['address_line_2'] ?? null
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

readonly class VirtualAccountSwiftReceivingBank
{
    public function __construct(
        public ?string $name,
        public ?string $addressLine1,
        public ?string $addressLine2
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null
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
        /** US rails; null for accounts on other rails (e.g. Colombian or Mexican accounts). */
        public ?VirtualAccountUsDetails $us,
        public StablecoinToken|string $token,
        public ?string $blockchainWalletId,
        public BankingPartner|string|null $bankingPartner = null,
        public ?string $kycStatus = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            us: isset($data['us']) ? VirtualAccountUsDetails::fromArray($data['us']) : null,
            token: Decode::enum(StablecoinToken::class, $data['token']),
            blockchainWalletId: $data['blockchain_wallet_id'] ?? null,
            bankingPartner: Decode::enum(BankingPartner::class, $data['banking_partner'] ?? null),
            kycStatus: $data['kyc_status'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'us' => $this->us?->toArray(),
            'token' => Decode::wire($this->token),
            'blockchain_wallet_id' => $this->blockchainWalletId,
        ];

        if ($this->bankingPartner !== null) {
            $data['banking_partner'] = Decode::wire($this->bankingPartner);
        }

        if ($this->kycStatus !== null) {
            $data['kyc_status'] = $this->kycStatus;
        }

        return $data;
    }
}

readonly class CreateVirtualAccountInput
{
    /**
     * @param  string|null  $breBGender  `male` or `female`; required by the bre_b banking partner
     */
    public function __construct(
        public string $customerId,
        public string $blockchainWalletId,
        public StablecoinToken $token,
        public BankingPartner $bankingPartner,
        public ?SoleProprietorDocType $soleProprietorDocType = null,
        public ?string $soleProprietorDocFile = null,
        public ?string $partnerFeeId = null,
        public ?string $breBGender = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'banking_partner' => $this->bankingPartner->value,
            'blockchain_wallet_id' => $this->blockchainWalletId,
            'token' => $this->token->value,
            'sole_proprietor_doc_type' => $this->soleProprietorDocType?->value,
            'sole_proprietor_doc_file' => $this->soleProprietorDocFile,
            'partner_fee_id' => $this->partnerFeeId,
            'bre_b' => $this->breBGender === null ? null : ['gender' => $this->breBGender],
        ], static fn ($value) => $value !== null);
    }
}

readonly class UpdateVirtualAccountInput
{
    public function __construct(
        public string $customerId,
        public string $virtualAccountId,
        public string $blockchainWalletId,
        public StablecoinToken $token,
        public ?string $partnerFeeId = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'blockchain_wallet_id' => $this->blockchainWalletId,
            'token' => $this->token->value,
            'partner_fee_id' => $this->partnerFeeId,
        ], static fn ($value) => $value !== null);
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
    public function create(
        CreateVirtualAccountInput $input,
        ?RequestOptions $options = null
    ): BlindPayApiResponse {
        $response = $this->client->post(
            Path::of('instances/%s/customers/%s/virtual-accounts', $this->instanceId, $input->customerId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => VirtualAccount::fromArray($data));
    }

    /**
     * Update a virtual account
     *
     * @return BlindPayApiResponse<null>
     */
    public function update(UpdateVirtualAccountInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($input->virtualAccountId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Virtual Account ID cannot be empty')
            );
        }

        return $this->client->put(
            Path::of('instances/%s/customers/%s/virtual-accounts/%s', $this->instanceId, $input->customerId, $input->virtualAccountId),
            $input->toArray(),
            $options
        );
    }

    /**
     * Get a virtual account
     *
     * @return BlindPayApiResponse<VirtualAccount>
     */
    public function get(string $customerId, string $virtualAccountId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($customerId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Customer ID cannot be empty')
            );
        }

        if (empty($virtualAccountId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Virtual Account ID cannot be empty')
            );
        }

        $response = $this->client->get(
            Path::of('instances/%s/customers/%s/virtual-accounts/%s', $this->instanceId, $customerId, $virtualAccountId),
            $options
        );

        return $response->map(fn (array $data) => VirtualAccount::fromArray($data));
    }

    /**
     * List virtual accounts for a customer
     *
     * @return BlindPayApiResponse<VirtualAccount[]>
     */
    public function list(string $customerId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($customerId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Customer ID cannot be empty')
            );
        }

        $response = $this->client->get(
            Path::of('instances/%s/customers/%s/virtual-accounts', $this->instanceId, $customerId),
            $options
        );

        return $response->map(function (array $data) {
            $items = array_map(
                fn (array $item) => VirtualAccount::fromArray($item),
                $data
            );

            return $items;
        });
    }
}
