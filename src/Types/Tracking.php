<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

use DateTimeImmutable;

abstract readonly class BaseTracking
{
    public function __construct(
        public string $step,
        public ?DateTimeImmutable $completedAt
    ) {}

    abstract public function toArray(): array;
}

readonly class TrackingTransaction extends BaseTracking
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

readonly class TrackingLiquidity extends BaseTracking
{
    public function __construct(
        string $step,
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
            'provider_transaction_id' => $this->providerTransactionId,
            'provider_status' => $this->providerStatus,
            'estimated_time_of_arrival' => $this->estimatedTimeOfArrival,
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}

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

readonly class TrackingPartnerFee extends BaseTracking
{
    public function __construct(
        string $step,
        public string $transactionHash,
        ?DateTimeImmutable $completedAt
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
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
            'transaction_hash' => $this->transactionHash,
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}
