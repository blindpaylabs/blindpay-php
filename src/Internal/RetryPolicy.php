<?php

declare(strict_types=1);

namespace BlindPay\SDK\Internal;

/**
 * The retry decision table shared by every BlindPay SDK, evaluated top to
 * bottom per failed attempt. The attempt budget (max retries) is checked by the caller.
 *
 * Mutations (POST/PUT/PATCH/DELETE) are retried only where their Idempotency-Key fully protects
 * them: no response, 429 (rejected before the handler ran) and 409 idempotency_key_in_flight.
 * They are never retried on 5xx, because the API releases the key on 5xx and a resend would run
 * the handler again; the error is flagged retryable instead and carries the key.
 *
 * @internal
 */
final class RetryPolicy
{
    /** 5xx codes the spec's error table classifies `terminal`. */
    public const TERMINAL_5XX_CODES = ['PAYINS_CARD_PAYMENT_PENDING'];

    private const RETRYABLE_GET_STATUSES = [408, 500, 502, 503, 504];

    private const MAX_RETRY_AFTER = 60;

    public static function isMutation(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Rows 2-4: no response was received.
     *
     * @param  bool  $nothingSent  the failure happened before any byte was sent (DNS / connect)
     */
    public static function shouldRetryNetworkError(string $method, bool $hasIdempotencyKey, bool $multipart, bool $nothingSent): bool
    {
        if ($multipart) {
            return $nothingSent;
        }

        return ! self::isMutation($method) || $hasIdempotencyKey;
    }

    /** Rows 2-10: a non-2xx response was received. */
    public static function shouldRetryResponse(string $method, bool $hasIdempotencyKey, bool $multipart, int $status, ?string $message, ?string $code): bool
    {
        $mutation = self::isMutation($method);
        if ($multipart || ($mutation && ! $hasIdempotencyKey)) {
            return false;
        }
        if (($status === 409 && $message === 'idempotency_key_in_flight') || $status === 429) {
            return true;
        }
        if ($mutation) {
            return false;
        }
        if ($status >= 500 && in_array($code, self::TERMINAL_5XX_CODES, true)) {
            return false;
        }

        return in_array($status, self::RETRYABLE_GET_STATUSES, true);
    }

    /**
     * Whether the caller may resend the same request: what the SDK would retry itself, plus a
     * mutation 5xx that carried an idempotency key, which only the caller may resend (with that
     * key, after checking state). A mutation 408 is not retryable: the handler may have run.
     */
    public static function isRetryableStatus(string $method, bool $hasIdempotencyKey, int $status, ?string $message, ?string $code): bool
    {
        $mutation = self::isMutation($method);
        if ($status === 429 || ($status === 409 && $message === 'idempotency_key_in_flight')) {
            return true;
        }
        if ($status === 408) {
            return ! $mutation;
        }

        return $status >= 500 && ! in_array($code, self::TERMINAL_5XX_CODES, true) && (! $mutation || $hasIdempotencyKey);
    }

    /**
     * Seconds to wait before retry number $attempt + 1: the `Retry-After` header when it is an
     * integer of at most 60 seconds, else min(8, 0.5 * 2^attempt) * a jitter factor in [0.5, 1].
     *
     * @param  int  $attempt  0 for the first retry
     * @param  callable(): float  $random  returns a value in [0, 1]
     */
    public static function delay(int $attempt, ?string $retryAfter, callable $random): float
    {
        if ($retryAfter !== null && preg_match('/^\d{1,3}$/', trim($retryAfter)) === 1 && (int) trim($retryAfter) <= self::MAX_RETRY_AFTER) {
            return (float) (int) trim($retryAfter);
        }

        return min(8.0, 0.5 * (2 ** $attempt)) * (0.5 + 0.5 * $random());
    }
}
