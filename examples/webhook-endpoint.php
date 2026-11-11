<?php

declare(strict_types=1);

/**
 * Webhook endpoint setup and verification.
 *
 * 1. registerWebhookEndpoint() creates the endpoint once and returns its signing secret.
 * 2. handleWebhookRequest() is what your endpoint's route runs on every delivery.
 *
 * Nothing runs when this file is loaded; call the functions from your own code.
 */
require __DIR__.'/../vendor/autoload.php';

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Payins\CreateEvmPayinResponse;
use BlindPay\SDK\Resources\Payouts\CreateEvmPayoutResponse;
use BlindPay\SDK\Resources\Webhooks\CreateWebhookEndpointInput;
use BlindPay\SDK\Resources\Webhooks\WebhookEvents;
use BlindPay\SDK\Types\TransactionStatus;
use BlindPay\SDK\Webhooks\WebhookException;
use BlindPay\SDK\Webhooks\WebhookVerifier;

/**
 * Create the endpoint and read its signing secret (`whsec_...`). Store the secret in your secret
 * manager and never log it.
 */
function registerWebhookEndpoint(string $url): string
{
    $blindpay = new BlindPay(
        apiKey: (string) getenv('BLINDPAY_API_KEY'),
        instanceId: (string) (getenv('BLINDPAY_INSTANCE_ID') ?: 'in_000000000000'),
    );

    $endpoint = $blindpay->instances->webhookEndpoints->create(new CreateWebhookEndpointInput(
        url: $url,
        events: [
            WebhookEvents::PAYOUT_NEW,
            WebhookEvents::PAYOUT_UPDATE,
            WebhookEvents::PAYOUT_COMPLETE,
            WebhookEvents::PAYIN_UPDATE,
            WebhookEvents::PAYIN_COMPLETE,
        ],
    ))->getDataOrFail();

    return $blindpay->instances->webhookEndpoints->getSecret($endpoint->id)->getDataOrFail()->key;
}

/**
 * Handle one webhook delivery. Returns the HTTP status to answer with.
 *
 * @param  string  $rawBody  the request body exactly as received: file_get_contents('php://input')
 * @param  array<string, string|string[]>  $headers  the request headers: getallheaders()
 * @param  callable(string): bool  $firstDelivery  records an `svix-id` and returns false when it was
 *                                                 already processed (back it with your database)
 */
function handleWebhookRequest(string $rawBody, array $headers, string $secret, callable $firstDelivery): int
{
    // Verifies the signature and the timestamp (5 minute tolerance), then decodes the payload.
    try {
        $event = WebhookVerifier::constructEvent($rawBody, $headers, $secret);
    } catch (WebhookException) {
        return 400;
    }

    // BlindPay may deliver the same event more than once: dedupe on the svix-id header.
    $svixId = array_change_key_case($headers, CASE_LOWER)['svix-id'] ?? '';
    $deliveryId = is_array($svixId) ? (string) ($svixId[0] ?? '') : $svixId;
    if ($deliveryId === '' || ! $firstDelivery($deliveryId)) {
        return 200;
    }

    // An event added after this SDK version has a string type and null data: acknowledge it.
    if (! $event->isKnown()) {
        return 200;
    }

    switch ($event->type) {
        case WebhookEvents::PAYOUT_UPDATE:
        case WebhookEvents::PAYOUT_COMPLETE:
            // A failed payout arrives on payout.update or payout.complete: branch on the status.
            $payout = $event->data;
            assert($payout instanceof CreateEvmPayoutResponse);
            if ($payout->status === TransactionStatus::FAILED) {
                error_log("payout {$payout->id} failed");
            } elseif ($payout->status === TransactionStatus::COMPLETED) {
                error_log("payout {$payout->id} completed");
            }

            break;
        case WebhookEvents::PAYIN_COMPLETE:
            $payin = $event->data;
            assert($payin instanceof CreateEvmPayinResponse);
            error_log("payin {$payin->id} completed");

            break;
        default:
            break;
    }

    return 200;
}
