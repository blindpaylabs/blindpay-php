<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

use DateTimeImmutable;

readonly class TrackingComplete extends BaseTracking
{
    public function __construct(
        string $step,
        public string $status,
        public ?string $transactionHash,
        public ?string $providerTransactionId = null,
        public ?string $refundReason = null,
        public ?string $gasFee = null,
        public ?string $errorMessage = null,
        ?DateTimeImmutable $completedAt = null
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
            status: $data['status'],
            transactionHash: $data['transaction_hash'] ?? null,
            providerTransactionId: $data['provider_transaction_id'] ?? null,
            refundReason: $data['refund_reason'] ?? null,
            gasFee: $data['gas_fee'] ?? null,
            errorMessage: $data['error_message'] ?? null,
            completedAt: isset($data['completed_at'])
                ? new DateTimeImmutable($data['completed_at'])
                : null
        );
    }

    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'status' => $this->status,
            'transaction_hash' => $this->transactionHash,
            'provider_transaction_id' => $this->providerTransactionId,
            'refund_reason' => $this->refundReason,
            'gas_fee' => $this->gasFee,
            'error_message' => $this->errorMessage,
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}
