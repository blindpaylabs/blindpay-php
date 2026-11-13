<?php

declare(strict_types=1);

namespace BlindPay\SDK\Internal;

use BlindPay\SDK\BlindPayApiException;
use BlindPay\SDK\BlindPayException;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ListResponse;
use Generator;

/** @internal */
final class AutoPager
{
    /** Page size the pager asks for when the caller sets none (valid for every list endpoint). */
    public const DEFAULT_LIMIT = '100';

    /**
     * BlindPayException::getErrorCode() when the API hands back a cursor this pager already sent:
     * following it would loop forever. Match on this constant, not on the string.
     */
    public const CURSOR_REPEATED = 'PAGINATION_CURSOR_REPEATED';

    /**
     * Yield every row of a cursor-paginated list, one page request at a time (each through the
     * normal retry path). Sends `limit` so the API returns the `{data, pagination}` envelope, and
     * follows `pagination.next_page` as `starting_after` while `has_more` is true. Forward only.
     *
     * @template T
     *
     * @param  array<string, mixed>  $query  the caller's list parameters (filters, limit, starting_after)
     * @param  callable(array<string, mixed>): BlindPayApiResponse<ListResponse<T>>  $fetchPage
     * @return Generator<int, T>
     *
     * @throws BlindPayException when ending_before is set (backward paging is not supported), or
     *                           when the API returns a next_page cursor this pager already sent (a
     *                           repeat or a cycle; error code AutoPager::CURSOR_REPEATED)
     * @throws BlindPayApiException when a page request fails
     */
    public static function iterate(array $query, callable $fetchPage): Generator
    {
        if (($query['ending_before'] ?? null) !== null) {
            throw BlindPayException::validation('listAutoPaging() pages forward only; ending_before is not supported.');
        }
        $query['limit'] ??= self::DEFAULT_LIMIT;
        // Every cursor sent so far (the caller's starting_after included).
        $sent = isset($query['starting_after']) ? [(string) $query['starting_after'] => true] : [];

        while (true) {
            $response = $fetchPage($query);
            if ($response->error !== null) {
                throw new BlindPayApiException($response->error);
            }
            /** @var ListResponse<T> $page */
            $page = $response->data;
            foreach ($page->data as $item) {
                yield $item;
            }
            $next = $page->pagination?->nextPage;
            if (! $page->hasMore() || $next === null) {
                return;
            }
            if (isset($sent[$next])) {
                // A cursor this pager already sent (the same one twice, or a cycle A -> B -> A):
                // following it would loop forever, and stopping quietly would hide the rows after it.
                throw BlindPayException::api("listAutoPaging() stopped: the API returned the cursor {$next} again, so paging would repeat pages it already read.", self::CURSOR_REPEATED);
            }
            $sent[$next] = true;
            $query['starting_after'] = $next;
        }
    }
}
