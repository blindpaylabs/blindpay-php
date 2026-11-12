<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

/** What the API documents about an error code (see ErrorCode::info()). */
final readonly class ErrorCodeInfo
{
    /**
     * @param  int  $status  the HTTP status the code typically comes with (an endpoint may differ)
     * @param  RetryClass  $retry  whether the request can be retried as is, needs a fix first, or not at all
     */
    public function __construct(
        public int $status,
        public RetryClass $retry,
    ) {}
}
