<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Payins;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\PaginationMetadata;
use BlindPay\SDK\Types\PaginationParams;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TrackingComplete;
use BlindPay\SDK\Types\TrackingPartnerFee;
use BlindPay\SDK\Types\TrackingPayment;
use BlindPay\SDK\Types\TrackingTransaction;
use BlindPay\SDK\Types\TransactionStatus;
use DateTimeImmutable;

readonly class BlindpayBankAch
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

readonly class BlindpayBankWire
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

readonly class BlindpayBankRtp
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

readonly class BlindpayBankBeneficiary
{
    public function __construct(
        public string $name,
        public string $addressLine1,
        public string $addressLine2
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            addressLine1: $data['address_line_1'],
            addressLine2: $data['address_line_2']
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

readonly class BlindpayBankReceivingBank
{
    public function __construct(
        public string $name,
        public string $addressLine1,
        public string $addressLine2
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            addressLine1: $data['address_line_1'],
            addressLine2: $data['address_line_2']
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

readonly class BlindpayBankDetails
{
    public function __construct(
        public string $routingNumber,
        public string $accountNumber,
        public string $accountType,
        public string $swiftBicCode,
        public BlindpayBankAch $ach,
        public BlindpayBankWire $wire,
        public BlindpayBankRtp $rtp,
        public BlindpayBankBeneficiary $beneficiary,
        public BlindpayBankReceivingBank $receivingBank
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            routingNumber: $data['routing_number'],
            accountNumber: $data['account_number'],
            accountType: $data['account_type'],
            swiftBicCode: $data['swift_bic_code'],
            ach: BlindpayBankAch::fromArray($data['ach']),
            wire: BlindpayBankWire::fromArray($data['wire']),
            rtp: BlindpayBankRtp::fromArray($data['rtp']),
            beneficiary: BlindpayBankBeneficiary::fromArray($data['beneficiary']),
            receivingBank: BlindpayBankReceivingBank::fromArray($data['receiving_bank'])
        );
    }

    public function toArray(): array
    {
        return [
            'routing_number' => $this->routingNumber,
            'account_number' => $this->accountNumber,
            'account_type' => $this->accountType,
            'swift_bic_code' => $this->swiftBicCode,
            'ach' => $this->ach->toArray(),
            'wire' => $this->wire->toArray(),
            'rtp' => $this->rtp->toArray(),
            'beneficiary' => $this->beneficiary->toArray(),
            'receiving_bank' => $this->receivingBank->toArray(),
        ];
    }
}

readonly class Payin
{
    public function __construct(
        public string $receiverId,
        public string $id,
        public TransactionStatus $status,
        public string $payinQuoteId,
        public string $instanceId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public string $type,
        public string $paymentMethod,
        public float $senderAmount,
        public float $receiverAmount,
        public StablecoinToken $token,
        public float $partnerFeeAmount,
        public float $totalFeeAmount,
        public float $commercialQuotation,
        public float $blindpayQuotation,
        public string $currency,
        public float $billingFee,
        public string $name,
        public string $address,
        public Network $network,
        public BlindpayBankDetails $blindpayBankDetails,
        public ?string $pixCode = null,
        public ?string $memoCode = null,
        public ?string $clabe = null,
        public ?TrackingTransaction $trackingTransaction = null,
        public ?TrackingPayment $trackingPayment = null,
        public ?TrackingComplete $trackingComplete = null,
        public ?TrackingPartnerFee $trackingPartnerFee = null,
        public ?string $imageUrl = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $legalName = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            receiverId: $data['receiver_id'],
            id: $data['id'],
            status: TransactionStatus::from($data['status']),
            payinQuoteId: $data['payin_quote_id'],
            instanceId: $data['instance_id'],
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
            type: $data['type'],
            paymentMethod: $data['payment_method'],
            senderAmount: (float) $data['sender_amount'],
            receiverAmount: (float) $data['receiver_amount'],
            token: StablecoinToken::from($data['token']),
            partnerFeeAmount: (float) $data['partner_fee_amount'],
            totalFeeAmount: (float) $data['total_fee_amount'],
            commercialQuotation: (float) $data['commercial_quotation'],
            blindpayQuotation: (float) $data['blindpay_quotation'],
            currency: $data['currency'],
            billingFee: (float) $data['billing_fee'],
            name: $data['name'],
            address: $data['address'],
            network: Network::from($data['network']),
            blindpayBankDetails: BlindpayBankDetails::fromArray($data['blindpay_bank_details']),
            pixCode: $data['pix_code'] ?? null,
            memoCode: $data['memo_code'] ?? null,
            clabe: $data['clabe'] ?? null,
            trackingTransaction: isset($data['tracking_transaction']) ? TrackingTransaction::fromArray($data['tracking_transaction']) : null,
            trackingPayment: isset($data['tracking_payment']) ? TrackingPayment::fromArray($data['tracking_payment']) : null,
            trackingComplete: isset($data['tracking_complete']) ? TrackingComplete::fromArray($data['tracking_complete']) : null,
            trackingPartnerFee: isset($data['tracking_partner_fee']) ? TrackingPartnerFee::fromArray($data['tracking_partner_fee']) : null,
            imageUrl: $data['image_url'] ?? null,
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            legalName: $data['legal_name'] ?? null
        );
    }
}

readonly class ListPayinsInput extends PaginationParams
{
    public function __construct(
        public ?TransactionStatus $status = null,
        public ?string $receiverId = null,
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

        if ($this->status !== null) {
            $params['status'] = $this->status->value;
        }

        if ($this->receiverId !== null) {
            $params['receiver_id'] = $this->receiverId;
        }

        return $params;
    }
}

readonly class ListPayinsResponse
{
    public function __construct(
        public array $data,
        public PaginationMetadata $pagination
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            data: array_map(
                fn (array $item) => Payin::fromArray($item),
                $data['data']
            ),
            pagination: PaginationMetadata::fromArray($data['pagination'])
        );
    }
}

readonly class ExportPayinsInput
{
    public function __construct(
        public TransactionStatus $status,
        public ?int $limit = null,
        public ?int $offset = null
    ) {}

    public function toArray(): array
    {
        $params = [
            'status' => $this->status->value,
        ];

        if ($this->limit !== null) {
            $params['limit'] = (string) $this->limit;
        }

        if ($this->offset !== null) {
            $params['offset'] = (string) $this->offset;
        }

        return $params;
    }

    public function toQueryString(): string
    {
        $params = $this->toArray();

        return empty($params) ? '' : '?'.http_build_query($params);
    }
}

readonly class CreateEvmPayinResponse
{
    public function __construct(
        public string $id,
        public TransactionStatus $status,
        public string $receiverId,
        public float $receiverAmount,
        public BlindpayBankDetails $blindpayBankDetails,
        public ?string $pixCode = null,
        public ?string $memoCode = null,
        public ?string $clabe = null,
        public ?TrackingComplete $trackingComplete = null,
        public ?TrackingPayment $trackingPayment = null,
        public ?TrackingTransaction $trackingTransaction = null,
        public ?TrackingPartnerFee $trackingPartnerFee = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: TransactionStatus::from($data['status']),
            receiverId: $data['receiver_id'],
            receiverAmount: (float) $data['receiver_amount'],
            blindpayBankDetails: BlindpayBankDetails::fromArray($data['blindpay_bank_details']),
            pixCode: $data['pix_code'] ?? null,
            memoCode: $data['memo_code'] ?? null,
            clabe: $data['clabe'] ?? null,
            trackingComplete: isset($data['tracking_complete']) ? TrackingComplete::fromArray($data['tracking_complete']) : null,
            trackingPayment: isset($data['tracking_payment']) ? TrackingPayment::fromArray($data['tracking_payment']) : null,
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
    public function list(?ListPayinsInput $params = null): BlindPayApiResponse
    {
        $queryParams = $params ? $params->toQueryString() : '';
        $response = $this->client->get("/instances/{$this->instanceId}/payins{$queryParams}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                ListPayinsResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get a specific payin by ID
     *
     * @param string $payinId The payin ID
     * @return BlindPayApiResponse<Payin>
     */
    public function get(string $payinId): BlindPayApiResponse
    {
        if (empty($payinId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Payin ID cannot be empty')
            );
        }

        $response = $this->client->get("/instances/{$this->instanceId}/payins/{$payinId}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                Payin::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get payin tracking information
     *
     * @param string $payinId The payin ID to track
     * @return BlindPayApiResponse<Payin>
     */
    public function getTrack(string $payinId): BlindPayApiResponse
    {
        if (empty($payinId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Payin ID cannot be empty')
            );
        }

        $response = $this->client->get("/e/payins/{$payinId}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                Payin::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Export payins by status
     *
     * @param ExportPayinsInput $params Export parameters
     * @return BlindPayApiResponse<Payin[]>
     */
    public function export(ExportPayinsInput $params): BlindPayApiResponse
    {
        $queryParams = $params->toQueryString();
        $response = $this->client->get("/instances/{$this->instanceId}/export/payins{$queryParams}");

        if ($response->isSuccess() && is_array($response->data)) {
            $payins = array_map(
                fn (array $item) => Payin::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($payins);
        }

        return $response;
    }

    /*
     * Create an EVM payin from a quote
     *
     * @param string $payinQuoteId The payin quote ID
     * @return BlindPayApiResponse<CreateEvmPayinResponse>
     */
    public function createEvm(string $payinQuoteId): BlindPayApiResponse
    {
        if (empty($payinQuoteId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Payin quote ID cannot be empty')
            );
        }

        $response = $this->client->post(
            "/instances/{$this->instanceId}/payins/evm",
            ['payin_quote_id' => $payinQuoteId]
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateEvmPayinResponse::fromArray($response->data)
            );
        }

        return $response;
    }
}
