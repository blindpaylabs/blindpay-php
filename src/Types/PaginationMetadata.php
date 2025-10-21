<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

readonly class PaginationMetadata
{
    public function __construct(
        public bool $hasMore,
        public int $nextPage,
        public int $prevPage
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            hasMore: $data['has_more'] ?? false,
            nextPage: $data['next_page'] ?? 0,
            prevPage: $data['prev_page'] ?? 0
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

