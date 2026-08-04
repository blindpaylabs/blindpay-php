<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

readonly class PaginationMetadata
{
    public function __construct(
        public bool $hasMore,
        public ?string $nextPage = null,
        public ?string $prevPage = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            hasMore: $data['has_more'] ?? false,
            nextPage: $data['next_page'] ?? null,
            prevPage: $data['prev_page'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'has_more' => $this->hasMore,
            'next_page' => $this->nextPage,
            'prev_page' => $this->prevPage,
        ];
    }
}
