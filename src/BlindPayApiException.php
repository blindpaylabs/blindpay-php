<?php

declare(strict_types=1);

namespace BlindPay\SDK;

use BlindPay\SDK\Types\ErrorResponse;

/**
 * Thrown where a BlindPayApiResponse cannot be returned: by getDataOrFail() and by the
 * auto-paging generators (listAutoPaging) when a page request fails. Carries the ErrorResponse.
 */
final class BlindPayApiException extends BlindPayException
{
    public function __construct(public readonly ErrorResponse $error)
    {
        parent::__construct($error->message, $error->code, [], $error->status ?? 0);
    }
}
