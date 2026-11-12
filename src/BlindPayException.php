<?php

declare(strict_types=1);

namespace BlindPay\SDK;

use BlindPay\SDK\Internal\BlindPayException as InternalBlindPayException;
use Exception;

/**
 * Base class of every exception the SDK throws: configuration errors from the client and
 * RequestOptions constructors, BlindPayApiException (getDataOrFail(), auto-paging) and
 * Webhooks\WebhookException. Catch this one.
 *
 *     try {
 *         $payout = $blindpay->payouts->get($id)->getDataOrFail();
 *     } catch (BlindPayException $e) {
 *         // ...
 *     }
 *
 * It extends the older Internal\BlindPayException, so existing `catch` blocks on that class keep
 * working.
 */
class BlindPayException extends InternalBlindPayException
{
    public static function validation(string $message, array $context = []): self
    {
        return new self(message: $message, errorCode: 'VALIDATION_ERROR', context: $context);
    }

    public static function api(string $message, ?string $errorCode = null, array $context = []): self
    {
        return new self(message: $message, errorCode: $errorCode ?? 'API_ERROR', context: $context);
    }

    public static function configuration(string $message): self
    {
        return new self(message: $message, errorCode: 'CONFIGURATION_ERROR');
    }

    public static function authentication(string $message = 'Authentication failed'): self
    {
        return new self(message: $message, errorCode: 'AUTHENTICATION_ERROR', code: 401);
    }
}
