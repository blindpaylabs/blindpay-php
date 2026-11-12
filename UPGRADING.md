# Upgrading from 4.1 to 4.2

4.2 adds `WebhookEvent::dataAs()`, `WebhookDecodeException` and type narrowing for static analysers. Three behaviours change; none of them needs a code change unless you rely on the old behaviour:

- **`CreateEvmPayoutResponse::$senderWalletAddress` is `?string`** (was `string`). The spec requires the field and the API always sends it, so it is set on every real response and webhook; it is null only when a payload omits it. Before, such a payload failed to decode (`constructEvent()` threw). Code that passes it to a `string` parameter now gets a PHPStan warning: add `?? ''` or a null check.
- **An authentic webhook that cannot be decoded throws `WebhookDecodeException`**, a subclass of `WebhookException`, so existing `catch (WebhookException)` blocks still catch it. Catch it first to answer 2xx instead of 400 (see the README's Webhooks section). `WebhookException` is no longer `final`.
- **`listAutoPaging()` throws `BlindPayException` (`PAGINATION_CURSOR_REPEATED`) when the API repeats a cursor**, after yielding the rows it received. 4.1 stopped silently there, which hid the rows after it.

Since 4.1, transport error messages no longer include query strings: branch on `$error->code` / `ErrorCode`, not on message text.

# Upgrading from 3.x to 4.0

4.0 makes the SDK match the BlindPay API contract: every response decodes (including values the SDK does not know yet), every method accepts per-request options, and the client retries and sends idempotency keys. Most application code keeps working with small edits. This guide lists every change that can break existing code, with a before and after for each, then the additions you may want to adopt.

Requirements are unchanged: PHP 8.2+ and Guzzle 7.9+.

Skim this list first:

1. [Tests that inject an HTTP client by reflection](#1-construction-and-testing)
2. [`getDataOrFail()` throws a different exception](#2-getdataorfail-throws-blindpayapiexception)
3. [Response enums are `Enum|string`](#3-response-enums-are-enumstring)
4. [List responses are `ListResponse`](#4-list-responses-are-listresponse)
5. [`PaginationParams` no longer validates](#5-paginationparams-no-longer-validates)
6. [Amounts are `int` cents](#6-amounts-are-int-cents)
7. [`CreateQuoteInput` was reshaped](#7-createquoteinput-was-reshaped)
8. [Bank account creates return `BankAccountListItem`](#8-bank-account-creates-return-bankaccountlistitem)
9. [Other changed request inputs](#9-other-changed-request-inputs)
10. [Uploads are multipart](#10-uploads-are-multipart)
11. [Removed methods and types](#11-removed-methods-and-types)
12. [Interfaces and constructors (only if you implement or build them yourself)](#12-interfaces-and-constructors)
13. [Autoloading](#13-autoloading)

## 1. Construction and testing

`new BlindPay(apiKey: ..., instanceId: ...)` is unchanged. The constructor gained optional `baseUrl`, `timeout`, `maxRetries`, `handler` and `sleep` arguments (see the README).

What breaks is injecting a Guzzle client by reflection, which tests commonly did. The client no longer has a `httpClient` property. Pass a Guzzle handler instead:

```php
// Before (3.x)
$blindpay = new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000');
$property = (new ReflectionClass($blindpay))->getProperty('httpClient');
$property->setAccessible(true);
$property->setValue($blindpay, new Client(['handler' => HandlerStack::create($mock)]));

// After (4.0)
$blindpay = new BlindPay(
    apiKey: 'test_key',
    instanceId: 'in_000000000000',
    handler: HandlerStack::create($mock), // or the MockHandler itself
);
```

Behaviour you get without changing anything, worth knowing before you deploy:

- **Retries are on by default** (`maxRetries: 2`), and every JSON mutation now sends an `Idempotency-Key` (a generated UUIDv4 unless you pass one). Set `maxRetries: 0` to turn both off.
- **A 5xx on a mutation is not retried.** Look the resource up before resending; see "Retries" in the README.
- **Each attempt has a 60 second timeout** (`timeout:`).
- The request ID and an idempotency-replayed flag are on every result, and errors carry the idempotency key (see section 2).

## 2. `getDataOrFail()` throws `BlindPayApiException`

It used to throw a `\RuntimeException` that held only the message. It now throws `BlindPay\SDK\BlindPayApiException`, which extends `BlindPay\SDK\Internal\BlindPayException` (and so `\Exception`), **not** `\RuntimeException`. A `catch (\RuntimeException)` no longer catches it.

```php
// Before (3.x)
try {
    $payout = $blindpay->payouts->get($id)->getDataOrFail();
} catch (\RuntimeException $e) {
    error_log($e->getMessage());
}

// After (4.0)
use BlindPay\SDK\BlindPayApiException;

try {
    $payout = $blindpay->payouts->get($id)->getDataOrFail();
} catch (BlindPayApiException $e) {
    error_log($e->getMessage());
    $error = $e->error; // ErrorResponse: status, code, requestId, idempotencyKey, ...
    $status = $e->getCode(); // the HTTP status (0 when no response was received)
}
```

`ErrorResponse` used to hold only `message`. It now also carries `status`, `code`, `description`, `errors`, `traceId`, `requestId`, `idempotencyKey`, `retryable`, `type` (an `ErrorType`: `API`, `NETWORK`, `VALIDATION` or `DECODE`) and `headers`. `new ErrorResponse('message')` still works. `BlindPayApiResponse` also gained `requestId` and `idempotencyReplayed`.

Resource methods still never throw for API, network or decoding failures: check `isError()`. A malformed or unexpected response body is now a `DECODE` error instead of a `TypeError` or warning escaping from the method. Where 3.x returned an `ErrorResponse` for a client-side rejection (an empty ID, say), it is now an `ErrorType::VALIDATION` error.

## 3. Response enums are `Enum|string`

Every enum in a response type now decodes open: a value this SDK version does not know is kept as the raw string instead of throwing. In 3.x only a few (network, token, currency, rail, banking partner, webhook events, source of wealth, NAICS code, customer type and KYC type) were open. The property types are now `Enum|string` (or `Enum|string|null`) on all of them, so `->value` on a closed-typed property no longer type-checks:

```php
// Before (3.x): status was always a TransactionStatus
echo $payout->status->value;

// After (4.0): it may be a string the SDK has not heard of
echo $payout->status instanceof TransactionStatus ? $payout->status->value : $payout->status;

// or just compare
if ($payout->status === TransactionStatus::COMPLETED) { ... }
```

Request (input) enums are unchanged and stay closed. In a webhook, the event `type` follows the same rule (`WebhookEvents|string`).

## 4. List responses are `ListResponse`

`ListPayoutsResponse`, `ListPayinsResponse`, `ListTransfersResponse`, `ListCustomersResponse`, `ListPayablesResponse` and `ListWalletDepositsResponse` (the last two are new) now extend `BlindPay\SDK\Types\ListResponse`: `data` (a list) plus a **nullable** `pagination`, iterable and `Countable`. Both wire shapes (a bare array, or `{data, pagination}`) decode into it.

- `customers->list()` used to return a plain array of customers when the API answered with a bare array, and a `ListCustomersResponse` otherwise. It now always returns a `ListCustomersResponse`:

  ```php
  // Before (3.x)
  $result = $blindpay->customers->list()->data;
  $customers = $result instanceof ListCustomersResponse ? $result->data : $result;
  $first = $customers[0];

  // After (4.0)
  $page = $blindpay->customers->list()->getDataOrFail(); // always a ListCustomersResponse
  $first = $page->data[0]; // $page itself has no array access; iterate it or read ->data
  foreach ($page as $customer) { ... }
  ```

- `ListTransfersResponse::$pagination` was always set (transfers always return the envelope); it is now `?PaginationMetadata` like the others. Use `$page->hasMore()` or `$page->pagination?->nextPage`.
- Customer bank accounts still return `ListBankAccountsResponse` (a `data` list), and the other plain lists (blockchain wallets, virtual accounts, partner fees, webhook endpoints, ...) still return arrays.

## 5. `PaginationParams` no longer validates

The constructor used to throw `InvalidArgumentException` for a `limit` outside 10, 50, 100, 200, 500, 1000 and an `offset` outside a fixed list. It no longer checks anything: the API owns the allowed values (wallet deposits, for example, accept 5). Remove any `catch (InvalidArgumentException)` you relied on, and expect the API's own 400 for a bad value. `offset` is deprecated, because the API ignores it; use `startingAfter` or `listAutoPaging()`.

`toQueryString()` now never sends null or empty values and encodes names and values with RFC 3986.

## 6. Amounts are `int` cents

Payout, payin and quote amounts were already cents on the wire, but several inputs were typed `float`. They are now `int`, and a `float` argument is a `TypeError` in a `strict_types` file (even `1000.0`):

| Input | Field(s) | Before | After |
|---|---|---|---|
| `Quotes\CreateQuoteInput` | `requestAmount` | `float` | `?int` |
| `Quotes\GetFxRateInput` | `requestAmount` | `float` | `int` |
| `Payins\CreatePayinQuoteInput` | `requestAmount` | `float` | `int` |
| `Payins\GetPayinFxRateInput` | `requestAmount` | `float` | `int` |
| `PartnerFees\CreatePartnerFeeInput` | `payinFlatFee`, `payinPercentageFee`, `payoutFlatFee`, `payoutPercentageFee` | `float` | `int` (flat fees in cents, percentages in basis points: 100 is 1%) |
| `Customers\RequestLimitIncreaseInput` | `daily`, `monthly`, `perTransaction` | `?float` | `?int` (cents) |

```php
// Before (3.x)
new GetFxRateInput(CurrencyType::SENDER, Currency::USD, Currency::BRL, 1000.0);

// After (4.0): 1000 cents is 10.00 USD
new GetFxRateInput(CurrencyType::SENDER, Currency::USD, Currency::BRL, 1000);

// Converting from a decimal amount: round, never truncate
$cents = (int) round($dollars * 100);
```

Response amounts keep their types.

## 7. `CreateQuoteInput` was reshaped

`network` and `token` are now required and come first. `bankAccountId` and `payableId` are both optional (pass exactly one; the API checks it), `requestAmount` is `?int`, and the `transactionDocument*` fields are gone (they were never part of the quote request). `coverFees` and `partnerFeeId` default to `null`. Positional calls break; use named arguments:

```php
// Before (3.x)
new CreateQuoteInput(
    bankAccountId: 'ba_000000000000',
    currencyType: CurrencyType::SENDER,
    requestAmount: 1000.0,
    coverFees: null,
    partnerFeeId: null,
    transactionDocumentFile: null,
    transactionDocumentId: null,
    transactionDocumentType: TransactionDocumentType::INVOICE,
    network: Network::BASE,
    token: StablecoinToken::USDC,
);

// After (4.0)
new CreateQuoteInput(
    network: Network::BASE,
    token: StablecoinToken::USDC,
    bankAccountId: 'ba_000000000000',
    currencyType: CurrencyType::SENDER,
    requestAmount: 1000,
);
```

Related: the payout inputs now take `walletId` as an alternative to `senderWalletAddress` (exactly one; the API checks it), so `senderWalletAddress` is optional. Existing positional calls keep their meaning:

| Input | 3.x arguments | 4.0 arguments |
|---|---|---|
| `CreateEvmPayoutInput`, `AuthorizeStellarTokenInput` | `quoteId, senderWalletAddress` | `quoteId, ?senderWalletAddress, ?walletId` |
| `CreateStellarPayoutInput` | `quoteId, senderWalletAddress, ?signedTransaction` | `quoteId, ?senderWalletAddress, ?signedTransaction, ?walletId` |
| `CreateSolanaPayoutInput` | `quoteId, senderWalletAddress` | `quoteId, ?senderWalletAddress, ?signedTransaction, ?walletId` (`signedTransaction` is new) |

```php
// 3.x and 4.0: unchanged
new CreateStellarPayoutInput($quoteId, $senderAddress, $signedXdr);

// 4.0: pay from a managed wallet instead
new CreateEvmPayoutInput($quoteId, walletId: 'wa_000000000000');
```

## 8. Bank account creates return `BankAccountListItem`

The API returns the same bank account object for every rail. The per-rail response classes read fields as required that are not, so they are removed, and every `create*` method returns `BankAccountListItem` (as `get()` and `list()` already did):

Removed: `CreatePixResponse`, `CreateArgentinaTransfersResponse`, `CreateSpeiResponse`, `CreateColombiaAchResponse`, `CreateAchResponse`, `CreateWireResponse`, `CreateInternationalSwiftResponse`, `CreateRtpResponse`, `CreatePixSafeResponse` and `CreateSepaResponse`.

```php
// Before (3.x)
/** @var BankAccounts\CreatePixResponse $account */
$account = $blindpay->customers->bankAccounts->createPix($input)->data;

// After (4.0)
/** @var BankAccounts\BankAccountListItem $account */
$account = $blindpay->customers->bankAccounts->createPix($input)->getDataOrFail();
```

Update any type hint, `instanceof` or `@var` that named the old classes. `BankAccountListItem` now also carries the SEPA, Faster Payments, PIX Safe, status and Plaid fields. `createTed()` and `createFasterPayments()` are new.

`BankAccounts\SpeiProtocol::SPEI` and `::STP` are removed: the API only accepts `clabe`, `debitcard` and `phonenum` (use `SpeiProtocol::CLABE`, `::DEBITCARD` or `::PHONENUM`). The `Payouts\SpeiProtocol` enum is a different class and is unchanged.

## 9. Other changed request inputs

| Input | Change | Before | After |
|---|---|---|---|
| `PartnerFees\CreatePartnerFeeInput` | The wallet addresses were never part of the API and are removed. | `new CreatePartnerFeeInput($evmAddress, 'Acme', ...)` with `evmWalletAddress` and `stellarWalletAddress` | `new CreatePartnerFeeInput(name: 'Acme', payinFlatFee: 0, payinPercentageFee: 100, payoutFlatFee: 0, payoutPercentageFee: 100)` |
| `VirtualAccounts\CreateVirtualAccountInput` | `bankingPartner` is required (it was `?BankingPartner = null`). Also new: `partnerFeeId`, `breBGender`. | `new CreateVirtualAccountInput($customerId, $walletId, StablecoinToken::USDC)` | `new CreateVirtualAccountInput($customerId, $walletId, StablecoinToken::USDC, BankingPartner::...)` |
| `Customers\UpdateCustomerInput` | `email` and `country` are required (the API requires them). The stale `individualHoldingDocFrontFile` is removed. `owners` may hold `Owner` objects. | `new UpdateCustomerInput(customerId: $id, city: 'Austin')` | `new UpdateCustomerInput(customerId: $id, email: 'jane@example.com', country: Country::US, city: 'Austin')` |
| `Customers\CreateIndividualWithEnhancedKYCInput` | The stale required `individualHoldingDocFrontFile` is removed. Remove it from positional calls. | `individualHoldingDocFrontFile: $url` | (delete the argument) |
| `Customers\RequestLimitIncreaseInput` | The supporting document is optional; new `supportingDocuments` list. | `supportingDocumentFile` and `supportingDocumentType` required | both optional (`null` default) |
| `Wallets\CreateOfframpWalletInput` | `externalId` is nullable (still positional, third). New `partnerFeeId`. | `externalId: string` | `externalId: ?string` |
| `BankAccounts::list()` | Takes optional filters and options. | `list($customerId)` | `list($customerId, ?ListBankAccountsInput $params = null, ?RequestOptions $options = null)` |
| `Payins::getTrack()` | Takes the public checkout token. | `getTrack($id)` | `getTrack($id, ?string $checkoutToken = null, ?RequestOptions $options = null)` |
| `ListPayoutsInput`, `ListPayinsInput`, `ListTransfersInput` | Every filter the API declares is now an optional argument (`customerName`, `bankAccountId`, `country`, `paymentMethod`, `network`, `token`, `walletId`, ...). | `new ListPayoutsInput(customerId: $id, limit: 50)` | `new ListPayoutsInput(customerId: $id, limit: 50, status: TransactionStatus::COMPLETED)` (the 3.x call is unchanged). Existing named and leading positional arguments are unchanged. |
| `Payins\CreatePayinQuoteInput`, `Transfers\CreateTransferQuoteInput` | Payin quotes take the full payment-method enum and `fundingBankAccountId`; transfer quotes take `partnerPercentageFee` (basis points) and `partnerFlatFee` (cents). | `new CreateTransferQuoteInput($walletId, ...)` | `new CreateTransferQuoteInput($walletId, ..., partnerFlatFee: 50)`: additive, the 3.x call is unchanged. |

`BankAccounts::list()`, `Payins::getTrack()`, the trailing `?RequestOptions` and the nullable `externalId` only affect you if you subclass or mock these classes; callers are source compatible.

Note `Payins\PaymentMethod` (the payin quote enum) and `Types\PaymentMethod` are different enums.

## 10. Uploads are multipart

`POST /v1/upload` takes `multipart/form-data`; the JSON body 3.x sent never worked. `UploadInput` now takes an `UploadFile`, and the `Upload` resource is bound to your instance ID.

```php
// Before (3.x)
$blindpay->upload->create(new UploadInput(UploadBucket::ONBOARDING, $fileAsString));

// After (4.0)
$blindpay->upload->create(new UploadInput(
    UploadBucket::ONBOARDING,
    UploadFile::fromPath('/path/to/id.pdf'), // or new UploadFile($bytes, 'id.pdf', 'application/pdf')
));
```

`upload->analyze()` (multipart) and `upload->presign()` are new. If you built the resource by hand, `new Upload($client)` became `new Upload($instanceId, $client)`. Uploads send no idempotency key and are not retried once bytes were sent.

## 11. Removed methods and types

- `payouts->export()` and `payins->export()`, with `ExportPayoutsInput` and `ExportPayinsInput`. They called routes that do not exist and always failed. To walk every row, use `payouts->listAutoPaging()` / `payins->listAutoPaging()`.
- The ten per-rail bank account `Create*Response` classes (section 8).
- `BankAccounts\SpeiProtocol::SPEI` and `::STP`: the API never accepted them (`spei_protocol` is `clabe`, `debitcard` or `phonenum`):

```php
// 3.x (rejected by the API)
new CreateSpeiInput($customerId, $beneficiary, 'Main', $clabe, $institutionCode, SpeiProtocol::SPEI);

// 4.0
new CreateSpeiInput($customerId, $beneficiary, 'Main', $clabe, $institutionCode, SpeiProtocol::CLABE);
```

- `ListCustomersResponse::fromArray()`: list responses are built by `ListResponse::decode()` (`ListCustomersResponse::decode($body, Customers::customerFromArray(...))`); `customers->list()` returns the decoded page, so you never need to call either:

```php
// 3.x
$page = ListCustomersResponse::fromArray($json);

// 4.0
$page = ListCustomersResponse::decode($json, Customers::customerFromArray(...));
```
- `BankingPartner::SSB`: the API no longer has this banking partner. A response that still carried `ssb` decodes as the string `'ssb'` (open enums).
- `Payin::$BankDetails` and `CreateEvmPayinResponse::$BankDetails` are renamed `$blindpayBankDetails` (the wire key is `blindpay_bank_details`):

```php
// 3.x
$details = $payin->BankDetails;

// 4.0
$details = $payin->blindpayBankDetails;
```

## 12. Interfaces and constructors

You can skip this section unless you implement `ApiClientInterface` (a fake client in tests, say) or construct resources and wrappers yourself.

- **`ApiClientInterface`** methods take a trailing `?RequestOptions $options = null`, and there is a new `postMultipart(string $path, array $multipart, ?RequestOptions $options = null)`. Update implementations:

  ```php
  // Before (3.x)
  public function get(string $path): BlindPayApiResponse;
  public function post(string $path, array $body): BlindPayApiResponse;

  // After (4.0)
  public function get(string $path, ?RequestOptions $options = null): BlindPayApiResponse;
  public function post(string $path, array $body, ?RequestOptions $options = null): BlindPayApiResponse;
  // put, patch, delete likewise; plus postMultipart()
  ```

- **Every resource method** ends with `?RequestOptions $options = null` (idempotency key, timeout, extra headers) and forwards it. Existing calls are source compatible; subclasses and mocks of resource classes must add the parameter.
- **Wrappers extend their resource.** `PayinsWrapper`, `CustomersWrapper` and `InstancesWrapper` used to hand-delegate to a private `$base`; now they extend `Payins`, `Customers` and `Instances`, so every method is inherited. Their constructors changed, which matters only if you build them yourself:

  ```php
  // Before (3.x)
  new PayinsWrapper(new Payins($instanceId, $client), $quotes);
  new CustomersWrapper(new Customers($instanceId, $client), $bankAccounts);
  new InstancesWrapper(new Instances($instanceId, $client), $webhooks, $tos);

  // After (4.0)
  new PayinsWrapper($instanceId, $client, $quotes);
  new CustomersWrapper($instanceId, $client, $bankAccounts, $rfi);
  new InstancesWrapper($instanceId, $client, $webhooks, $tos, $onboarding, $rfi);
  ```

- **`Customers::customerFromArray()`** is a new public static method: the customer decoder (previously a private `mapCustomer()`), also used for `customer.new` and `customer.update` webhooks.
- **`BlindPay::verifyWebhookSignature()`** still works but is deprecated: use `WebhookVerifier::verify()` or `WebhookVerifier::constructEvent()`.

## 13. Autoloading

Composer now also autoloads `src/` by **classmap**, because several types are declared in their resource's file (`CreateEvmPayoutInput` lives in `Payouts.php`) and must load without the client, for example in a webhook handler. `composer install` and `composer require` handle this. If you copy the package into your project without Composer's installer (vendoring by hand, a mono-repo path copy), run:

```bash
composer dump-autoload
```

## New in 4.0

You do not need any of this to upgrade, but you probably want some of it:

- **Per-request options:** `new RequestOptions(idempotencyKey: ..., timeout: ..., headers: [...])` as the last argument of any method.
- **Configuration:** `baseUrl`, `timeout`, `maxRetries` and an injectable Guzzle `handler`.
- **Pagination generators:** `listAutoPaging()` on payouts, payins, customers, transfers and payables, and `wallets->custodial->getDepositsAutoPaging()`.
- **Typed webhooks:** `WebhookVerifier::constructEvent()` returns a `WebhookEvent` with a typed `data` for every event (an unknown event never throws).
- **Eighteen new operations:** `payables` (`create`, `list`, `get`, `delete`, `listAutoPaging`), `instances->get()`, `instances->onboarding->*`, `instances->rfi` and `customers->rfi` (`get`, `submit`), `customers->bankAccounts->createPlaidLink()`, `wallets->blockchain->setSelfCustody()`, `wallets->offramp->update()`, `wallets->custodial->getDeposits()`, `upload->analyze()` and `upload->presign()`.
- **`customers->create()`** for any customer type and KYC level (`CreateCustomerInput`).
- **Open response enums** (section 3) and a wider set of response fields.
