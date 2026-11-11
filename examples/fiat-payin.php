<?php

declare(strict_types=1);

/**
 * Receive fiat and settle it as stablecoin in a customer's blockchain wallet.
 *
 * 1. Quote the payin (amounts are integer cents).
 * 2. Create the payin from the quote and show the customer how to pay.
 *
 * On a development instance a payin completes by itself after about 30 seconds, and OTC payin
 * quotes expire after 10 seconds.
 *
 * Nothing runs when this file is loaded; call the function from your own code.
 */
require __DIR__.'/../vendor/autoload.php';

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Payins\CreatePayinQuoteInput;
use BlindPay\SDK\Resources\Payins\PaymentMethod;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\StablecoinToken;

/**
 * @param  int  $amountCents  what the payer sends, in cents of their currency (50000 = 500.00)
 * @return string the payin ID
 */
function startFiatPayin(string $blockchainWalletId, int $amountCents): string
{
    $blindpay = new BlindPay(
        apiKey: (string) getenv('BLINDPAY_API_KEY'),
        instanceId: (string) (getenv('BLINDPAY_INSTANCE_ID') ?: 'in_000000000000'),
    );

    $quote = $blindpay->payins->quotes->create(new CreatePayinQuoteInput(
        blockchainWalletId: $blockchainWalletId,
        currencyType: CurrencyType::SENDER,
        paymentMethod: PaymentMethod::PIX,
        requestAmount: $amountCents,
        token: StablecoinToken::USDC,
    ))->getDataOrFail();

    $payin = $blindpay->payins->createEvm($quote->id)->getDataOrFail();

    // PIX payers get a copy-and-paste code; other rails return bank details.
    echo "payin {$payin->id}: ".($payin->pixCode ?? 'see the bank details on the payin')."\n";

    return $payin->id;
}
