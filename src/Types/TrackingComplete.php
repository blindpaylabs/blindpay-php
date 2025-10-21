<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

use DateTimeImmutable;

readonly class TrackingComplete extends BaseTracking
{
    public function __construct(
        string $step,
        public string $status,
        public string $transactionHash,
        ?DateTimeImmutable $completedAt
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
            status: $data['status'],
            transactionHash: $data['transaction_hash'],
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
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}
