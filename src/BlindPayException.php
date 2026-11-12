<?php

declare(strict_types=1);

namespace BlindPay\SDK;

use BlindPay\SDK\Internal\BlindPayException as InternalBlindPayException;

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
class BlindPayException extends InternalBlindPayException {}
