# CLAUDE.md -- BlindPay PHP SDK

This file is for AI agents modifying this codebase. Follow these conventions exactly.

The shapes of requests and responses come from the BlindPay OpenAPI spec (`.api-sync/spec-snapshot.json`), not from the docs. When code and spec disagree, the spec wins.

## 1. Project Structure

```
blindpay-php/
  composer.json              # Package: blindpay/php, PHP ^8.2, Guzzle ^7.9. Autoloads src/ by PSR-4 and by classmap.
  pint.json                  # Laravel Pint config (linter)
  phpunit.xml.dist           # PHPUnit config
  phpstan.neon.dist          # PHPStan level 7 over src/ and examples/ (phpVersion 80200)
  README.md                  # User docs: configuration, results, idempotency, retries, pagination, webhooks
  UPGRADING.md               # 3.x -> 4.0 migration guide (every breaking change, before/after)
  RELEASING.md               # Release runbook: VERSION, release.yaml, secrets, stuck runs, verifying Packagist
  examples/                  # Runnable scripts (functions only, nothing runs on load); not in the Composer archive
  scripts/
    api-sync.php             # Patches the SDK from the OpenAPI spec (api-sync workflow)
    contract-check.php       # Checks the wire keys the SDK reads/writes against the spec snapshot
  .api-sync/                 # api-sync state, see section 10
  src/
    BlindPay.php             # Main client. Implements ApiClientInterface, builds Transport and every resource in its constructor.
    BlindPayException.php    # Public base of every SDK exception (extends Internal\BlindPayException for old catch blocks)
    BlindPayApiException.php # Thrown by getDataOrFail() and the listAutoPaging() generators; carries the ErrorResponse
    Internal/                # @internal: not public API
      ApiClientInterface.php # get, post, put, patch, delete, postMultipart (each takes ?RequestOptions)
      Transport.php          # Guzzle client: headers, idempotency keys, timeout, retries, response/error decoding
      RetryPolicy.php        # The retry decision table and backoff (pure functions)
      ErrorGuard.php         # Runs a decoder with PHP warnings raised as exceptions (map(), WebhookEvent)
      AutoPager.php          # AutoPager::iterate(): the cursor-list generator behind every listAutoPaging()
      Path.php               # Path::of('instances/%s/...', $id): percent-encodes path segments
      Query.php              # Query::build($params): the one query-string helper
      Decode.php             # Decode::enum / Decode::wire (open enums), listEnvelope
      Uuid.php               # UUIDv4 for generated idempotency keys
      BlindPayException.php  # Older exception base; throw and catch the public \BlindPay\SDK\BlindPayException
    Webhooks/
      WebhookVerifier.php    # Standalone Svix verifier: verify() and constructEvent() (no API client needed)
      WebhookEvent.php       # Typed event {type, data, raw}; unknown events keep the raw name and null data
      WebhookPayloads.php    # Payload types with no resource class (CustomerDeletedPayload, TosAcceptedPayload, WalletInboundPayload)
      WebhookException.php
    Resources/               # Each subdirectory = one API resource domain
      Available/Available.php
      BankAccounts/BankAccounts.php
      CustodialWallets/CustodialWallets.php
      Customers/
        Customers.php        # Resource class + inline types; Customers::customerFromArray() is the public customer decoder
        CustomersWrapper.php # extends Customers; adds ->bankAccounts and ->rfi
      Fees/Fees.php
      Instances/
        Instances.php
        InstancesWrapper.php # extends Instances; adds ->webhookEndpoints, ->tos, ->onboarding, ->rfi
        Onboarding.php
      Ownership/Ownership.php
      PartnerFees/PartnerFees.php
      Payables/Payables.php
      Payins/
        Payins.php
        PayinsWrapper.php    # extends Payins; adds ->quotes
        Quotes.php           # payin quotes (class Quotes, namespace ...\Payins; a different class from Resources\Quotes\Quotes)
      Payouts/Payouts.php
      Quotes/Quotes.php
      Rfi/Rfi.php            # InstanceRfis and CustomerRfis
      TermsOfService/TermsOfService.php
      Transfers/Transfers.php
      Upload/Upload.php      # multipart upload / analyze, JSON presign; UploadFile
      VirtualAccounts/VirtualAccounts.php
      Wallets/
        WalletsWrapper.php   # Aggregation only: ->blockchain, ->offramp, ->custodial
        BlockchainWallets.php
        OfframpWallets.php
      Webhooks/Webhooks.php  # Webhook endpoint management + the WebhookEvents enum
    Types/                   # Shared types used across multiple resources
      BlindPayApiResponse.php  # Result of every method: data or error, requestId, idempotencyReplayed, map(), getDataOrFail()
      ErrorResponse.php        # status, code, description, errors, traceId, requestId, idempotencyKey, retryable, type, headers
      ErrorType.php            # enum: api, network, validation, decode
      ErrorCode.php            # GENERATED by scripts/api-sync.php: documented API error codes + ErrorCode::info()
      ErrorCodeInfo.php, RetryClass.php  # ErrorCode::info() result; ErrorResponse::retryClass()
      RequestOptions.php       # idempotencyKey, timeout, headers
      ListResponse.php         # Base of every list response: data + nullable pagination, both wire shapes
      PaginationParams.php     # Base class for paginated list inputs (does not validate)
      PaginationMetadata.php
      BaseTracking.php, Tracking*.php, PayinTrackingPayment.php, PayoutTrackingPayment.php
      <Name>.php               # Shared enums (Network, StablecoinToken, Currency, Rail, TransactionStatus, Country, ...)
  tests/
    Internal/                # TransportTest (retry table, idempotency, errors), AutoPagerTest, DecodeTest, RequestOptionsGuardTest
    Webhooks/                # WebhookVerifierTest (shared Svix vectors), WebhookEventTest (every webhook fixture variant)
    Resources/               # One test file per resource, mirroring src/Resources; RequestBodiesTest checks request bodies
    SpecFixtures/SpecFixtureDecodeTest.php  # decodes every shared spec fixture through the resource methods
    ApiSync/ApiSyncTest.php  # scripts/api-sync.php
    Fixtures/                # shared spec fixtures (spec/) and webhook vectors; never edit by hand
```

## 2. Conventions

### Naming

- **Directories**: PascalCase matching the resource domain name (e.g., `Payins/`, `BankAccounts/`)
- **Files**: PascalCase matching the primary class name (e.g., `Payins.php`, `PayinsWrapper.php`)
- **Namespaces**: `BlindPay\SDK\Resources\{ResourceDir}` for resources, `BlindPay\SDK\Types` for shared types
- **Classes**: PascalCase. Response types often end with `Response`. Input types end with `Input`.
- **Properties**: camelCase in PHP, snake_case in API JSON. The `fromArray`/`toArray` methods handle conversion.
- **Methods on resource classes**: camelCase verbs matching the API action (`list`, `listAutoPaging`, `get`, `create`, `update`, `delete`, `getTrack`)
- **Enums**: PascalCase name, UPPER_SNAKE_CASE cases, backed by `string` type

### PHP Patterns

- Every PHP file starts with `declare(strict_types=1);`
- All type/data classes are `readonly class` with promoted constructor properties
- Resource classes (the class with API methods) are NOT readonly -- they use `private readonly` for constructor params
- Shared enums live in `src/Types/`. Resource-local enums live inline in the resource file.
- All API methods return `BlindPayApiResponse<T>` (documented with a `@return BlindPayApiResponse<T>` docblock; the generic reaches PHPStan and IDEs only from a `/** */` comment, never `/* */`). The two exceptions are the `listAutoPaging()` / `getDepositsAutoPaging()` generators, which return `Generator`.
- Types declared inline in resource files load through the Composer classmap, not PSR-4 (see section 8).
- No `toArray()` on response types (only `fromArray`). Input types have `toArray()` that returns the wire keys, because `scripts/contract-check.php` reads the `'key' =>` entries of every `toArray()`. Some types have both.

### Resource method conventions (every method, no exceptions)

1. The **last parameter** is `?RequestOptions $options = null`, and the method **forwards it** to the client call. `tests/Internal/RequestOptionsGuardTest` fails for a method without it, and `SpecFixtureDecodeTest` fails for one that does not forward it.
2. **Paths** are built with `Internal\Path::of('instances/%s/customers/%s', $this->instanceId, $customerId)`, never string interpolation: it percent-encodes each segment, so an ID cannot change the route.
3. **Query strings** are built with `Internal\Query::build([...])`, which drops null and empty values and RFC 3986-encodes the rest. Never `http_build_query` or hand-concatenation.
4. **Decoding** is `return $this->client->get(...)->map(fn (array $data) => X::fromArray($data));`. `map()` turns any decode failure (TypeError, ValueError, undefined key) into an `ErrorType::DECODE` error and passes errors through untouched. Never branch on `isSuccess()` and decode by hand. A call with no response body (a 204 `DELETE`) returns the client result as is, without `map()`.
5. **Client-side validation** returns `BlindPayApiResponse::error(ErrorResponse::validation('Customer ID cannot be empty'))`. Check IDs with `=== ''` before building the path (not `empty()`, which also rejects `'0'`; the api-sync generator emits the same check). Do **not** validate things the API validates (enum-like values, "exactly one of" inputs, `limit` ranges): the server answers 400, and the SDK must keep working when its rules change.
6. Retries live in `Internal\Transport::send()`, an explicit loop driven by `RetryPolicy`, not in Guzzle's `Middleware::retry`. The loop has to generate the idempotency key once per call before attempt 0, serialise the body once, tell "a status line arrived, then the transfer failed" apart from "no response", sleep through an injectable `sleep` (tests never really sleep), and put the key and the `retryable` flag on the final `ErrorResponse`; the middleware's decider/delay callbacks see none of that state. Callers can still push their own middleware through the `handler` stack.
7. Methods never throw for API, network or decoding failures. Only `getDataOrFail()` and the auto-paging generators throw (`BlindPayApiException`).

### fromArray Pattern (response/data types)

```php
readonly class ExampleResponse
{
    public function __construct(
        public string $id,
        public TransactionStatus|string $status,
        public Network|string $network,
        public ?string $optionalField = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: Decode::enum(TransactionStatus::class, $data['status']),
            network: Decode::enum(Network::class, $data['network']),
            optionalField: $data['optional_field'] ?? null
        );
    }
}
```

Key rules:
- Required fields: access directly as `$data['key']`. "Required" means listed in the spec schema's `required` AND not nullable; anything else is optional (`$data['key'] ?? null`).
- **Every enum in a response type is open**: `Decode::enum(EnumName::class, $data['key'])`, typed `EnumName|string`. A value the SDK does not know is kept as the raw string, so a new API value never fails a whole response. Optional: `Decode::enum(EnumName::class, $data['key'] ?? null)`, typed `EnumName|string|null`. In a `toArray()` of such a type, serialise with `Decode::wire($this->field)`, never a hand-written `instanceof` check. Never `EnumName::from()` on response data.
  - Exceptions, kept as plain `string` on purpose (open-ended value sets, listed in `.api-sync/known-divergences.json`): `swift_payment_code` and `business_industry` on `BankAccountOut`, and bank account `status`.
  - When the API starts returning a value an enum lacks, add the case (the open decoding keeps working before and after).
  - **Request (input) enums stay closed**, for type safety.
- A type shared by input and output (e.g. `Customers\Owner`) keeps its constructor as strict as the request spec. If a response may omit a field the request requires, keep the constructor parameter non-nullable, declare the property nullable, and let `fromArray()` set the properties directly (see `Owner::fromArray`).
- Every response type must decode all variants of its shared spec fixture (`tests/Fixtures/spec`: `full`, `required_only`, `nullables_null`, `unknown_enum`, `decimals`), exercised by `tests/SpecFixtures/SpecFixtureDecodeTest.php`.
- Nested objects: `NestedType::fromArray($data['key'])`; optional: `isset($data['key']) ? NestedType::fromArray($data['key']) : null`
- Float fields: `(float) $data['key']`. Cent amounts stay integers (`(int)`); wallet balances are decimal whole tokens (`float`).
- DateTime fields: `new DateTimeImmutable($data['key'])`
- Array of objects: `array_map(fn (array $item) => Type::fromArray($item), $data['key'])`
- JSON keys are snake_case; PHP properties are camelCase. A few spec keys are not snake_case (`functionName`, `blindpayContractAddress`, `chainId`, `countrySlug`, `swiftCode`, `swiftCodeLink`, `requiredWhen`, `signedXdr`): keep them exactly.

### toArray Pattern (input types)

```php
readonly class ExampleInput
{
    public function __construct(
        public string $requiredField,
        public EnumType $enumField,
        public ?string $optionalField = null
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'required_field' => $this->requiredField,
            'enum_field' => $this->enumField->value,
            'optional_field' => $this->optionalField,
        ], static fn ($value) => $value !== null);
    }
}
```

Key rules:
- Required fields are typed non-nullable and always present; optional fields default to `null` and are omitted when null (do not send nulls, unless the spec uses `null` to clear a value, as `UpdateOfframpWalletInput::partnerFeeId` does).
- Enum values: use `->value` to serialize. Nested objects with toArray: use `$this->nested->toArray()`.
- Units: amounts are **integer cents** (`int`), partner percentage fees are **basis points** (`int`), partner flat fees are cents; wallet balances in responses are whole tokens. Document the unit in a docblock on the field.
- "Exactly one of" inputs (payout `senderWalletAddress` / `walletId`, quote `bankAccountId` / `payableId`) keep both fields optional and say "exactly one" in the docblock. No client-side check.

### Paginated List Input Pattern

Extend `PaginationParams`, forward its four arguments to `parent::__construct()`, and merge filters into `parent::toArray()`:

```php
readonly class ListExampleInput extends PaginationParams
{
    public function __construct(
        public ?string $filterField = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $startingAfter = null,
        ?string $endingBefore = null
    ) {
        parent::__construct($limit, $offset, $startingAfter, $endingBefore);
    }

    public function toArray(): array
    {
        return parent::toArray() + array_filter([
            'filter_field' => $this->filterField,
        ], static fn ($value) => $value !== null);
    }
}
```

`PaginationParams` does not validate `limit` or `offset`: the API owns the allowed values (and the wallet deposits list accepts different ones). `offset` is deprecated because the API ignores it; do not add it to new inputs. Include **every filter the spec declares** for the operation.

### Paginated List Response Pattern

The API returns a bare array when no pagination parameter is sent and `{data, pagination}` when `limit`, `starting_after` or `ending_before` is. Every list response extends `Types\ListResponse`, which decodes both:

```php
/**
 * @extends ListResponse<Example>
 */
readonly class ListExampleResponse extends ListResponse
{
    /** Accepts both shapes the API returns (see ListResponse). */
    public static function fromArray(array $data): self
    {
        return self::decode($data, Example::fromArray(...));
    }
}
```

Cursor lists (payouts, payins, customers, transfers, payables, wallet deposits) also get `listAutoPaging()`, a thin wrapper over `Internal\AutoPager::iterate()`:

```php
public function list(?ListExampleInput $params = null, ?RequestOptions $options = null): BlindPayApiResponse
{
    return $this->listPage($params?->toArray() ?? [], $options);
}

/** @return Generator<int, Example> */
public function listAutoPaging(?ListExampleInput $params = null, ?RequestOptions $options = null): Generator
{
    return AutoPager::iterate($params?->toArray() ?? [], fn (array $query) => $this->listPage($query, $options));
}

private function listPage(array $query, ?RequestOptions $options): BlindPayApiResponse
{
    return $this->client->get(Path::of('instances/%s/examples', $this->instanceId).Query::build($query), $options)
        ->map(fn (array $data) => ListExampleResponse::fromArray($data));
}
```

The pager is forward only (`ending_before` throws `BlindPayException`), asks for `limit=100` unless set, and throws `BlindPayApiException` when a page fails. Lists that are always plain arrays (blockchain wallets, virtual accounts, partner fees, webhook endpoints, ...) are not paged: decode them into a PHP array with `array_map`.

## 3. How To: Add a New Resource

Suppose the API adds a new `invoices` resource at `instances/{instanceId}/invoices`.

### Step 1: Create the directory and resource file

Create `src/Resources/Invoices/Invoices.php`:

```php
<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Invoices;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\RequestOptions;

// -- Define all types inline, above the resource class --

readonly class Invoice
{
    public function __construct(
        public string $id,
        public string $instanceId
        // ... all fields
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            instanceId: $data['instance_id']
        );
    }
}

// Input/Response types as needed...

class Invoices
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /**
     * List invoices
     *
     * @return BlindPayApiResponse<Invoice[]>
     */
    public function list(?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->client->get(Path::of('instances/%s/invoices', $this->instanceId), $options)
            ->map(fn (array $data) => array_map(fn (array $item) => Invoice::fromArray($item), $data));
    }

    /**
     * Get an invoice
     *
     * @return BlindPayApiResponse<Invoice>
     */
    public function get(string $invoiceId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($invoiceId === '') {
            return BlindPayApiResponse::error(ErrorResponse::validation('Invoice ID cannot be empty'));
        }

        return $this->client->get(Path::of('instances/%s/invoices/%s', $this->instanceId, $invoiceId), $options)
            ->map(fn (array $data) => Invoice::fromArray($data));
    }
}
```

### Step 2: Register in BlindPay.php

1. Add the `use` import at the top, and the public property:
   ```php
   use BlindPay\SDK\Resources\Invoices\Invoices;

   public readonly Invoices $invoices;
   ```

2. Build it in `__construct()`, after the transport exists:
   ```php
   $this->invoices = new Invoices($this->instanceId, $this);
   ```

### Step 3: Create test file, regenerate the classmap, register the operations

1. Create `tests/Resources/Invoices/InvoicesTest.php` following the test pattern (see section 8).
2. Run `composer dump-autoload`: the Composer classmap must learn the new file (see section 8).
3. Add every new operation to `calls()` in `tests/SpecFixtures/SpecFixtureDecodeTest.php` (see section 8).

### Note on resources without instanceId

If the resource does not need the instance ID (like `Available`), the constructor takes only `ApiClientInterface`:

```php
class Available
{
    public function __construct(
        private readonly ApiClientInterface $client
    ) {}
}
```

And in BlindPay.php: `$this->available = new Available($this);`

## 4. How To: Add a Method to an Existing Resource

1. Define any new input/response types inline in the resource file (above the resource class).
2. Add the method to the resource class following this pattern:

```php
/**
 * @return BlindPayApiResponse<NewMethodResponse>
 */
public function newMethod(NewMethodInput $input, ?RequestOptions $options = null): BlindPayApiResponse
{
    return $this->client->post(
        Path::of('instances/%s/resource-path', $this->instanceId),
        $input->toArray(),
        $options
    )->map(fn (array $data) => NewMethodResponse::fromArray($data));
}
```

3. Wrappers (`PayinsWrapper`, `CustomersWrapper`, `InstancesWrapper`) **extend** their base resource, so a new base method is inherited: add nothing to the wrapper.
4. Add the operation to `SpecFixtureDecodeTest::calls()` and a request-body test (see section 8).

### Method patterns by HTTP verb

- **GET single**: `$this->client->get(Path::of('...%s', ...), $options)` -- validate the ID is not empty first
- **GET list**: `$this->client->get(Path::of('...').Query::build($params?->toArray() ?? []), $options)`
- **POST create**: `$this->client->post($path, $input->toArray(), $options)`
- **PUT update**: `$this->client->put($path, $input->toArray(), $options)`
- **PATCH update**: `$this->client->patch($path, $input->toArray(), $options)`
- **DELETE**: `$this->client->delete($path, null, $options)`
- **Multipart** (uploads): `$this->client->postMultipart($path.Query::build(['instance_id' => $this->instanceId]), $parts, $options)`. Build parts in the input's `toArray()` (`UploadFile::parts([...])`). Multipart requests never carry an idempotency key.

### ID validation pattern

For methods accepting an entity ID as a string parameter, validate before making the request:

```php
if ($entityId === '') {
    return BlindPayApiResponse::error(ErrorResponse::validation('Entity ID cannot be empty'));
}
```

## 5. How To: Modify Types

### Adding a property to an existing type

1. Add the property to the constructor (put optional/nullable properties at the end with defaults).
2. Update `fromArray()` to deserialize the new field (open enums via `Decode::enum`).
3. Update `toArray()` if the type has one.

### Adding an enum case

Add the new case to the enum. Enum cases use UPPER_SNAKE_CASE names and lowercase snake_case string values (matching the API):

```php
enum TransactionStatus: string
{
    case REFUNDED = 'refunded';
    case PROCESSING = 'processing';
    // Add new case:
    case CANCELLED = 'cancelled';
}
```

Response decoding is open, so nothing else changes. If the enum backs a webhook event, also add it to `WebhookEvent::decode()`.

### Adding a new shared enum

Create a new file in `src/Types/` with the enum following the existing pattern, then run `composer dump-autoload` if it is a new file.

### Adding a new shared type (non-enum)

Create a new file in `src/Types/`. Use `readonly class` with `fromArray()` and optionally `toArray()`.

### Resource-local types vs shared types

- If a type is used only within one resource file, define it inline in that resource file.
- If a type is used across multiple resources, place it in `src/Types/`.

## 6. How To: Remove a Resource

1. Delete the resource directory under `src/Resources/`.
2. Remove the `use` import, property declaration, and initialization from `src/BlindPay.php`.
3. If the resource was part of a wrapper, also remove it from the wrapper constructor.
4. Delete corresponding test files under `tests/Resources/` and its entries in `SpecFixtureDecodeTest::calls()`.
5. Run `composer dump-autoload`. Removing a public method or type is a breaking change: see section 9.

## 7. How To: Add a Sub-Resource (Wrapper Pattern)

Sub-resources are used when a resource has logically grouped child endpoints. There are two wrapper patterns.

### Pattern A: Extending wrapper (Payins, Customers, Instances)

The wrapper **extends** the base resource, so it inherits every method, and exposes sub-resources as public readonly properties. There are no delegation methods to maintain.

```php
class PayinsWrapper extends Payins
{
    public function __construct(
        string $instanceId,
        ApiClientInterface $client,
        public readonly Quotes $quotes, // accessed as $blindpay->payins->quotes->create(...)
    ) {
        parent::__construct($instanceId, $client);
    }
}
```

In `BlindPay::__construct()`:

```php
$this->payins = new PayinsWrapper($this->instanceId, $this, new PayinQuotes($this->instanceId, $this));
```

and type the client property as the wrapper (`public readonly PayinsWrapper $payins;`).

### Pattern B: Aggregation wrapper (Wallets)

The wrapper only groups sub-resources; there are no base methods.

```php
readonly class WalletsWrapper
{
    public readonly BlockchainWallets $blockchain;
    public readonly OfframpWallets $offramp;
    public readonly CustodialWallets $custodial;

    public function __construct(
        BlockchainWallets $blockchainResource,
        OfframpWallets $offrampResource,
        CustodialWallets $custodialResource
    ) {
        $this->blockchain = $blockchainResource;
        $this->offramp = $offrampResource;
        $this->custodial = $custodialResource;
    }
}
```

Usage: `$blindpay->wallets->blockchain->list($customerId)`

## 8. Testing

### Framework

Tests use **Pest v3** (built on PHPUnit). Test files use PHPUnit-style classes with `#[Test]` attributes. PHP is not installed on the dev machines: run everything in Docker (see the commands below).

### Commands

```bash
composer run test             # Run all tests
composer run test:coverage    # Run tests with coverage
composer run lint:check       # Check code style (Laravel Pint)
composer run lint:fix         # Auto-fix code style
composer run analyse          # PHPStan level 7 over src/ and examples/
composer run contract-check   # SDK wire keys vs the spec snapshot
composer run api-sync:check   # SDK vs the spec snapshot (what the api-sync workflow checks)
composer dump-autoload        # After adding or removing any src/ file
```

Without a local PHP, wrap any of them:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer run test
```

CI (`tests.yaml`) runs `composer validate --strict`, Pest and PHPStan on PHP 8.2, 8.4 and 8.5. `composer.lock` must install on all three: run `composer update` under PHP 8.2, the floor, never under a newer PHP (the `composer:2` image ships the newest PHP). A lock that pinned `brianium/paratest` 7.8.4 (`~8.2 || ~8.3 || ~8.4`) could not install on 8.5.

### Autoloading: run `composer dump-autoload` after adding a file under `src/`

`composer.json` autoloads `src/` by PSR-4 **and** by classmap. Many types are declared inline in their resource file (`CreateEvmPayoutInput` lives in `Payouts.php`), which PSR-4 cannot find on its own; the classmap makes them loadable without first touching the resource class (for example in a webhook handler that builds no client). The classmap is generated, so a new file (or a new class in an existing one) is invisible to tests until you run `composer dump-autoload`.

### Test file pattern

Tests inject a Guzzle handler through the constructor. Never use reflection on the client.

```php
<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class ExampleTest extends TestCase
{
    private BlindPay $blindpay;

    private MockHandler $mockHandler;

    private array $history = [];

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler;
        $stack = HandlerStack::create($this->mockHandler);
        $stack->push(Middleware::history($this->history));

        $this->blindpay = new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000', handler: $stack);
    }

    private function mockResponse(array $body, int $status = 200): void
    {
        $this->mockHandler->append(
            new Response($status, ['Content-Type' => 'application/json'], json_encode($body))
        );
    }

    private function request(): RequestInterface
    {
        return $this->history[0]['request'];
    }

    #[Test]
    public function it_does_something(): void
    {
        $this->mockResponse([/* mock API response data */]);

        $response = $this->blindpay->resource->method();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertSame('GET', $this->request()->getMethod());
        // Assert on $response->data properties...
    }
}
```

Key points:
- Use `MockHandler` to mock HTTP responses (no real API calls) and `Middleware::history` to assert the method, path, headers and body that were sent.
- Retries are on by default: either pass `maxRetries: 0`, or inject `sleep: fn (float $seconds) => ...` so no test really sleeps (see `tests/Internal/TransportTest.php`).
- Test method names start with `it_` and describe the behavior.
- Mock data should match the API's snake_case JSON structure. Use placeholder ids only (`test_key`, `in_000000000000`, `cu_000000000000`): no real keys, customer data or personal emails.
- Test namespace: resource tests under `tests/Resources/` all use `BlindPay\SDK\Tests\Resources`. Tests in the other folders (`Internal/`, `Webhooks/`, `SpecFixtures/`, `ApiSync/`) use the PSR-4 namespace of their folder, e.g. `BlindPay\SDK\Tests\Webhooks`.

### The spec fixture harness (every operation must be in it)

`tests/SpecFixtures/SpecFixtureDecodeTest.php` is the proof that the SDK matches the API. Its `calls()` table maps **every** spec operation (`'GET /v1/instances/{id}/payouts'`) to a closure that calls the SDK method. For each operation the harness:

- asserts the HTTP verb and path, and that the `RequestOptions` it passed reach the wire (idempotency key, timeout, headers);
- decodes **every variant** of the operation's shared response fixture (`full`, `required_only`, `nullables_null`, `unknown_enum`, `decimals`) through the real method, and fails on any decode error.

When you add an operation, add it to `calls()`. When you add a response type, make it decode all variants. `PENDING` (operations that do not decode every variant yet, with a reason) must **stay empty**: do not add to it, fix the type. Fixtures under `tests/Fixtures/` are shared across the BlindPay SDKs: never edit them by hand.

Other guard tests: `RequestOptionsGuardTest` (every resource method takes a trailing `?RequestOptions $options = null`), `RequestBodiesTest` (request bodies are exactly the spec's), `WebhookEventTest` (every webhook fixture variant decodes, unknown events do not fail), `WebhookVerifierTest` (the shared Svix vectors).

## 9. Versioning

### VERSION constant

`src/BlindPay.php` has a `private const VERSION` that is sent in the `User-Agent` header (`blindpay-php/{VERSION}`):

```php
private const VERSION = '4.1.0';
```

Keep the exact form `private const VERSION = 'X.Y.Z';`: `scripts/api-sync.php` rewrites it with a regular expression when it bumps the version, and `.github/workflows/release.yaml` reads it on every push to `main` to decide whether to tag a release (it tags only when `vX.Y.Z` does not exist yet).

### Release process

The full runbook (secrets, stuck runs, verifying Packagist) is [RELEASING.md](RELEASING.md).

1. Bump `VERSION` in `src/BlindPay.php` in the PR itself, following the SemVer table below. api-sync PRs bump it automatically.
2. Merge to `main`. `.github/workflows/release.yaml` then reads `VERSION`. If tag `vX.Y.Z` doesn't exist yet, it validates, runs the tests and contract check, pushes the tag and creates the GitHub release. Never tag by hand.
3. Packagist picks up the tag through its GitHub webhook. Composer resolves the version from git tags, not from `composer.json`.

### SemVer rules for this SDK

| Change | Bump |
|--------|------|
| New resource, new optional field, new enum case, new error code | MINOR |
| Bug fix, refactor, typo | PATCH |
| Remove field, add required field, rename class/method, change return type or an input type | MAJOR |

A MAJOR change needs an entry in `UPGRADING.md` with a before and after snippet, and a `feat!:` / `fix!:` conventional commit. Removing a public method or type is breaking even if it always failed.

`scripts/api-sync.php --apply` follows the same table: an added field, enum case, operation or error code bumps MINOR; a change that only refreshes generated data (an error code's status, retry class or description) bumps PATCH. See [RELEASING.md](RELEASING.md) for what a merge to `main` triggers.

## 10. api-sync

The `api-sync` workflow compares the SDK with the OpenAPI spec and opens a PR that patches it (`scripts/api-sync.php`). `contract-check` verifies the wire keys in every `fromArray()` / `toArray()`. Their state lives in `.api-sync/`:

| File | Purpose |
|---|---|
| `spec-snapshot.json` | The committed OpenAPI spec both tools compare against. Refresh it when the reference spec changes. `--apply` copies a pretty-printed source byte for byte and pretty-prints (2-space) a minified one, so refreshes stay small diffs. |
| `spec-map.json` | Maps spec schemas and enums to SDK classes (`types`, `enums`, `ignore`). A new SDK class for a spec schema needs an entry. |
| `unmodeled.json` | Spec fields the SDK deliberately does not model yet, each with a reason and an owner. A field the SDK now models must be removed from it; `--check` fails on drift that is in neither the SDK nor this file. |
| `known-divergences.json` | Recorded, reasoned differences from the spec (a string-typed enum, an SDK case that differs from the spec value). |
| `contract-allowlist.json` | Wire keys that `contract-check` should not require in the spec (legacy shapes, SDK-only keys), each with a reason. |

`src/Types/ErrorCode.php` is generated from the Markdown table in the spec's `Errors` tag (code, status, retry, description columns): `--check` fails when a code is added or changes, `--apply` regenerates the file, and a code that leaves the spec needs a human (removing a constant is breaking). Never edit it by hand.

Every `*WebhookOut` schema is mapped in `spec-map.json` to the class `WebhookEvent` decodes it with, so a field added to a webhook payload alone fails `--check` too.

Do not add new typed fields for provider-named spec fields (`checkbook_*`, `onemoney_*`, `circle_wallet_id`, `aiprise_*`): record them in `unmodeled.json`.

## 11. OpenAPI to SDK Mapping Rules

When translating an OpenAPI spec change to SDK code:

### Endpoints to resource methods

| OpenAPI | SDK |
|---------|-----|
| `GET /v1/instances/{id}/things` | `$blindpay->things->list()` (plus `listAutoPaging()` for cursor lists) |
| `GET /v1/instances/{id}/things/{thingId}` | `$blindpay->things->get($thingId)` |
| `POST /v1/instances/{id}/things` | `$blindpay->things->create($input)` |
| `PUT /v1/instances/{id}/things/{thingId}` | `$blindpay->things->update($input)` |
| `PATCH /v1/instances/{id}/things/{thingId}` | `$blindpay->things->update($input)` (thingId inside input) |
| `DELETE /v1/instances/{id}/things/{thingId}` | `$blindpay->things->delete($thingId)` |

Every method also takes a trailing `?RequestOptions $options = null`.

### URL path segments

- The base URL `https://api.blindpay.com/v1/` is set by the transport. Resource methods use relative paths through `Path::of('instances/%s/things', $this->instanceId)`.
- The `instanceId` is stored on the resource class and passed as the first `Path::of` segment.
- Sub-resource IDs (e.g., customer ID) come from method parameters or input objects, and also go through `Path::of`.

### Schema to types

| OpenAPI schema | PHP |
|----------------|-----|
| `type: string` | `string` |
| `type: number` (a decimal) | `float` (cast with `(float)`) |
| `type: number` (cents or basis points) | `int` (cast with `(int)`) |
| `type: integer` | `int` (cast with `(int)`) |
| `type: boolean` | `bool` |
| `type: string, format: date-time` | `DateTimeImmutable` |
| `type: string, enum: [...]` | PHP `enum` backed by string; open (`Enum\|string` via `Decode::enum`) in responses, closed in requests |
| `type: object` | `readonly class` with `fromArray()` |
| `type: array, items: { $ref }` | `array` with `array_map(fn => Type::fromArray(...))` |
| `nullable: true` or field not in `required` | `?Type` with `= null` default, `$data['key'] ?? null` in fromArray |

### Request body to input type

- Create a `readonly class` with `toArray()` that converts to the API's snake_case format.
- Required properties come first, optional properties last with `= null` defaults, and request fields follow the spec's `required` list exactly.

### Response body to response type

- Create a `readonly class` with `fromArray()`.
- Response types generally do NOT need `toArray()`.

### Query parameters

- For GET endpoints with query params, create an input class with a `toArray()` that returns the parameters, and pass it to `Query::build()`. If it supports pagination, extend `PaginationParams` and add `listAutoPaging()`.

### Nested endpoints

Nested endpoints like `instances/{id}/customers/{customerId}/bank-accounts` are modeled as sub-resources accessed via wrappers: `$blindpay->customers->bankAccounts->list($customerId)`.

### Track endpoints

`getTrack()` methods call `/v1/e/*`, the public tracking endpoints of BlindPay's web and iOS apps. A server-side call gets 401 in production: keep the docblock note ("use `get()` instead") and test them with the mock harness only. The SDK still sends `Authorization` on them.
