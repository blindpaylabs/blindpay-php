<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * One page of a list. The API returns a bare array when no pagination parameter is sent, and
 * `{data, pagination}` when `limit`, `starting_after` or `ending_before` is; both decode here, and
 * `pagination` is null for the bare form. Iterate it directly or read `data`.
 *
 * For every row of a large list, use the resource's listAutoPaging() generator instead.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
readonly class ListResponse implements Countable, IteratorAggregate
{
    /**
     * @param  list<T>  $data
     */
    final public function __construct(
        public array $data,
        public ?PaginationMetadata $pagination = null,
    ) {}

    /**
     * @param  array<mixed>  $body  the decoded response body, in either shape
     * @param  callable(array): T  $item  decodes one row
     */
    public static function decode(array $body, callable $item): static
    {
        $isEnvelope = ! array_is_list($body) && array_key_exists('data', $body) && is_array($body['data']);

        return new static(
            data: array_map($item, $isEnvelope ? array_values($body['data']) : $body),
            pagination: $isEnvelope && is_array($body['pagination'] ?? null) ? PaginationMetadata::fromArray($body['pagination']) : null,
        );
    }

    /** Whether another page follows this one (false for the bare-array form). */
    public function hasMore(): bool
    {
        return $this->pagination !== null && $this->pagination->hasMore;
    }

    public function count(): int
    {
        return count($this->data);
    }

    /** @return Traversable<int, T> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }
}
