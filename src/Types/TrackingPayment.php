<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

use DateTimeImmutable;

readonly class TrackingPayment extends BaseTracking
{
    public function __construct(
        string $step,
        public string $providerName,
        public string $providerTransactionId,
        public string $providerStatus,
        public string $estimatedTimeOfArrival,
        ?DateTimeImmutable $completedAt
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
            providerName: $data['provider_name'],
            providerTransactionId: $data['provider_transaction_id'],
            providerStatus: $data['provider_status'],
            estimatedTimeOfArrival: $data['estimated_time_of_arrival'],
            completedAt: isset($data['completed_at'])
                ? new DateTimeImmutable($data['completed_at'])
                : null
        );
    }

    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'provider_name' => $this->providerName,
            'provider_transaction_id' => $this->providerTransactionId,
            'provider_status' => $this->providerStatus,
            'estimated_time_of_arrival' => $this->estimatedTimeOfArrival,
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}

