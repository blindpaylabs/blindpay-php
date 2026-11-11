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
        ?DateTimeImmutable $completedAt = null,
        public ?int $returnedAmount = null,
        public ?string $refundReviewReason = null,
        public ?string $refundReviewDetectedAt = null,
        public ?string $refundReviewActionedAt = null,
        public ?string $reviewSource = null,
        public ?array $reviewSources = null,
        public ?array $reviewContext = null,
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
                : null,
            returnedAmount: isset($data['returned_amount']) ? (int) $data['returned_amount'] : null,
            refundReviewReason: $data['refund_review_reason'] ?? null,
            refundReviewDetectedAt: $data['refund_review_detected_at'] ?? null,
            refundReviewActionedAt: $data['refund_review_actioned_at'] ?? null,
            reviewSource: $data['review_source'] ?? null,
            reviewSources: $data['review_sources'] ?? null,
            reviewContext: $data['review_context'] ?? null,
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
