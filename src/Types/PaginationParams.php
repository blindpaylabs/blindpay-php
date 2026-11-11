<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

use BlindPay\SDK\Internal\Query;

/**
 * Cursor pagination for list endpoints. Passing any of `limit`, `startingAfter` or `endingBefore`
 * makes the API return `{data, pagination}` (see ListResponse); pass `pagination->nextPage` back as
 * `startingAfter` for the next page, or use the resource's listAutoPaging() generator.
 */
readonly class PaginationParams
{
    /**
     * @param  int|null  $limit  page size; the API accepts 10, 50, 100, 200, 500 or 1000 (wallet
     *                           deposits: 5, 10, 50, 100, 200). Not validated client-side.
     * @param  int|null  $offset  deprecated: ignored by the API; use $startingAfter
     */
    public function __construct(
        public ?int $limit = null,
        public ?int $offset = null,
        public ?string $startingAfter = null,
        public ?string $endingBefore = null
    ) {}

    /** @return array<string, string> */
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
        return Query::build($this->toArray());
    }
}
