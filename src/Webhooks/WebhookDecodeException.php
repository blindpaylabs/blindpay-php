<?php

declare(strict_types=1);

namespace BlindPay\SDK\Webhooks;

use Exception;

/**
 * The delivery is authentic (its signature and timestamp verified) but its body could not be
 * decoded: not a JSON object, no `webhook_event`, or a payload this SDK version cannot read.
 *
 * Do not answer it like a forged request. BlindPay retries every non-2xx delivery for hours, so
 * log the `svix-id` and $raw, acknowledge with a 2xx, and replay the event from the dashboard's
 * webhook events page once your handler (or this SDK) is fixed.
 */
final class WebhookDecodeException extends WebhookException
{
    /**
     * @param  string|null  $eventType  the body's `webhook_event`, when it has one
     * @param  array<string, mixed>|null  $raw  the decoded JSON body, when it is a JSON object
     */
    public function __construct(
        string $message,
        public readonly ?string $eventType = null,
        public readonly ?array $raw = null,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
