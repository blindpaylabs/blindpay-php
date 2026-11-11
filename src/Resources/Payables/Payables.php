<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Payables;

use BlindPay\SDK\BlindPayApiException;
use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\AutoPager;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Internal\Query;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Currency;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\ListResponse;
use BlindPay\SDK\Types\PaginationParams;
use BlindPay\SDK\Types\RequestOptions;
use DateTimeImmutable;
use Generator;

enum PayableStatus: string
{
    case DRAFT = 'draft';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
}

enum PayableLastAttemptStatus: string
{
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
}

enum PayableType: string
{
    case BOLETO = 'boleto';
    case PIX_CODE = 'pix_code';
    case INVOICE = 'invoice';
}

enum PayableBankAccountType: string
{
    case ACH = 'ach';
    case WIRE = 'wire';
}

/** The issuer (`from`) or beneficiary (`to`) of an invoice payable. */
readonly class PayableParty
{
    public function __construct(
        public ?string $legalName = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $city = null,
        public ?string $stateProvinceRegion = null,
        public ?string $postalCode = null,
        /** ISO 3166-1 alpha-2 */
        public ?string $country = null,
        public ?string $taxId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            legalName: $data['legal_name'] ?? null,
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'] ?? null,
            stateProvinceRegion: $data['state_province_region'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            country: $data['country'] ?? null,
            taxId: $data['tax_id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'legal_name' => $this->legalName,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
            'city' => $this->city,
            'state_province_region' => $this->stateProvinceRegion,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
            'tax_id' => $this->taxId,
        ], static fn ($value) => $value !== null);
    }
}

readonly class PayableLineItem
{
    /**
     * @param  int  $quantity  at least 1
     * @param  int|null  $price  unit price in cents of the payable currency (150000 = 1,500.00)
     */
    public function __construct(
        public string $name,
        public int $quantity,
        public ?int $price,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            quantity: (int) $data['quantity'],
            price: isset($data['price']) ? (int) $data['price'] : null,
        );
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'quantity' => $this->quantity, 'price' => $this->price];
    }
}

/** Destination of an invoice payable, as registered. Null for boleto and PIX payables. */
readonly class PayableBankAccount
{
    public function __construct(
        public PayableBankAccountType|string $type,
        public ?string $beneficiaryName = null,
        public ?string $routingNumber = null,
        public ?string $accountNumber = null,
        public ?string $accountType = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: Decode::enum(PayableBankAccountType::class, $data['type']),
            beneficiaryName: $data['beneficiary_name'] ?? null,
            routingNumber: $data['routing_number'] ?? null,
            accountNumber: $data['account_number'] ?? null,
            accountType: $data['account_type'] ?? null,
        );
    }
}

/** A payable: a boleto, PIX code or invoice to pay out. Amounts are integer cents. */
readonly class Payable
{
    /**
     * @param  int|null  $amount  total in cents: line items plus taxes minus discount
     * @param  PayableLineItem[]  $lineItems
     */
    public function __construct(
        public string $id,
        public string $instanceId,
        public string $customerId,
        public Currency|string $currency,
        public PayableStatus|string $status,
        public ?int $amount,
        public ?PayableParty $from = null,
        public ?PayableParty $to = null,
        public array $lineItems = [],
        public ?string $note = null,
        public ?int $discount = null,
        public ?int $taxes = null,
        public ?string $boletoBarcode = null,
        public ?string $pixQrcode = null,
        public ?string $documentFile = null,
        public ?string $dueDate = null,
        public ?string $scheduledAt = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
        public ?string $payoutId = null,
        public ?PayableBankAccount $bankAccount = null,
        public PayableLastAttemptStatus|string|null $lastAttemptStatus = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $legalName = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            instanceId: $data['instance_id'],
            customerId: $data['customer_id'],
            currency: Decode::enum(Currency::class, $data['currency']),
            status: Decode::enum(PayableStatus::class, $data['status']),
            amount: isset($data['amount']) ? (int) $data['amount'] : null,
            from: isset($data['from']) ? PayableParty::fromArray($data['from']) : null,
            to: isset($data['to']) ? PayableParty::fromArray($data['to']) : null,
            lineItems: array_map(static fn (array $item) => PayableLineItem::fromArray($item), $data['line_items'] ?? []),
            note: $data['note'] ?? null,
            discount: isset($data['discount']) ? (int) $data['discount'] : null,
            taxes: isset($data['taxes']) ? (int) $data['taxes'] : null,
            boletoBarcode: $data['boleto_barcode'] ?? null,
            pixQrcode: $data['pix_qrcode'] ?? null,
            documentFile: $data['document_file'] ?? null,
            dueDate: $data['due_date'] ?? null,
            scheduledAt: $data['scheduled_at'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null,
            payoutId: $data['payout_id'] ?? null,
            bankAccount: isset($data['bank_account']) ? PayableBankAccount::fromArray($data['bank_account']) : null,
            lastAttemptStatus: Decode::enum(PayableLastAttemptStatus::class, $data['last_attempt_status'] ?? null),
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            legalName: $data['legal_name'] ?? null,
        );
    }
}

/**
 * A payable to create. Exactly one source applies: a `boletoBarcode`, a `pixQrcode`, or an invoice
 * (`lineItems` plus a destination `bankAccount`). Amounts are integer cents of `currency`.
 */
readonly class CreatePayableInput
{
    /**
     * @param  PayableLineItem[]  $lineItems  invoice line items (at most 100)
     * @param  int|null  $discount  cents subtracted from the line item total
     * @param  int|null  $taxes  cents added to the line item total
     * @param  array<string, mixed>|null  $bankAccount  the invoice destination, as the API's
     *                                                  CreateBankAccountIn body (e.g. `['type' => 'ach', ...]`)
     * @param  string|null  $dueDate  ISO 8601 date
     * @param  string|null  $scheduledAt  ISO 8601 date-time to pay out at
     */
    public function __construct(
        public string $customerId,
        public Currency $currency,
        public ?PayableParty $from = null,
        public ?PayableParty $to = null,
        public array $lineItems = [],
        public ?string $note = null,
        public ?int $discount = null,
        public ?int $taxes = null,
        public ?string $boletoBarcode = null,
        public ?string $pixQrcode = null,
        public ?string $documentFile = null,
        public ?string $dueDate = null,
        public ?string $scheduledAt = null,
        public ?array $bankAccount = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'customer_id' => $this->customerId,
            'currency' => $this->currency->value,
        ];
        if ($this->from !== null) {
            $data['from'] = $this->from->toArray();
        }
        if ($this->to !== null) {
            $data['to'] = $this->to->toArray();
        }
        if ($this->lineItems !== []) {
            $data['line_items'] = array_map(static fn (PayableLineItem $item) => $item->toArray(), $this->lineItems);
        }

        return $data + array_filter([
            'note' => $this->note,
            'discount' => $this->discount,
            'taxes' => $this->taxes,
            'boleto_barcode' => $this->boletoBarcode,
            'pix_qrcode' => $this->pixQrcode,
            'document_file' => $this->documentFile,
            'due_date' => $this->dueDate,
            'scheduled_at' => $this->scheduledAt,
            'bank_account' => $this->bankAccount,
        ], static fn ($value) => $value !== null);
    }
}

readonly class ListPayablesInput extends PaginationParams
{
    public function __construct(
        public ?PayableStatus $status = null,
        public ?string $customerId = null,
        public ?string $customerName = null,
        public ?PayableType $type = null,
        public ?string $beneficiaryName = null,
        ?int $limit = null,
        ?string $startingAfter = null,
        ?string $endingBefore = null,
    ) {
        parent::__construct($limit, null, $startingAfter, $endingBefore);
    }

    public function toArray(): array
    {
        return parent::toArray() + array_filter([
            'status' => $this->status?->value,
            'customer_id' => $this->customerId,
            'customer_name' => $this->customerName,
            'type' => $this->type?->value,
            'beneficiary_name' => $this->beneficiaryName,
        ], static fn ($value) => $value !== null);
    }
}

/**
 * @extends ListResponse<Payable>
 */
readonly class ListPayablesResponse extends ListResponse
{
    /** Accepts both shapes the API returns (see ListResponse). */
    public static function fromArray(array $data): self
    {
        return self::decode($data, Payable::fromArray(...));
    }
}

class Payables
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client,
    ) {}

    /**
     * Create a payable (boleto, PIX code or invoice) to pay out later with a quote's `payable_id`.
     *
     * @return BlindPayApiResponse<Payable>
     */
    public function create(CreatePayableInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->client->post(Path::of('instances/%s/payables', $this->instanceId), $input->toArray(), $options)
            ->map(fn (array $data) => Payable::fromArray($data));
    }

    /**
     * One page of payables (see ListResponse for the two wire shapes).
     *
     * @return BlindPayApiResponse<ListPayablesResponse>
     */
    public function list(?ListPayablesInput $params = null, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->listPage($params?->toArray() ?? [], $options);
    }

    /**
     * Every payable matching $params, fetched lazily page by page (`limit` 100 unless you set
     * one). Forward only: `endingBefore` is rejected. A failed page throws BlindPayApiException.
     *
     * @return Generator<int, Payable>
     *
     * @throws BlindPayApiException
     */
    public function listAutoPaging(?ListPayablesInput $params = null, ?RequestOptions $options = null): Generator
    {
        return AutoPager::iterate($params?->toArray() ?? [], fn (array $query) => $this->listPage($query, $options));
    }

    /**
     * @return BlindPayApiResponse<Payable>
     */
    public function get(string $payableId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($payableId === '') {
            return BlindPayApiResponse::error(ErrorResponse::validation('Payable ID cannot be empty'));
        }

        return $this->client->get(Path::of('instances/%s/payables/%s', $this->instanceId, $payableId), $options)
            ->map(fn (array $data) => Payable::fromArray($data));
    }

    /**
     * Delete a payable that has not been paid out; returns it as it was.
     *
     * @return BlindPayApiResponse<Payable>
     */
    public function delete(string $payableId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($payableId === '') {
            return BlindPayApiResponse::error(ErrorResponse::validation('Payable ID cannot be empty'));
        }

        return $this->client->delete(Path::of('instances/%s/payables/%s', $this->instanceId, $payableId), null, $options)
            ->map(fn (array $data) => Payable::fromArray($data));
    }

    /** @param array<string, string> $query */
    private function listPage(array $query, ?RequestOptions $options): BlindPayApiResponse
    {
        return $this->client->get(Path::of('instances/%s/payables', $this->instanceId).Query::build($query), $options)
            ->map(fn (array $data) => ListPayablesResponse::fromArray($data));
    }
}
