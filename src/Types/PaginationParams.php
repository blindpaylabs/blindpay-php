<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

readonly class PaginationParams
{
    public function __construct(
        public ?int $limit = null,
        public ?int $offset = null,
        public ?string $startingAfter = null,
        public ?string $endingBefore = null
    ) {
        if ($this->limit !== null) {
            $validLimits = [10, 50, 100, 200, 1000];
            if (! in_array($this->limit, $validLimits, true)) {
                throw new \InvalidArgumentException(
                    'Invalid limit. Must be one of: '.implode(', ', $validLimits)
                );
            }
        }

        if ($this->offset !== null) {
            $validOffsets = [0, 10, 50, 100, 200, 1000];
            if (! in_array($this->offset, $validOffsets, true)) {
                throw new \InvalidArgumentException(
                    'Invalid offset. Must be one of: '.implode(', ', $validOffsets)
                );
            }
        }
    }

    public function toArray(): array
    {
        $params = [];

        if ($this->limit !== null) {
            $params['limit'] = (string) $this->limit;
        }

        if ($this->offset !== null) {
            $params['offset'] = (string) $this->offset;
        }

        if ($this->startingAfter !== null) {
            $params['starting_after'] = $this->startingAfter;
        }

        if ($this->endingBefore !== null) {
            $params['ending_before'] = $this->endingBefore;
        }

        return $params;
    }

    public function toQueryString(): string
    {
        $params = $this->toArray();

        if (empty($params)) {
            return '';
        }

        return '?'.http_build_query($params);
    }
}

