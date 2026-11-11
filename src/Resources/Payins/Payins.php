<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Payins;

use BlindPay\SDK\BlindPayApiException;
use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\AutoPager;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Internal\Query;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\ListResponse;
use BlindPay\SDK\Types\ManualExecutionStatus;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\PaginationParams;
use BlindPay\SDK\Types\PayinTrackingPayment;
use BlindPay\SDK\Types\RequestOptions;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TrackingComplete;
use BlindPay\SDK\Types\TrackingPartnerFee;
use BlindPay\SDK\Types\TrackingTransaction;
use BlindPay\SDK\Types\TransactionStatus;
use DateTimeImmutable;
use Generator;

readonly class BankAch
{
    public function __construct(
        public string $routingNumber,
        public string $accountNumber
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            routingNumber: $data['routing_number'],
            accountNumber: $data['account_number']
        );
    }

    public function toArray(): array
    {
        return [
            'routing_number' => $this->routingNumber,
            'account_number' => $this->accountNumber,
        ];
    }
}

readonly class BankWire
{
    public function __construct(
        public string $routingNumber,
        public string $accountNumber
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            routingNumber: $data['routing_number'],
            accountNumber: $data['account_number']
        );
    }

    public function toArray(): array
    {
        return [
            'routing_number' => $this->routingNumber,
            'account_number' => $this->accountNumber,
        ];
    }
}

readonly class BankRtp
{
    public function __construct(
        public string $routingNumber,
        public string $accountNumber
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            routingNumber: $data['routing_number'],
            accountNumber: $data['account_number']
        );
    }

    public function toArray(): array
    {
        return [
            'routing_number' => $this->routingNumber,
            'account_number' => $this->accountNumber,
        ];
    }
}

readonly class BankBeneficiary
{
    public function __construct(
        public ?string $name,
        public ?string $addressLine1,
        public ?string $addressLine2
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
        ];
    }
}

readonly class BankReceivingBank
{
    public function __construct(
        public ?string $name,
        public ?string $addressLine1,
        public ?string $addressLine2
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'address_line_1' => $this->addressLine1,
            'address_line_2' => $this->addressLine2,
        ];
    }
}

readonly class BankDetails
{
    public function __construct(
        public ?string $routingNumber,
        public ?string $accountNumber,
        public ?string $accountType,
        public ?string $swiftBicCode,
        public ?BankAch $ach,
        public ?BankWire $wire,
        public ?BankRtp $rtp,
        public BankBeneficiary $beneficiary,
        public BankReceivingBank $receivingBank,
        public ?string $swiftAccountNumber = null,
        public ?array $swiftReceivingBank = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            routingNumber: $data['routing_number'] ?? null,
            accountNumber: $data['account_number'] ?? null,
            accountType: $data['account_type'] ?? null,
            swiftBicCode: $data['swift_bic_code'] ?? null,
            ach: isset($data['ach']) ? BankAch::fromArray($data['ach']) : null,
            wire: isset($data['wire']) ? BankWire::fromArray($data['wire']) : null,
            rtp: isset($data['rtp']) ? BankRtp::fromArray($data['rtp']) : null,
            beneficiary: BankBeneficiary::fromArray($data['beneficiary']),
            receivingBank: BankReceivingBank::fromArray($data['receiving_bank']),
            swiftAccountNumber: $data['swift_account_number'] ?? null,
            swiftReceivingBank: $data['swift_receiving_bank'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'routing_number' => $this->routingNumber,
            'account_number' => $this->accountNumber,
            'account_type' => $this->accountType,
            'swift_bic_code' => $this->swiftBicCode,
            'ach' => $this->ach?->toArray(),
            'wire' => $this->wire?->toArray(),
            'rtp' => $this->rtp?->toArray(),
            'beneficiary' => $this->beneficiary->toArray(),
            'receiving_bank' => $this->receivingBank->toArray(),
            'swift_account_number' => $this->swiftAccountNumber,
            'swift_receiving_bank' => $this->swiftReceivingBank,
        ];
    }
}

readonly class Payin
{
    public function __construct(
        public string $customerId,
        public string $id,
        public TransactionStatus|string $status,
        public string $payinQuoteId,
        public string $instanceId,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public string $type,
        public string $paymentMethod,
        public ?float $senderAmount,
        public ?float $receiverAmount,
        public StablecoinToken|string $token,
        public ?float $partnerFeeAmount,
        public ?float $totalFeeAmount,
        public ?float $commercialQuotation,
        public ?float $blindpayQuotation,
        public string $currency,
        /** @deprecated The API no longer returns billing_fee, so this is null unless it does. Use $billingFeeAmount. */
        public ?float $billingFee,
        public string $name,
        public ?string $address,
        public Network|string $network,
        public ?BankDetails $BankDetails,
        public ?string $pixCode = null,
        public ?string $memoCode = null,
        public ?string $clabe = null,
        public ?TrackingTransaction $trackingTransaction = null,
        public ?PayinTrackingPayment $trackingPayment = null,
        public ?TrackingComplete $trackingComplete = null,
        public ?TrackingPartnerFee $trackingPartnerFee = null,
        public ?string $imageUrl = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $legalName = null,
        public ?bool $isOtc = null,
        public ?float $billingFeeAmount = null,
        public ?string $pseDocumentType = null,
        public ?string $pseFullName = null,
        public ?string $psePaymentLink = null,
        public ?string $pseTaxId = null,
        public ?string $partnerFeeId = null,
        public ManualExecutionStatus|string|null $manualExecutionStatus = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            customerId: $data['customer_id'],
            id: $data['id'],
            status: Decode::enum(TransactionStatus::class, $data['status']),
            payinQuoteId: $data['payin_quote_id'],
            instanceId: $data['instance_id'],
            createdAt: isset($data['created_at'])
                ? new DateTimeImmutable($data['created_at'])
                : null,
            updatedAt: isset($data['updated_at'])
                ? new DateTimeImmutable($data['updated_at'])
                : null,
            type: $data['type'],
            paymentMethod: $data['payment_method'],
            senderAmount: isset($data['sender_amount']) ? (float) $data['sender_amount'] : null,
            receiverAmount: isset($data['receiver_amount']) ? (float) $data['receiver_amount'] : null,
            token: Decode::enum(StablecoinToken::class, $data['token']),
            partnerFeeAmount: isset($data['partner_fee_amount']) ? (float) $data['partner_fee_amount'] : null,
            totalFeeAmount: isset($data['total_fee_amount']) ? (float) $data['total_fee_amount'] : null,
            commercialQuotation: isset($data['commercial_quotation']) ? (float) $data['commercial_quotation'] : null,
            blindpayQuotation: isset($data['blindpay_quotation']) ? (float) $data['blindpay_quotation'] : null,
            currency: $data['currency'],
            billingFee: isset($data['billing_fee']) ? (float) $data['billing_fee'] : null,
            name: $data['name'],
            address: $data['address'] ?? null,
            network: Decode::enum(Network::class, $data['network']),
            BankDetails: isset($data['blindpay_bank_details']) ? BankDetails::fromArray($data['blindpay_bank_details']) : null,
            pixCode: $data['pix_code'] ?? null,
            memoCode: $data['memo_code'] ?? null,
            clabe: $data['clabe'] ?? null,
            trackingTransaction: isset($data['tracking_transaction']) ? TrackingTransaction::fromArray($data['tracking_transaction']) : null,
            trackingPayment: isset($data['tracking_payment']) ? PayinTrackingPayment::fromArray($data['tracking_payment']) : null,
            trackingComplete: isset($data['tracking_complete']) ? TrackingComplete::fromArray($data['tracking_complete']) : null,
            trackingPartnerFee: isset($data['tracking_partner_fee']) ? TrackingPartnerFee::fromArray($data['tracking_partner_fee']) : null,
            imageUrl: $data['image_url'] ?? null,
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            legalName: $data['legal_name'] ?? null,
            isOtc: $data['is_otc'] ?? null,
            billingFeeAmount: isset($data['billing_fee_amount']) ? (float) $data['billing_fee_amount'] : null,
            pseDocumentType: $data['pse_document_type'] ?? null,
            pseFullName: $data['pse_full_name'] ?? null,
            psePaymentLink: $data['pse_payment_link'] ?? null,
            pseTaxId: $data['pse_tax_id'] ?? null,
            partnerFeeId: $data['partner_fee_id'] ?? null,
            manualExecutionStatus: Decode::enum(ManualExecutionStatus::class, $data['manual_execution_status'] ?? null)
        );
    }
}

readonly class ListPayinsInput extends PaginationParams
{
    /**
     * @param  int|null  $offset  deprecated: ignored by the API; use $startingAfter
     */
    public function __construct(
        public ?TransactionStatus $status = null,
        public ?string $customerId = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $startingAfter = null,
        ?string $endingBefore = null,
        public ?string $customerName = null,
        public ?string $bankAccountId = null,
        public ?string $country = null,
        /** the rail / payment method wire value, e.g. pix, ach, wire, spei_bitso */
        public ?string $paymentMethod = null,
        public ?Network $network = null,
        public ?StablecoinToken $token = null,
    ) {
        parent::__construct($limit, $offset, $startingAfter, $endingBefore);
    }

    public function toArray(): array
    {
        return parent::toArray() + array_filter([
            'status' => $this->status?->value,
            'customer_id' => $this->customerId,
            'customer_name' => $this->customerName,
            'bank_account_id' => $this->bankAccountId,
            'country' => $this->country,
            'payment_method' => $this->paymentMethod,
            'network' => $this->network?->value,
            'token' => $this->token?->value,
        ], static fn ($value) => $value !== null);
    }
}

/**
 * @extends ListResponse<Payin>
 */
readonly class ListPayinsResponse extends ListResponse
{
    /** Accepts both shapes the API returns (see ListResponse). */
    public static function fromArray(array $data): self
    {
        return self::decode($data, Payin::fromArray(...));
    }
}

readonly class CreateEvmPayinResponse
{
    public function __construct(
        public string $id,
        public TransactionStatus|string $status,
        public ?string $customerId,
        public ?float $receiverAmount,
        public ?BankDetails $BankDetails,
        public ?string $pixCode = null,
        public ?string $memoCode = null,
        public ?string $clabe = null,
        public ?TrackingComplete $trackingComplete = null,
        public ?PayinTrackingPayment $trackingPayment = null,
        public ?TrackingTransaction $trackingTransaction = null,
        public ?TrackingPartnerFee $trackingPartnerFee = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: Decode::enum(TransactionStatus::class, $data['status']),
            customerId: $data['customer_id'] ?? null,
            receiverAmount: isset($data['receiver_amount']) ? (float) $data['receiver_amount'] : null,
            BankDetails: isset($data['blindpay_bank_details']) ? BankDetails::fromArray($data['blindpay_bank_details']) : null,
            pixCode: $data['pix_code'] ?? null,
            memoCode: $data['memo_code'] ?? null,
            clabe: $data['clabe'] ?? null,
            trackingComplete: isset($data['tracking_complete']) ? TrackingComplete::fromArray($data['tracking_complete']) : null,
            trackingPayment: isset($data['tracking_payment']) ? PayinTrackingPayment::fromArray($data['tracking_payment']) : null,
            trackingTransaction: isset($data['tracking_transaction']) ? TrackingTransaction::fromArray($data['tracking_transaction']) : null,
            trackingPartnerFee: isset($data['tracking_partner_fee']) ? TrackingPartnerFee::fromArray($data['tracking_partner_fee']) : null
        );
    }
}

class Payins
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * List all payins with optional filters
     *
     * @param ListPayinsInput|null $params Optional filters for listing payins
     * @return BlindPayApiResponse<ListPayinsResponse>
     */
    public function list(?ListPayinsInput $params = null, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->listPage($params?->toArray() ?? [], $options);
    }

    /**
     * Every payin matching $params, fetched lazily page by page (`limit` 100 unless you set one),
     * following `pagination.next_page` until `has_more` is false. Forward only: `endingBefore` is
     * rejected. A failed page request throws BlindPayApiException (it carries the ErrorResponse).
     *
     *     foreach ($blindpay->payins->listAutoPaging() as $payin) { ... }
     *
     * @return Generator<int, Payin>
     *
     * @throws BlindPayApiException
     */
    public function listAutoPaging(?ListPayinsInput $params = null, ?RequestOptions $options = null): Generator
    {
        return AutoPager::iterate($params?->toArray() ?? [], fn (array $query) => $this->listPage($query, $options));
    }

    /** @param array<string, string> $query */
    private function listPage(array $query, ?RequestOptions $options): BlindPayApiResponse
    {
        return $this->client->get(Path::of('instances/%s/payins', $this->instanceId).Query::build($query), $options)
            ->map(fn (array $data) => ListPayinsResponse::fromArray($data));
    }

    /*
     * Get a specific payin by ID
     *
     * @param string $payinId The payin ID
     * @return BlindPayApiResponse<Payin>
     */
    public function get(string $payinId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($payinId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Payin ID cannot be empty')
            );
        }

        $response = $this->client->get(Path::of('instances/%s/payins/%s', $this->instanceId, $payinId), $options);

        return $response->map(fn (array $data) => Payin::fromArray($data));
    }

    /*
     * Get payin tracking information
     *
     * @param string $payinId The payin ID to track
     * @return BlindPayApiResponse<Payin>
     */
    public function getTrack(string $payinId, ?string $checkoutToken = null, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($payinId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Payin ID cannot be empty')
            );
        }

        $response = $this->client->get(Path::of('e/payins/%s', $payinId).Query::build(['checkout_token' => $checkoutToken]), $options);

        return $response->map(fn (array $data) => Payin::fromArray($data));
    }

    /*
     * Create an EVM payin from a quote
     *
     * @param string $payinQuoteId The payin quote ID
     * @return BlindPayApiResponse<CreateEvmPayinResponse>
     */
    public function createEvm(string $payinQuoteId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($payinQuoteId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Payin quote ID cannot be empty')
            );
        }

        $response = $this->client->post(
            Path::of('instances/%s/payins/evm', $this->instanceId),
            ['payin_quote_id' => $payinQuoteId],
            $options
        );

        return $response->map(fn (array $data) => CreateEvmPayinResponse::fromArray($data));
    }
}
