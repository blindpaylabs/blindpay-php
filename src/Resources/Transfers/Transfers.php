<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Transfers;

use BlindPay\SDK\BlindPayApiException;
use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\AutoPager;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Internal\Query;
use BlindPay\SDK\Types\BaseTracking;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\ListResponse;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\PaginationParams;
use BlindPay\SDK\Types\RequestOptions;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TrackingComplete;
use BlindPay\SDK\Types\TrackingPartnerFee;
use BlindPay\SDK\Types\TransactionStatus;
use DateTimeImmutable;
use Generator;

readonly class TransferTrackingTransactionMonitoring extends BaseTracking
{
    public function __construct(
        string $step,
        public ?float $blockchainScreening = null,
        public ?float $riskScore = null,
        ?DateTimeImmutable $completedAt = null
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
            blockchainScreening: isset($data['blockchain_screening']) ? (float) $data['blockchain_screening'] : null,
            riskScore: isset($data['risk_score']) ? (float) $data['risk_score'] : null,
            completedAt: isset($data['completed_at'])
                ? new DateTimeImmutable($data['completed_at'])
                : null
        );
    }

    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'blockchain_screening' => $this->blockchainScreening,
            'risk_score' => $this->riskScore,
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}

readonly class TransferTrackingPaymaster extends BaseTracking
{
    public function __construct(
        string $step,
        public ?string $transactionHash = null,
        public ?string $gasFee = null,
        public ?string $errorMessage = null,
        ?DateTimeImmutable $completedAt = null
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
            transactionHash: $data['transaction_hash'] ?? null,
            gasFee: $data['gas_fee'] ?? null,
            errorMessage: $data['error_message'] ?? null,
            completedAt: isset($data['completed_at'])
                ? new DateTimeImmutable($data['completed_at'])
                : null
        );
    }

    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'transaction_hash' => $this->transactionHash,
            'gas_fee' => $this->gasFee,
            'error_message' => $this->errorMessage,
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}

readonly class TransferTrackingBridgeSwap extends BaseTracking
{
    public function __construct(
        string $step,
        public ?string $transactionHash = null,
        public ?string $gasFee = null,
        public ?string $errorMessage = null,
        ?DateTimeImmutable $completedAt = null
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
            transactionHash: $data['transaction_hash'] ?? null,
            gasFee: $data['gas_fee'] ?? null,
            errorMessage: $data['error_message'] ?? null,
            completedAt: isset($data['completed_at'])
                ? new DateTimeImmutable($data['completed_at'])
                : null
        );
    }

    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'transaction_hash' => $this->transactionHash,
            'gas_fee' => $this->gasFee,
            'error_message' => $this->errorMessage,
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}

readonly class CreateTransferQuoteInput
{
    /**
     * @param  int  $requestAmount  integer cents. A swap between different tokens needs at least 100 USDC
     *                              (10000) on the USDC side, or the quote fails with
     *                              \BlindPay\SDK\Types\ErrorCode::TRANSFERS_SWAP_AMOUNT_BELOW_MINIMUM
     */
    public function __construct(
        public string $walletId,
        public StablecoinToken $senderToken,
        public string $customerWalletAddress,
        public StablecoinToken $customerToken,
        public Network $customerNetwork,
        public int $requestAmount,
        public CurrencyType $amountReference,
        public ?bool $coverFees = null,
        public ?string $partnerFeeId = null,
        /** basis points (100 = 1%), overriding the partner fee's */
        public ?int $partnerPercentageFee = null,
        /** cents, overriding the partner fee's */
        public ?int $partnerFlatFee = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'wallet_id' => $this->walletId,
            'sender_token' => $this->senderToken->value,
            'customer_wallet_address' => $this->customerWalletAddress,
            'customer_token' => $this->customerToken->value,
            'customer_network' => $this->customerNetwork->value,
            'request_amount' => $this->requestAmount,
            'amount_reference' => $this->amountReference->value,
        ];

        if ($this->coverFees !== null) {
            $data['cover_fees'] = $this->coverFees;
        }

        if ($this->partnerFeeId !== null) {
            $data['partner_fee_id'] = $this->partnerFeeId;
        }
        if ($this->partnerPercentageFee !== null) {
            $data['partner_percentage_fee'] = $this->partnerPercentageFee;
        }
        if ($this->partnerFlatFee !== null) {
            $data['partner_flat_fee'] = $this->partnerFlatFee;
        }

        return $data;
    }
}

readonly class CreateTransferQuoteResponse
{
    public function __construct(
        public string $id,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public float $receiverAmount,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public float $senderAmount,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public float $flatFee,
        public ?float $expiresAt = null,
        public ?float $commercialQuotation = null,
        public ?float $blindpayQuotation = null,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public ?float $partnerFeeAmount = null,
        public ?int $partnerPercentageFee = null,
        public ?int $partnerFlatFee = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            receiverAmount: (float) $data['receiver_amount'],
            senderAmount: (float) $data['sender_amount'],
            flatFee: (float) $data['flat_fee'],
            expiresAt: isset($data['expires_at']) ? (float) $data['expires_at'] : null,
            commercialQuotation: isset($data['commercial_quotation']) ? (float) $data['commercial_quotation'] : null,
            blindpayQuotation: isset($data['blindpay_quotation']) ? (float) $data['blindpay_quotation'] : null,
            partnerFeeAmount: isset($data['partner_fee_amount']) ? (float) $data['partner_fee_amount'] : null,
            partnerPercentageFee: isset($data['partner_percentage_fee']) ? (int) $data['partner_percentage_fee'] : null,
            partnerFlatFee: isset($data['partner_flat_fee']) ? (int) $data['partner_flat_fee'] : null,
        );
    }
}

readonly class CreateTransferInput
{
    public function __construct(
        public string $transferQuoteId
    ) {}

    public function toArray(): array
    {
        return [
            'transfer_quote_id' => $this->transferQuoteId,
        ];
    }
}

readonly class Transfer
{
    public function __construct(
        public string $id,
        public string $instanceId,
        public TransactionStatus|string $status,
        public string $transferQuoteId,
        public string $walletId,
        public StablecoinToken|string $senderToken,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public float $senderAmount,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public float $receiverAmount,
        public StablecoinToken|string $customerToken,
        public Network|string $customerNetwork,
        public string $customerWalletAddress,
        public string $customerId,
        public ?string $address,
        public Network|string $network,
        public TransferTrackingTransactionMonitoring $trackingTransactionMonitoring,
        public TransferTrackingPaymaster $trackingPaymaster,
        public TransferTrackingBridgeSwap $trackingBridgeSwap,
        public TrackingComplete $trackingComplete,
        public ?TrackingPartnerFee $trackingPartnerFee,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public ?string $imageUrl = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $legalName = null,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public ?float $partnerFeeAmount = null,
        public ?string $externalId = null,
        public ?int $partnerPercentageFee = null,
        public ?int $partnerFlatFee = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            instanceId: $data['instance_id'],
            status: Decode::enum(TransactionStatus::class, $data['status']),
            transferQuoteId: $data['transfer_quote_id'],
            walletId: $data['wallet_id'],
            senderToken: Decode::enum(StablecoinToken::class, $data['sender_token']),
            senderAmount: (float) $data['sender_amount'],
            receiverAmount: (float) $data['receiver_amount'],
            customerToken: Decode::enum(StablecoinToken::class, $data['customer_token']),
            customerNetwork: Decode::enum(Network::class, $data['customer_network']),
            customerWalletAddress: $data['customer_wallet_address'],
            customerId: $data['customer_id'],
            address: $data['address'] ?? null,
            network: Decode::enum(Network::class, $data['network']),
            trackingTransactionMonitoring: TransferTrackingTransactionMonitoring::fromArray($data['tracking_transaction_monitoring']),
            trackingPaymaster: TransferTrackingPaymaster::fromArray($data['tracking_paymaster']),
            trackingBridgeSwap: TransferTrackingBridgeSwap::fromArray($data['tracking_bridge_swap']),
            trackingComplete: TrackingComplete::fromArray($data['tracking_complete']),
            trackingPartnerFee: isset($data['tracking_partner_fee']) ? TrackingPartnerFee::fromArray($data['tracking_partner_fee']) : null,
            createdAt: isset($data['created_at'])
                ? new DateTimeImmutable($data['created_at'])
                : null,
            updatedAt: isset($data['updated_at'])
                ? new DateTimeImmutable($data['updated_at'])
                : null,
            imageUrl: $data['image_url'] ?? null,
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            legalName: $data['legal_name'] ?? null,
            partnerFeeAmount: isset($data['partner_fee_amount']) ? (float) $data['partner_fee_amount'] : null,
            externalId: $data['external_id'] ?? null,
            partnerPercentageFee: isset($data['partner_percentage_fee']) ? (int) $data['partner_percentage_fee'] : null,
            partnerFlatFee: isset($data['partner_flat_fee']) ? (int) $data['partner_flat_fee'] : null,
        );
    }
}

/**
 * Response of POST /transfers (spec CreateTransferOut): the new transfer's id, status and
 * tracking steps. Fetch the full Transfer with get() when you need amounts and parties.
 */
readonly class CreateTransferResponse
{
    public function __construct(
        public string $id,
        public TransactionStatus|string $status,
        public TransferTrackingTransactionMonitoring $trackingTransactionMonitoring,
        public TransferTrackingPaymaster $trackingPaymaster,
        public TransferTrackingBridgeSwap $trackingBridgeSwap,
        public TrackingComplete $trackingComplete,
        public ?TrackingPartnerFee $trackingPartnerFee = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: Decode::enum(TransactionStatus::class, $data['status']),
            trackingTransactionMonitoring: TransferTrackingTransactionMonitoring::fromArray($data['tracking_transaction_monitoring']),
            trackingPaymaster: TransferTrackingPaymaster::fromArray($data['tracking_paymaster']),
            trackingBridgeSwap: TransferTrackingBridgeSwap::fromArray($data['tracking_bridge_swap']),
            trackingComplete: TrackingComplete::fromArray($data['tracking_complete']),
            trackingPartnerFee: isset($data['tracking_partner_fee']) ? TrackingPartnerFee::fromArray($data['tracking_partner_fee']) : null
        );
    }
}

readonly class ListTransfersInput extends PaginationParams
{
    /**
     * @param  int|null  $offset  deprecated: ignored by the API; use $startingAfter
     */
    public function __construct(
        ?int $limit = null,
        ?int $offset = null,
        ?string $startingAfter = null,
        ?string $endingBefore = null,
        public ?string $customerId = null,
        public ?string $walletId = null,
        public ?TransactionStatus $status = null,
        public ?Network $network = null,
        public ?StablecoinToken $token = null,
    ) {
        parent::__construct($limit, $offset, $startingAfter, $endingBefore);
    }

    public function toArray(): array
    {
        return parent::toArray() + array_filter([
            'customer_id' => $this->customerId,
            'wallet_id' => $this->walletId,
            'status' => $this->status?->value,
            'network' => $this->network?->value,
            'token' => $this->token?->value,
        ], static fn ($value) => $value !== null);
    }
}

/**
 * @extends ListResponse<Transfer>
 */
readonly class ListTransfersResponse extends ListResponse
{
    /** Accepts both shapes the API returns (see ListResponse). */
    public static function fromArray(array $data): self
    {
        return self::decode($data, Transfer::fromArray(...));
    }
}

class Transfers
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /**
     * Create a transfer quote
     *
     * A swap between different tokens needs at least 100 USDC, measured on the USDC side; a smaller
     * one fails with code \BlindPay\SDK\Types\ErrorCode::TRANSFERS_SWAP_AMOUNT_BELOW_MINIMUM (400, message
     * `fx_amount_below_minimum`).
     *
     * @return BlindPayApiResponse<CreateTransferQuoteResponse>
     */
    public function createQuote(
        CreateTransferQuoteInput $input,
        ?RequestOptions $options = null
    ): BlindPayApiResponse {
        $response = $this->client->post(
            Path::of('instances/%s/transfer-quotes', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => CreateTransferQuoteResponse::fromArray($data));
    }

    /**
     * Create a transfer
     *
     * @return BlindPayApiResponse<CreateTransferResponse>
     */
    public function create(CreateTransferInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/transfers', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => CreateTransferResponse::fromArray($data));
    }

    /**
     * List transfers
     *
     * @return BlindPayApiResponse<ListTransfersResponse>
     */
    public function list(?ListTransfersInput $params = null, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->listPage($params?->toArray() ?? [], $options);
    }

    /**
     * Every transfer matching $params, fetched lazily page by page (`limit` 100 unless you set one),
     * following `pagination.next_page` until `has_more` is false. Forward only: `endingBefore` is
     * rejected. A failed page request throws BlindPayApiException (it carries the ErrorResponse).
     *
     *     foreach ($blindpay->transfers->listAutoPaging() as $transfer) { ... }
     *
     * @return Generator<int, Transfer>
     *
     * @throws BlindPayApiException
     */
    public function listAutoPaging(?ListTransfersInput $params = null, ?RequestOptions $options = null): Generator
    {
        return AutoPager::iterate($params?->toArray() ?? [], fn (array $query) => $this->listPage($query, $options));
    }

    /**
     * @param  array<string, string>  $query
     * @return BlindPayApiResponse<ListTransfersResponse>
     */
    private function listPage(array $query, ?RequestOptions $options): BlindPayApiResponse
    {
        return $this->client->get(Path::of('instances/%s/transfers', $this->instanceId).Query::build($query), $options)
            ->map(fn (array $data) => ListTransfersResponse::fromArray($data));
    }

    /**
     * Get a transfer by ID
     *
     * @return BlindPayApiResponse<Transfer>
     */
    public function get(string $transferId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($transferId === '') {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Transfer ID cannot be empty')
            );
        }

        $response = $this->client->get(Path::of('instances/%s/transfers/%s', $this->instanceId, $transferId), $options);

        return $response->map(fn (array $data) => Transfer::fromArray($data));
    }

    /**
     * Get transfer tracking (public endpoint)
     *
     * Public tracking endpoint for BlindPay's web and iOS apps: a server-side call gets 401 in
     * production. Use get() instead.
     *
     * @return BlindPayApiResponse<Transfer>
     */
    public function getTrack(string $transferId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($transferId === '') {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Transfer ID cannot be empty')
            );
        }

        $response = $this->client->get(Path::of('e/transfers/%s', $transferId), $options);

        return $response->map(fn (array $data) => Transfer::fromArray($data));
    }
}
