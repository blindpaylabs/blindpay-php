<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Payables;

use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Types\Currency;
use DateTimeImmutable;

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
