<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

use DateTimeImmutable;

readonly class PayinTrackingPayment extends BaseTracking
{
    public function __construct(
        string $step,
        public ?string $providerName = null,
        public ?array $reviewContexts = null,
        public ?array $reviewSources = null,
        ?DateTimeImmutable $completedAt = null
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
            providerName: $data['provider_name'] ?? null,
            completedAt: isset($data['completed_at']),
            reviewContexts: $data['review_contexts'] ?? null,
            reviewSources: $data['review_sources'] ?? null
                ? new DateTimeImmutable($data['completed_at'])
                : null
        );
    }

    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'provider_name' => $this->providerName,
            'completed_at' => $this->completedAt?->format('c'),
            'review_contexts' => $this->reviewContexts,
            'review_sources' => $this->reviewSources,
        ];
    }
}
