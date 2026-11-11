<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

/**
 * Per-request options, accepted as the last argument of every resource method.
 *
 *     $blindpay->payouts->createEvm($input, new RequestOptions(idempotencyKey: $orderId));
 *
 * Options never change the shared client; each call sees only its own.
 */
final readonly class RequestOptions
{
    /**
     * @param  string|null  $idempotencyKey  sent as the `Idempotency-Key` header on POST/PUT/PATCH/DELETE
     *                                       JSON requests. 1-255 characters; an empty or longer key is
     *                                       rejected before anything is sent. When omitted and retries
     *                                       are enabled, the SDK generates a UUIDv4 key per call.
     * @param  float|null  $timeout  total seconds allowed per attempt; defaults to the client's timeout
     * @param  array<string, string>  $headers  extra request headers. They cannot override the SDK's own
     *                                          headers (Authorization, User-Agent, Idempotency-Key, ...).
     */
    public function __construct(
        public ?string $idempotencyKey = null,
        public ?float $timeout = null,
        public array $headers = [],
    ) {}
}
