# CLAUDE.md -- BlindPay PHP SDK

This file is for AI agents modifying this codebase. Follow these conventions exactly.

## 1. Project Structure

```
blindpay-php/
  composer.json              # Package: blindpay/php, PHP ^8.2, Guzzle ^7.9
  pint.json                  # Laravel Pint config (linter)
  phpunit.xml.dist           # PHPUnit config
  VERSIONING.md              # Release process documentation
  src/
    BlindPay.php             # Main client. Implements ApiClientInterface. Holds all resource properties.
    Internal/
      ApiClientInterface.php # Interface: get, post, put, patch, delete methods
      BlindPayException.php  # SDK exception class
    Resources/               # Each subdirectory = one API resource domain
      Available/             # Simple resource (no instanceId, no wrapper)
        Available.php        # Resource class + inline types
      Payins/                # Complex resource with sub-resource
        Payins.php           # Resource class + inline types (Payin, ListPayinsInput, etc.)
        PayinsWrapper.php    # Wrapper delegating to Payins + exposing Quotes sub-resource
        Quotes.php           # Sub-resource class + inline types
      Receivers/             # Complex resource with sub-resource
        Receivers.php        # Resource class + inline types
        ReceiversWrapper.php # Wrapper delegating to Receivers + exposing BankAccounts
      Wallets/               # Aggregation wrapper (no base methods, only sub-resources)
        WalletsWrapper.php   # Groups BlockchainWallets, OfframpWallets, CustodialWallets
        BlockchainWallets.php
        OfframpWallets.php
      Instances/
        Instances.php
        InstancesWrapper.php # Groups Instances + ApiKeys + Webhooks + TermsOfService
      ApiKeys/ApiKeys.php
      BankAccounts/BankAccounts.php
      CustodialWallets/CustodialWallets.php
      Fees/Fees.php
      PartnerFees/PartnerFees.php
      Payouts/Payouts.php
      Quotes/Quotes.php
      TermsOfService/TermsOfService.php
      Transfers/Transfers.php
      VirtualAccounts/VirtualAccounts.php
      Webhooks/Webhooks.php
    Types/                   # Shared types used across multiple resources
      BlindPayApiResponse.php  # Generic response wrapper (success/error)
      ErrorResponse.php
      PaginationParams.php     # Base class for paginated list inputs
      PaginationMetadata.php   # Response pagination metadata
      BaseTracking.php         # Abstract base for tracking types
      TrackingTransaction.php
      TrackingPayment.php
      TrackingComplete.php
      TrackingPartnerFee.php
      TrackingLiquidity.php
      AccountClass.php         # enum: individual, business
      BankAccountType.php      # enum: checking, savings
      Country.php              # enum: ISO 3166-1 alpha-2 codes
      Currency.php             # enum: USDC, USDT, USDB, BRL, USD, MXN, COP, ARS
      CurrencyType.php         # enum: sender, receiver
      Network.php              # enum: base, sepolia, arbitrum, polygon, ethereum, stellar, tron, solana, etc.
      PaymentMethod.php        # enum: ach, wire, pix, spei, transfers, pse, international_swift
      Permission.php           # enum: full_access
      Rail.php                 # enum: wire, ach, pix, spei_bitso, etc.
      RecipientRelationship.php
      StablecoinToken.php      # enum: USDC, USDT, USDB
      TransactionDocumentType.php
      TransactionStatus.php    # enum: refunded, processing, completed, failed, on_hold, pending_review
      WebhookEvent.php         # enum: receiver.new, payout.new, payin.new, etc.
  tests/
    Resources/               # Mirrors src/Resources structure
      Available/AvailableTest.php
      Payins/PayinsTest.php
      Payins/PayinQuotesTest.php
      Receivers/ReceiversTest.php
      Wallets/BlockchainWalletsTest.php
      Wallets/OfframpWalletsTest.php
      ... (one test file per resource)
```

## 2. Conventions

### Naming

- **Directories**: PascalCase matching the resource domain name (e.g., `Payins/`, `BankAccounts/`)
- **Files**: PascalCase matching the primary class name (e.g., `Payins.php`, `PayinsWrapper.php`)
- **Namespaces**: `BlindPay\SDK\Resources\{ResourceDir}` for resources, `BlindPay\SDK\Types` for shared types
- **Classes**: PascalCase. Response types end with `Response`. Input types end with `Input`.
- **Properties**: camelCase in PHP, snake_case in API JSON. The `fromArray`/`toArray` methods handle conversion.
- **Methods on resource classes**: camelCase verbs matching the API action (`list`, `get`, `create`, `update`, `delete`, `export`, `getTrack`)
- **Enums**: PascalCase name, UPPER_SNAKE_CASE cases, backed by `string` type

### PHP Patterns

- Every PHP file starts with `declare(strict_types=1);`
- All type/data classes are `readonly class` with promoted constructor properties
- Resource classes (the class with API methods) are NOT readonly -- they use `private readonly` for constructor params
- Shared enums live in `src/Types/`. Resource-local enums live inline in the resource file.
- All API methods return `BlindPayApiResponse`
- No `toArray()` on response types (only `fromArray`). Input types have `toArray()`. Some types have both.

### fromArray Pattern (response/data types)

```php
readonly class ExampleResponse
{
    public function __construct(
        public string $id,
        public TransactionStatus $status,
        public ?string $optionalField = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: TransactionStatus::from($data['status']),
            optionalField: $data['optional_field'] ?? null
        );
    }
}
```

Key rules:
- Required fields: access directly as `$data['key']`
- Optional/nullable fields: use `$data['key'] ?? null`
- Enum fields: use `EnumName::from($data['key'])`
- Optional enum fields: `isset($data['key']) ? EnumName::from($data['key']) : null`
- Nested objects: `NestedType::fromArray($data['key'])`
- Optional nested objects: `isset($data['key']) ? NestedType::fromArray($data['key']) : null`
- Float fields: `(float) $data['key']`
- DateTime fields: `new DateTimeImmutable($data['key'])`
- Array of objects: `array_map(fn (array $item) => Type::fromArray($item), $data['key'])`
- JSON keys are snake_case; PHP properties are camelCase

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
        $data = [
            'required_field' => $this->requiredField,
            'enum_field' => $this->enumField->value,
        ];

        if ($this->optionalField !== null) {
            $data['optional_field'] = $this->optionalField;
        }

        return $data;
    }
}
```

Key rules:
- Required fields: always include in the returned array
- Optional fields: conditionally include with `if ($this->field !== null)`
- Enum values: use `->value` to serialize
- Nested objects with toArray: use `$this->nested->toArray()`

### Paginated List Input Pattern

Extend `PaginationParams` and override `toArray()`:

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
        $params = parent::toArray();

        if ($this->filterField !== null) {
            $params['filter_field'] = $this->filterField;
        }

        return $params;
    }
}
```

### Paginated List Response Pattern

```php
readonly class ListExampleResponse
{
    public function __construct(
        public array $data,
        public PaginationMetadata $pagination
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            data: array_map(
                fn (array $item) => Example::fromArray($item),
                $data['data']
            ),
            pagination: PaginationMetadata::fromArray($data['pagination'])
        );
    }
}
```

## 3. How To: Add a New Resource

Suppose the API adds a new `invoices` resource at `instances/{instanceId}/invoices`.

### Step 1: Create the directory and resource file

Create `src/Resources/Invoices/Invoices.php`:

```php
<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Invoices;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;

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

    public function list(): BlindPayApiResponse
    {
        $response = $this->client->get("instances/{$this->instanceId}/invoices");

        if ($response->isSuccess() && is_array($response->data)) {
            $items = array_map(
                fn (array $item) => Invoice::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($items);
        }

        return $response;
    }

    public function get(string $invoiceId): BlindPayApiResponse
    {
        if (empty($invoiceId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Invoice ID cannot be empty')
            );
        }

        $response = $this->client->get("instances/{$this->instanceId}/invoices/{$invoiceId}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                Invoice::fromArray($response->data)
            );
        }

        return $response;
    }
}
```

### Step 2: Register in BlindPay.php

1. Add `use` import at top:
   ```php
   use BlindPay\SDK\Resources\Invoices\Invoices;
   ```

2. Add public property:
   ```php
   public readonly Invoices $invoices;
   ```

3. Initialize in `__construct()`:
   ```php
   $this->invoices = new Invoices($this->instanceId, $this);
   ```

### Step 3: Create test file

Create `tests/Resources/Invoices/InvoicesTest.php` following the test pattern (see section 8).

### Note on resources without instanceId

If the resource does not require instanceId (like `Available`), the constructor takes only `ApiClientInterface`:

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
public function newMethod(NewMethodInput $input): BlindPayApiResponse
{
    $response = $this->client->post(
        "instances/{$this->instanceId}/resource-path",
        $input->toArray()
    );

    if ($response->isSuccess() && is_array($response->data)) {
        return BlindPayApiResponse::success(
            NewMethodResponse::fromArray($response->data)
        );
    }

    return $response;
}
```

3. If the resource uses a wrapper, also add the delegation method to the wrapper class:

```php
public function newMethod(NewMethodInput $input): BlindPayApiResponse
{
    return $this->base->newMethod($input);
}
```

4. Add a corresponding test method.

### Method patterns by HTTP verb

- **GET single**: `$this->client->get("path/{$id}")` -- validate ID is not empty first
- **GET list**: `$this->client->get("path{$queryParams}")` -- build query string from input
- **POST create**: `$this->client->post("path", $input->toArray())`
- **PUT update**: `$this->client->put("path/{$id}", $input->toArray())`
- **PATCH update**: `$this->client->patch("path/{$id}", $input->toArray())`
- **DELETE**: `$this->client->delete("path/{$id}")`

### ID validation pattern

For methods accepting an entity ID as a string parameter, validate before making the request:

```php
if (empty($entityId)) {
    return BlindPayApiResponse::error(
        new \BlindPay\SDK\Types\ErrorResponse('Entity ID cannot be empty')
    );
}
```

## 5. How To: Modify Types

### Adding a property to an existing type

1. Add the property to the constructor (put optional/nullable properties at the end with defaults).
2. Update `fromArray()` to deserialize the new field.
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

### Adding a new shared enum

Create a new file in `src/Types/` with the enum following the existing pattern.

### Adding a new shared type (non-enum)

Create a new file in `src/Types/`. Use `readonly class` with `fromArray()` and optionally `toArray()`.

### Resource-local types vs shared types

- If a type is used only within one resource file, define it inline in that resource file.
- If a type is used across multiple resources, place it in `src/Types/`.

## 6. How To: Remove a Resource

1. Delete the resource directory under `src/Resources/`.
2. Remove the `use` import, property declaration, and initialization from `src/BlindPay.php`.
3. If the resource was part of a wrapper, also remove it from the wrapper constructor and any delegation methods.
4. Delete corresponding test files under `tests/Resources/`.

## 7. How To: Add a Sub-Resource (Wrapper Pattern)

Sub-resources are used when a resource has logically grouped child endpoints. There are two wrapper patterns:

### Pattern A: Delegation wrapper (Payins, Receivers)

The wrapper delegates the base resource's methods AND exposes sub-resources as public properties.

1. Create the sub-resource class (e.g., `src/Resources/Payins/Quotes.php`).
2. Create or update the wrapper class:

```php
readonly class PayinsWrapper
{
    public function __construct(
        private Payins $base,        // private: methods are delegated
        public Quotes $quotes        // public: accessed as $blindpay->payins->quotes->method()
    ) {}

    // Delegate each base method:
    public function list(?ListPayinsInput $params = null): BlindPayApiResponse
    {
        return $this->base->list($params);
    }

    // ... delegate all other base methods
}
```

3. In `BlindPay.php`, create a private `initialize*` method:

```php
private function initializePayins(): void
{
    $payinsResource = new Payins($this->instanceId, $this);
    $quotesResource = new PayinQuotes($this->instanceId, $this);

    $this->payins = new PayinsWrapper($payinsResource, $quotesResource);
}
```

4. Change the property type from the resource class to the wrapper class:
   ```php
   public readonly PayinsWrapper $payins;
   ```

5. Call the initializer in `__construct()`.

### Pattern B: Aggregation wrapper (Wallets)

The wrapper only groups sub-resources, no base methods to delegate.

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

Usage: `$blindpay->wallets->blockchain->list($receiverId)`

## 8. Testing

### Framework

Tests use **Pest v3** (built on PHPUnit). Test files use PHPUnit-style classes with `#[Test]` attributes.

### Commands

```bash
composer run test             # Run all tests
composer run test:coverage    # Run tests with coverage
composer run lint:check       # Check code style (Laravel Pint)
composer run lint:fix         # Auto-fix code style
```

### Test file pattern

```php
<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ExampleTest extends TestCase
{
    private BlindPay $blindpay;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $this->blindpay = new BlindPay(
            apiKey: 'test-key',
            instanceId: 'in_000000000000'
        );

        $this->injectHttpClient($httpClient);
    }

    private function injectHttpClient(Client $client): void
    {
        $reflection = new ReflectionClass($this->blindpay);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($this->blindpay, $client);
    }

    private function mockResponse(array $body, int $status = 200): void
    {
        $this->mockHandler->append(
            new Response(
                $status,
                ['Content-Type' => 'application/json'],
                json_encode($body)
            )
        );
    }

    #[Test]
    public function it_does_something(): void
    {
        $this->mockResponse([/* mock API response data */]);

        $response = $this->blindpay->resource->method();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        // Assert on $response->data properties...
    }
}
```

Key points:
- Use `MockHandler` to mock HTTP responses (no real API calls).
- Inject the mock HTTP client via reflection on the private `httpClient` property.
- Test method names start with `it_` and describe the behavior.
- Assert `isSuccess()`, `error` is null, then check `data` properties.
- Mock data should match the API's snake_case JSON structure.
- Test namespace is `BlindPay\SDK\Tests\Resources` (all test classes share this namespace).

## 9. Versioning

### VERSION constant

`src/BlindPay.php` has a `private const VERSION` that is used in the `User-Agent` header. This constant must be updated when releasing a new version.

```php
private const VERSION = '1.4.0';
```

Update this whenever you change the SDK version. The User-Agent sent to the API is `blindpay-php/{VERSION}`.

### Release process

1. Merge changes to `main`.
2. Update `const VERSION` in `BlindPay.php` to the new version number.
3. Create a GitHub release with a git tag (e.g., `v1.5.0`). The tag triggers Packagist to pick up the new version.
4. Composer resolves version from git tags, not from `composer.json`.

### SemVer rules for this SDK

| Change | Bump |
|--------|------|
| New resource, new optional field, new enum case | MINOR |
| Bug fix, refactor, typo | PATCH |
| Remove field, add required field, rename class/method, change return type | MAJOR |

## 10. OpenAPI to SDK Mapping Rules

When translating an OpenAPI spec change to SDK code:

### Endpoints to resource methods

| OpenAPI | SDK |
|---------|-----|
| `GET /v1/instances/{id}/things` | `$blindpay->things->list()` |
| `GET /v1/instances/{id}/things/{thingId}` | `$blindpay->things->get($thingId)` |
| `POST /v1/instances/{id}/things` | `$blindpay->things->create($input)` |
| `PUT /v1/instances/{id}/things/{thingId}` | `$blindpay->things->update($input)` |
| `PATCH /v1/instances/{id}/things/{thingId}` | `$blindpay->things->update($input)` (thingId inside input) |
| `DELETE /v1/instances/{id}/things/{thingId}` | `$blindpay->things->delete($thingId)` |

### URL path segments

- The base URL `https://api.blindpay.com/v1/` is already set. Resource methods use relative paths like `instances/{$this->instanceId}/things`.
- The `instanceId` is stored on the resource class and interpolated.
- Sub-resource IDs (e.g., receiverId) come from method parameters or input objects.

### Schema to types

| OpenAPI schema | PHP |
|----------------|-----|
| `type: string` | `string` |
| `type: number` / `type: float` | `float` (cast with `(float)`) |
| `type: integer` | `int` (cast with `(int)`) |
| `type: boolean` | `bool` |
| `type: string, format: date-time` | `DateTimeImmutable` |
| `type: string, enum: [...]` | PHP `enum` backed by string |
| `type: object` | `readonly class` with `fromArray()` |
| `type: array, items: { $ref }` | `array` with `array_map(fn => Type::fromArray(...))` |
| `nullable: true` or field not in `required` | `?Type` with `= null` default, `$data['key'] ?? null` in fromArray |

### Request body to input type

- Create a `readonly class` with `toArray()` that converts to the API's snake_case format.
- Required properties come first, optional properties last with `= null` defaults.

### Response body to response type

- Create a `readonly class` with `fromArray()`.
- Response types generally do NOT need `toArray()`.

### Query parameters

- For GET endpoints with query params, create an input class with a `toQueryString()` method.
- If it supports pagination, extend `PaginationParams`.

### Nested endpoints

Nested endpoints like `instances/{id}/receivers/{receiverId}/bank-accounts` are modeled as sub-resources accessed via wrappers: `$blindpay->receivers->bankAccounts->list($receiverId)`.
