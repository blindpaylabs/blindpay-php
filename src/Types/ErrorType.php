<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

/** What produced an ErrorResponse. */
enum ErrorType: string
{
    /** The API answered with a non-2xx status. */
    case API = 'api';

    /** No response was received: connection, DNS or TLS failure, or the per-attempt timeout. */
    case NETWORK = 'network';

    /** The SDK rejected the call before sending it (e.g. an empty ID or idempotency key). */
    case VALIDATION = 'validation';

    /** The API answered 2xx but the body could not be decoded into the SDK's types. */
    case DECODE = 'decode';
}
