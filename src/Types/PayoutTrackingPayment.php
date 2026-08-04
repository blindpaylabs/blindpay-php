<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

use DateTimeImmutable;

readonly class PayoutTrackingPayment extends BaseTracking
{
    public function __construct(
        string $step,
        public ?string $providerName = null,
        public ?string $providerTransactionId = null,
        public ?string $providerStatus = null,
        public ?string $estimatedTimeOfArrival = null,
        ?DateTimeImmutable $completedAt = null
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
            providerName: $data['provider_name'] ?? null,
            providerTransactionId: $data['provider_transaction_id'] ?? null,
            providerStatus: $data['provider_status'] ?? null,
            estimatedTimeOfArrival: $data['estimated_time_of_arrival'] ?? null,
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
