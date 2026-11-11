<?php

declare(strict_types=1);

/**
 * Reconcile payouts: walk every payout that matches a filter with the auto-pager.
 *
 * listAutoPaging() is lazy: it asks for 100 rows per request and follows the cursor until the API
 * says there are no more, so memory stays flat however many payouts match. It throws
 * BlindPayApiException when a page request fails, so wrap the loop, not each row.
 *
 * Nothing runs when this file is loaded; call the function from your own code.
 */
require __DIR__.'/../vendor/autoload.php';

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\BlindPayApiException;
use BlindPay\SDK\Resources\Payouts\ListPayoutsInput;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\TransactionStatus;

/**
 * Count payouts by status for one customer.
 *
 * @return array<string, int> status => number of payouts
 */
function countPayoutsByStatus(string $customerId): array
{
    $blindpay = new BlindPay(
        apiKey: (string) getenv('BLINDPAY_API_KEY'),
        instanceId: (string) (getenv('BLINDPAY_INSTANCE_ID') ?: 'in_000000000000'),
    );

    $counts = [];

    try {
        $payouts = $blindpay->payouts->listAutoPaging(new ListPayoutsInput(
            customerId: $customerId,
            network: Network::BASE,
        ));

        foreach ($payouts as $payout) {
            // status is TransactionStatus|string: a status newer than this SDK is a plain string
            $status = $payout->status instanceof TransactionStatus ? $payout->status->value : $payout->status;
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
    } catch (BlindPayApiException $e) {
        error_log("reconciliation stopped: {$e->error->message} (request {$e->error->requestId})");
    }

    return $counts;
}
