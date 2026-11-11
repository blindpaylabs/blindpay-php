<?php

declare(strict_types=1);

/**
 * Pay out from a BlindPay managed wallet to a customer's bank account.
 *
 * 1. Quote with the bank account (amounts are integer cents).
 * 2. Create the payout with the managed wallet's ID and an explicit idempotency key.
 * 3. React to payout.update / payout.complete webhooks (a failed payout can arrive on either).
 *
 * Nothing runs when this file is loaded; call the functions from your own code.
 */
require __DIR__.'/../vendor/autoload.php';

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Payouts\CreateEvmPayoutInput;
use BlindPay\SDK\Resources\Payouts\CreateEvmPayoutResponse;
use BlindPay\SDK\Resources\Quotes\CreateQuoteInput;
use BlindPay\SDK\Resources\Webhooks\WebhookEvents;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\RequestOptions;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TransactionStatus;
use BlindPay\SDK\Webhooks\WebhookEvent;

/**
 * @param  int  $amountCents  what the customer's bank account receives, in cents (100000 = 1,000.00)
 * @param  string  $orderId  your own unique reference; it becomes the idempotency key
 * @return string the payout ID
 */
function payoutFromManagedWallet(string $bankAccountId, string $walletId, int $amountCents, string $orderId): string
{
    $blindpay = new BlindPay(
        apiKey: (string) getenv('BLINDPAY_API_KEY'),
        instanceId: (string) (getenv('BLINDPAY_INSTANCE_ID') ?: 'in_000000000000'),
    );

    // Quotes expire after 5 minutes and can be used once.
    $quote = $blindpay->quotes->create(new CreateQuoteInput(
        network: Network::BASE,
        token: StablecoinToken::USDC,
        bankAccountId: $bankAccountId,
        currencyType: CurrencyType::RECEIVER,
        requestAmount: $amountCents,
    ))->getDataOrFail();

    // Exactly one of walletId / senderWalletAddress. The key makes a repeated call safe.
    $response = $blindpay->payouts->createEvm(
        new CreateEvmPayoutInput(quoteId: $quote->id, walletId: $walletId),
        new RequestOptions(idempotencyKey: "payout-{$orderId}"),
    );

    if ($response->isError()) {
        $error = $response->error;
        if ($error->retryable && $error->status !== null && $error->status >= 500) {
            // The payout may exist. Look it up (payouts->list filtered by customer or bank
            // account) before resending with the same key: $error->idempotencyKey.
            throw new RuntimeException("payout outcome unknown for order {$orderId} (request {$error->requestId})");
        }

        throw new RuntimeException("payout rejected: {$error->message} ({$error->code})");
    }

    return $response->data->id;
}

/** Call this from your webhook handler with the event from WebhookVerifier::constructEvent(). */
function onPayoutEvent(WebhookEvent $event): void
{
    if ($event->type !== WebhookEvents::PAYOUT_UPDATE && $event->type !== WebhookEvents::PAYOUT_COMPLETE) {
        return;
    }

    $payout = $event->data;
    assert($payout instanceof CreateEvmPayoutResponse);

    // Do not infer the outcome from the event name: branch on the status.
    match ($payout->status) {
        TransactionStatus::COMPLETED => error_log("payout {$payout->id} paid out"),
        TransactionStatus::FAILED => error_log("payout {$payout->id} failed"),
        TransactionStatus::REFUNDED => error_log("payout {$payout->id} refunded"),
        default => null, // processing, on_hold, or a status newer than this SDK
    };
}
