<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

/**
 * How to react to an API error code, as the API documents it per code.
 *
 * This is guidance for your own handling. The SDK's automatic retries follow their own rules
 * (see BlindPay::__construct $maxRetries) and do not read it.
 */
enum RetryClass: string
{
    /** The same request can be retried as is. */
    case RETRYABLE = 'retryable';

    /** Do not retry: the request cannot succeed. */
    case TERMINAL = 'terminal';

    /** The request succeeds only after you fix something (fund a balance, correct bank details, ...). */
    case ACTIONABLE = 'actionable';

    /** No code, or a code this SDK version does not know. */
    case UNKNOWN = 'unknown';
}
