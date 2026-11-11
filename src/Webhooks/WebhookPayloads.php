<?php

declare(strict_types=1);

namespace BlindPay\SDK\Webhooks;

use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Resources\Customers\KycType;
use BlindPay\SDK\Types\AccountClass;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\StablecoinToken;
use DateTimeImmutable;

// Payloads of the webhook events that have no matching API resource type. The others reuse the
// resource classes (see WebhookEvent::payloadClass()).

/** `customer.delete` */
readonly class CustomerDeletedPayload
{
    public function __construct(
        public string $id,
        public string $customerId,
        public string $instanceId,
        public AccountClass|string $type,
        public KycType|string $kycType,
        /** verifying, approved, rejected, deprecated, pending_review, awaiting_contract, compliance_request, approved_rfi, ... */
        public string $kycStatus,
        public DateTimeImmutable $deletedAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            customerId: $data['customer_id'],
            instanceId: $data['instance_id'],
            type: Decode::enum(AccountClass::class, $data['type']),
            kycType: Decode::enum(KycType::class, $data['kyc_type']),
            kycStatus: $data['kyc_status'],
            deletedAt: new DateTimeImmutable($data['deleted_at']),
        );
    }
}

/** `tos.accept` */
readonly class TosAcceptedPayload
{
    public function __construct(
        public string $tosId,
        /** the `idempotency_key` the ToS flow was initiated with */
        public string $idempotencyKey,
        public ?string $customerId = null,
        public ?string $version = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tosId: $data['tos_id'],
            idempotencyKey: $data['idempotency_key'],
            customerId: $data['customer_id'] ?? null,
            version: $data['version'] ?? null,
        );
    }
}

/** The token of a `wallet.inbound` deposit. `amount` is in whole tokens (10.5 = 10.5 USDC). */
readonly class WalletInboundToken
{
    public function __construct(
        public string $id,
        public string $address,
        public StablecoinToken|string $symbol,
        public float $amount,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            address: $data['address'],
            symbol: Decode::enum(StablecoinToken::class, $data['symbol']),
            amount: (float) $data['amount'],
        );
    }
}

/** `wallet.inbound`: a deposit arrived in a managed wallet. */
readonly class WalletInboundPayload
{
    public function __construct(
        public string $id,
        public string $address,
        public Network|string $network,
        public WalletInboundToken $token,
        public string $depositId,
        public string $transactionHash,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            address: $data['address'],
            network: Decode::enum(Network::class, $data['network']),
            token: WalletInboundToken::fromArray($data['token']),
            depositId: $data['deposit_id'],
            transactionHash: $data['transaction_hash'],
        );
    }
}
