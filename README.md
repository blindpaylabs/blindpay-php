# BlindPay PHP SDK

The official PHP SDK for [BlindPay](https://blindpay.com), the stablecoin API for global payments.

- PHP 8.2 or higher, [Guzzle](https://docs.guzzlephp.org) 7.9+
- Typed inputs and responses, open enums, retries, idempotency keys, pagination generators and a standalone webhook verifier
- Upgrading from 3.x? Read [UPGRADING.md](UPGRADING.md).

## Installation

```bash
composer require blindpay/php
```

## Quick start

Get your API key and instance ID from the BlindPay dashboard (`https://app.blindpay.com/instances/{instanceId}/api-keys`). Keep the key out of source control; read it from the environment.

```php
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use BlindPay\SDK\BlindPay;

$blindpay = new BlindPay(
    apiKey: getenv('BLINDPAY_API_KEY'),
    instanceId: 'in_000000000000',
);

$response = $blindpay->available->getRails();

if ($response->isError()) {
    throw new RuntimeException($response->error->message);
}

foreach ($response->data as $rail) {
    echo "{$rail->label} ({$rail->value}) - {$rail->country}\n";
}
```

More runnable scripts live in [`examples/`](examples): webhook endpoint setup and verification, a managed-wallet payout, a fiat payin and a reconciliation job.

## Configuration

```php
$blindpay = new BlindPay(
    apiKey: getenv('BLINDPAY_API_KEY'),
    instanceId: 'in_000000000000',
    baseUrl: 'https://api.blindpay.com', // default
    timeout: 60.0,                        // seconds per attempt, default 60
    maxRetries: 2,                        // retries after the first attempt, default 2; 0 disables
    handler: null,                        // a Guzzle handler or HandlerStack, default null
);
```

| Option | Default | Notes |
|---|---|---|
| `baseUrl` | `https://api.blindpay.com` | API origin without `/v1`. **Every request, including your API key, goes to this URL.** Override it only for tests or a proxy you trust. |
| `timeout` | `60.0` | Seconds allowed per attempt (connect, send and receive). Can be overridden per call with `RequestOptions(timeout: ...)`. |
| `maxRetries` | `2` | Retries after the first attempt, so 3 attempts in total. `0` turns retries off, and also stops the SDK generating idempotency keys. |
| `handler` | Guzzle default | A Guzzle handler or `HandlerStack`. Inject a `MockHandler` in tests, or a stack with your own middleware (logging, metrics, tracing). |

The client is immutable and safe to share (build it once, reuse it everywhere). Per-request options never change it, and `var_dump()` / `print_r()` of the client never show the API key.

Testing with a mock handler:

```php
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

$mock = new MockHandler([new Response(200, [], json_encode([]))]);
$stack = HandlerStack::create($mock);
$history = [];
$stack->push(Middleware::history($history));

$blindpay = new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000', handler: $stack);
```

## Results and errors

Resource methods return a `BlindPayApiResponse`. They do not throw for API, network or decoding failures: check `isError()`.

```php
$response = $blindpay->payouts->get('pa_000000000000');

if ($response->isError()) {
    $error = $response->error; // BlindPay\SDK\Types\ErrorResponse

    error_log(sprintf(
        '[%s] %s (status %s, code %s, request %s)',
        $error->type->value, $error->message, $error->status ?? '-', $error->code ?? '-', $error->requestId ?? '-',
    ));

    return;
}

$payout = $response->data; // Payout
echo $response->requestId; // the X-Request-Id of the response
```

On success, `data` holds the decoded value, `requestId` the `X-Request-Id` response header, and `idempotencyReplayed` is `true` when the API replayed a stored response for your idempotency key.

`ErrorResponse` fields:

| Field | Meaning |
|---|---|
| `message` | The API `message` (for example `not_enough_balance`), or an SDK description. Never contains the request body or the API key. |
| `status` | HTTP status, or `null` when no response was received. |
| `code` | The API's stable, UPPERCASE error code (for example `PAYOUTS_INSUFFICIENT_BALANCE`). Branch on this, not on `message`. Absent on some errors (auth, rate limit, idempotency). |
| `description` | A longer explanation, when the API sends one. |
| `errors` | The raw `errors` array of the body (validation details); empty by default. |
| `traceId` | The API `trace_id`, when present. Quote it to support. |
| `requestId` | The `X-Request-Id` response header. |
| `idempotencyKey` | The key the request carried, yours or generated. Use it to resend safely. |
| `retryable` | Whether resending the same request with the same key may succeed. |
| `type` | An `ErrorType`, see below. |
| `headers` | Response headers (lower-cased names), when a response arrived. |

`ErrorType` tells you where a failure came from:

| `ErrorType` | Meaning |
|---|---|
| `API` | The API answered with a non-2xx status. Non-JSON error bodies (for example an HTML 502 page) still produce an `API` error with the status. |
| `NETWORK` | No response was received: connection, DNS or TLS failure, or the per-attempt timeout. |
| `VALIDATION` | The SDK rejected the call before sending it (an empty ID, an invalid idempotency key, ...). |
| `DECODE` | The API answered 2xx but the body did not fit the SDK's types. |

`$error->isNetworkError()` and `$error->isApiError()` are shortcuts. An empty 2xx body (for example 204) is a success with `data === null`.

If you prefer exceptions, `getDataOrFail()` returns the data or throws `BlindPay\SDK\BlindPayApiException`, which carries the `ErrorResponse`:

```php
use BlindPay\SDK\BlindPayApiException;

try {
    $payout = $blindpay->payouts->get('pa_000000000000')->getDataOrFail();
} catch (BlindPayApiException $e) {
    echo $e->error->code, ' ', $e->error->requestId; // the ErrorResponse
    echo $e->getCode();      // the HTTP status (0 when no response was received)
    echo $e->getErrorCode(); // the API error code
}
```

## Idempotency

Every `POST`, `PUT`, `PATCH` and `DELETE` JSON request can carry an `Idempotency-Key`, so a repeated request is executed once. Pass your own key (an order or payment ID works well) in `RequestOptions`, the trailing argument of every resource method:

```php
use BlindPay\SDK\Resources\Payouts\CreateEvmPayoutInput;
use BlindPay\SDK\Types\RequestOptions;

$response = $blindpay->payouts->createEvm(
    new CreateEvmPayoutInput(quoteId: 'qu_000000000000', walletId: 'wa_000000000000'),
    new RequestOptions(idempotencyKey: 'order-4821-payout'),
);
```

- A key is 1 to 255 characters. An empty or longer key is rejected with a `VALIDATION` error before anything is sent (the API silently ignores an empty key, which would look protected and not be).
- When you pass no key and retries are on (`maxRetries > 0`), the SDK generates a UUIDv4 key for every mutation before the first attempt, and reuses it on every retry. It is exposed on `$response->error->idempotencyKey` if the call fails.
- Multipart requests (uploads) never carry an idempotency key.
- The terms of service call has its own `idempotencyKey` in the request body (`InitiateInput`). That is unrelated to the header.

`RequestOptions` also takes `timeout` (seconds, for that call) and `headers` (extra headers; they cannot override the SDK's own `Authorization`, `User-Agent`, `Idempotency-Key` or `Content-Type`).

## Retries

The SDK retries a failed attempt up to `maxRetries` times:

| Request | Retried on |
|---|---|
| `GET` | network errors and timeouts, HTTP 408, 429, 500, 502, 503 and 504 (not a 5xx whose `code` is `PAYINS_CARD_PAYMENT_PENDING`, which the API marks terminal) |
| `POST` / `PUT` / `PATCH` / `DELETE` (JSON) | only where the idempotency key fully protects the request: network errors and timeouts, HTTP 429, and HTTP 409 `idempotency_key_in_flight`. **Never on a 5xx.** |
| Multipart (uploads) | only when nothing was sent (a DNS or connect failure) |

Other 4xx responses are never retried. The delay is the `Retry-After` header when it is a whole number of seconds up to 60, otherwise `min(8, 0.5 * 2^attempt)` seconds multiplied by a random factor between 0.5 and 1. The request body is serialised once and the same bytes are resent on every attempt.

A mutation that gets a 5xx is not retried because the API releases the idempotency key on a 5xx, so a resend would run the handler again. The error comes back with `retryable: true` and the key instead. **If a create or payout returns a 5xx, look the resource up (by quote, external id, or a list filter) before resending. Resend with the same `idempotency_key` from the error** (`$response->error->idempotencyKey`).

```php
if ($response->isError() && $response->error->retryable && $response->error->status >= 500) {
    // 1. check whether the payout exists (payouts->list with a filter, or by quote)
    // 2. only if it does not, resend with the same key
    $retry = $blindpay->payouts->createEvm($input, new RequestOptions(idempotencyKey: $response->error->idempotencyKey));
}
```

A 409 `idempotency_key_in_flight` usually carries a `Retry-After` above 60 seconds, so after the retries run out you get a retryable error with the key: wait, then resend the same request with the same key.

## Pagination

List endpoints return a bare array when you send no pagination parameter, and `{data, pagination}` when you send `limit`, `starting_after` or `ending_before`. The SDK decodes both into one type, `ListResponse` (payouts, payins, transfers, customers, payables and wallet deposits):

```php
use BlindPay\SDK\Resources\Payouts\ListPayoutsInput;

$page = $blindpay->payouts->list(new ListPayoutsInput(limit: 50))->getDataOrFail();

foreach ($page as $payout) { // iterable and Countable; $page->data is the list
    echo $payout->id, "\n";
}

if ($page->hasMore()) {
    $next = $blindpay->payouts->list(new ListPayoutsInput(limit: 50, startingAfter: $page->pagination->nextPage));
}
```

`$page->pagination` (`hasMore`, `nextPage`, `prevPage`) is `null` for the bare-array form. `PaginationParams` does not validate `limit` client-side: the API accepts 10, 50, 100, 200, 500 or 1000 (wallet deposits: 5, 10, 50, 100, 200). `offset` is deprecated and ignored by the API; use `startingAfter`.

**Unpaginated lists return every row.** For a large list (a busy instance's payouts, say), page it with the generator instead. `listAutoPaging()` exists on `payouts`, `payins`, `customers`, `transfers` and `payables`; `wallets->custodial->getDepositsAutoPaging()` does the same for wallet deposits:

```php
use BlindPay\SDK\BlindPayApiException;

try {
    foreach ($blindpay->payouts->listAutoPaging(new ListPayoutsInput(customerId: 'cu_000000000000')) as $payout) {
        echo $payout->id, "\n";
    }
} catch (BlindPayApiException $e) {
    error_log('page failed: '.$e->error->message);
}
```

The generator is lazy, asks for `limit=100` unless you set one, follows `next_page` as `starting_after` while `has_more`, and goes forward only (`endingBefore` throws `BlindPayException`). Each page goes through the normal retry path. A failed page **throws** `BlindPayApiException`, so put the `try` around the `foreach`.

Lists that are always plain arrays (blockchain wallets, virtual accounts, partner fees, webhook endpoints, ...) return a PHP array in `data`; customer bank accounts return `ListBankAccountsResponse`, with the rows in `data`.

## Webhooks

Verify against the **raw** request body, never re-encoded JSON, and do it before you trust anything in it. No API client is needed, only the endpoint's signing secret (`whsec_...`, from `$blindpay->instances->webhookEndpoints->getSecret($id)` or the dashboard). Never log the secret.

```php
use BlindPay\SDK\Resources\Webhooks\WebhookEvents;
use BlindPay\SDK\Webhooks\WebhookException;
use BlindPay\SDK\Webhooks\WebhookVerifier;

$rawBody = file_get_contents('php://input');
$headers = getallheaders(); // header names are matched case-insensitively
$secret  = getenv('BLINDPAY_WEBHOOK_SECRET');

// Option 1: just check the signature (never throws; 5 minute timestamp tolerance by default)
if (! WebhookVerifier::verify($rawBody, $headers, $secret)) {
    http_response_code(400);
    exit;
}

// Option 2: verify and decode into a typed event
try {
    $event = WebhookVerifier::constructEvent($rawBody, $headers, $secret);
} catch (WebhookException) {
    http_response_code(400);
    exit;
}

// Deliveries can repeat: dedupe on the svix-id header
$deliveryId = array_change_key_case($headers, CASE_LOWER)['svix-id'] ?? '';
// if (alreadyProcessed($deliveryId)) { http_response_code(200); exit; }

if ($event->type === WebhookEvents::PAYOUT_COMPLETE || $event->type === WebhookEvents::PAYOUT_UPDATE) {
    $payout = $event->data; // CreateEvmPayoutResponse
    // see "Failed payouts" below
}

http_response_code(200);
```

`WebhookEvent` has `type` (a `WebhookEvents` case, or the raw string for an event added after this SDK version), `data` (the typed payload, `null` for an unknown event), and `raw` (the decoded JSON). An unknown event never throws: acknowledge it with a 2xx. `$event->isKnown()` tells them apart.

| Events | `data` |
|---|---|
| `customer.new`, `customer.update` | `IndividualWithStandardKYC`, `IndividualWithEnhancedKYC` or `BusinessWithStandardKYB` |
| `customer.delete` | `CustomerDeletedPayload` |
| `bankAccount.new` | `BankAccountListItem` |
| `blockchainWallet.new`, `blockchainWallet.update` | `BlockchainWallet` |
| `limitIncrease.new`, `limitIncrease.update` | `LimitIncreaseRequest` |
| `payout.new`, `.update`, `.complete`, `.partnerFee` | `CreateEvmPayoutResponse` |
| `payin.new`, `.update`, `.complete`, `.partnerFee` | `CreateEvmPayinResponse` |
| `transfer.new`, `.update`, `.complete` | `CreateTransferResponse` |
| `payable.new`, `.update`, `.complete` | `Payable` |
| `virtualAccount.new`, `virtualAccount.complete` | `VirtualAccount` |
| `wallet.new`, `wallet.update` | `CustodialWallet` |
| `wallet.inbound` | `WalletInboundPayload` |
| `tos.accept` | `TosAcceptedPayload` |

### Failed payouts

A failed payout arrives on **both** `payout.update` (with `status: "failed"`) and `payout.complete` (the dev simulation, for example, sends `payout.complete` with a failed status). Do not infer failure from the event name; branch on `status`:

```php
use BlindPay\SDK\Types\TransactionStatus;

$payout = $event->data;

match ($payout->status) { // TransactionStatus|string: a status this SDK does not know is a plain string
    TransactionStatus::COMPLETED => markPaid($payout->id),
    TransactionStatus::FAILED => markFailed($payout->id),
    TransactionStatus::REFUNDED => markRefunded($payout->id),
    default => null, // processing, on_hold, ...
};
```

To register an endpoint, see [`examples/webhook-endpoint.php`](examples/webhook-endpoint.php). The older `$blindpay->verifyWebhookSignature(...)` still works but is deprecated in favour of `WebhookVerifier::verify()`.

## File uploads

KYC and KYB fields take the URL of an uploaded file. Uploads are `multipart/form-data`; give an `UploadFile` a path, a stream or bytes:

```php
use BlindPay\SDK\Resources\Upload\AnalyzeUploadInput;
use BlindPay\SDK\Resources\Upload\PresignInput;
use BlindPay\SDK\Resources\Upload\UploadAnalyzeType;
use BlindPay\SDK\Resources\Upload\UploadBucket;
use BlindPay\SDK\Resources\Upload\UploadFile;
use BlindPay\SDK\Resources\Upload\UploadInput;

$file = UploadFile::fromPath('/path/to/proof-of-address.pdf'); // or new UploadFile($bytes, 'doc.pdf', 'application/pdf')

$uploaded = $blindpay->upload->create(new UploadInput(UploadBucket::ONBOARDING, $file));
echo $uploaded->data->fileUrl ?? '';

// Rate how likely a document is to be approved, before you submit it
$analysis = $blindpay->upload->analyze(new AnalyzeUploadInput(
    file: UploadFile::fromPath('/path/to/proof-of-address.pdf'),
    type: UploadAnalyzeType::PROOF_OF_ADDRESS,
    metadata: ['full_name' => 'Jane Doe'],
));

// A short-lived signed URL to read an uploaded file
$signed = $blindpay->upload->presign(new PresignInput($uploaded->data->fileUrl));
```

Uploads authenticate against your instance ID and need a completed onboarding. They carry no idempotency key and are retried only when nothing was sent.

## Units

| Value | Unit |
|---|---|
| Payout, payin, quote, transfer and limit amounts (`requestAmount`, `senderAmount`, ...) | integer **cents** (`1000` is 10.00) |
| Partner percentage fees | **basis points** (`100` is 1%) |
| Partner flat fees | cents |
| Wallet balances | **whole tokens**, decimal (`10.5` is 10.5 USDC) |
| `total_fee_amount` on payouts and payins | a decimal token amount |

For whole-unit sender currencies, a `CurrencyType::SENDER` payin quote amount must be a multiple of 100. Never build cent amounts by multiplying a float: round explicitly (`(int) round($dollars * 100)`).

## Enums

Every enum in a **response** is open: a value this SDK version does not know is kept as the raw string instead of failing the whole response. Response properties are typed `Enum|string`, so compare defensively:

```php
if ($payout->status === TransactionStatus::COMPLETED) { /* known value */ }
if ($payout->status instanceof TransactionStatus) { $payout->status->value; } // a string never has ->value
```

Enums in **requests** (inputs) stay closed, for type safety.

## "Exactly one of" inputs

Some inputs take exactly one of two fields. The SDK does not validate this; the API answers 400 if you send both or neither.

- Payout creation (`createEvm`, `createSolana`, `createStellar`, `authorizeStellarToken`): `senderWalletAddress` or `walletId` (a BlindPay managed wallet).
- Payout quotes (`CreateQuoteInput`): `bankAccountId` or `payableId`.

## Tracking endpoints

`payouts->getTrack()`, `payins->getTrack()` and `transfers->getTrack()` call `/v1/e/*`, the public tracking endpoints of BlindPay's web and iOS apps. From a server-side SDK they return 401 in production. Use `payouts->get($id)` (and `payins->get`, `transfers->get`) instead.

## Testing against a development instance

- Amounts of `66600` (666.00) make a payout fail, and `77700` (777.00) make it refund.
- Payins complete on their own after about 30 seconds.
- Quotes expire after 5 minutes (OTC payin quotes after 10 seconds; SEPA quotes may expire sooner).
- Development instances are rate limited to 40 requests per minute. A 429 is retried for you.
- Settlement is simulated with the test token USDB.

## Resources

Everything hangs off the client. Every method takes a trailing `?RequestOptions $options = null`.

| Property | Methods |
|---|---|
| `available` | `getBankDetails`, `getRails`, `getSwiftCodeBankDetails`, `getNaicsCodes` |
| `customers` | `list`, `listAutoPaging`, `create`, `createIndividualWithStandardKYC`, `createIndividualWithEnhancedKYC`, `createBusinessWithStandardKYB`, `get`, `update`, `delete`, `getLimits`, `getLimitIncreaseRequests`, `requestLimitIncrease` |
| `customers->bankAccounts` | `list`, `get`, `delete`, `createPix`, `createPixSafe`, `createArgentinaTransfers`, `createSpei`, `createColombiaAch`, `createAch`, `createWire`, `createInternationalSwift`, `createRtp`, `createSepa`, `createTed`, `createFasterPayments`, `createPlaidLink` |
| `customers->rfi` | `get`, `submit` |
| `wallets->blockchain` | `list`, `createWithAddress`, `createWithHash`, `getMessage`, `get`, `delete`, `setSelfCustody`, `createAssetTrustline`, `mintUsdbStellar`, `mintUsdbSolana`, `prepareSolanaDelegationTransaction` |
| `wallets->offramp` | `list`, `create`, `get`, `update` |
| `wallets->custodial` | `list`, `get`, `create`, `getBalance`, `delete`, `getDeposits`, `getDepositsAutoPaging` |
| `quotes` | `create`, `getFxRate` |
| `payouts` | `list`, `listAutoPaging`, `get`, `getTrack`, `createEvm`, `createSolana`, `createStellar`, `authorizeStellarToken`, `submitDocuments` |
| `payins` | `list`, `listAutoPaging`, `get`, `getTrack`, `createEvm` |
| `payins->quotes` | `create`, `getFxRate` |
| `transfers` | `createQuote`, `create`, `list`, `listAutoPaging`, `get`, `getTrack` |
| `payables` | `create`, `list`, `listAutoPaging`, `get`, `delete` |
| `virtualAccounts` | `create`, `update`, `get`, `list` |
| `partnerFees` | `list`, `create`, `get`, `delete` |
| `fees` | `get` |
| `upload` | `create`, `analyze`, `presign` |
| `instances` | `get`, `update`, `delete`, `getMembers`, `deleteMember`, `updateMemberRole` |
| `instances->webhookEndpoints` | `list`, `create`, `delete`, `getSecret`, `getPortalAccessUrl` |
| `instances->tos` | `initiate` |
| `instances->onboarding` | `getBusinessDetails`, `getBusinessProfile`, `getOwnershipDocuments` |
| `instances->rfi` | `get`, `submit` |
| `ownership` | `migrate` |

Responses are the readonly classes next to each resource (`BlindPay\SDK\Resources\Payouts\Payout`, ...). Request inputs are `...Input` classes with named constructor arguments.

## Documentation

- [BlindPay API documentation](https://blindpay.com/docs/getting-started/overview)
- [API reference](https://api.blindpay.com/reference)

## Support

- Email: [support@blindpay.com](mailto:support@blindpay.com)
- Issues: [GitHub Issues](https://github.com/blindpaylabs/blindpay-php/issues)

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

Made by the [BlindPay](https://blindpay.com) team.
