<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Transfers;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BaseTracking;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\PaginationMetadata;
use BlindPay\SDK\Types\PaginationParams;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TrackingComplete;
use BlindPay\SDK\Types\TrackingPartnerFee;
use BlindPay\SDK\Types\TransactionStatus;
use DateTimeImmutable;

readonly class TransferTrackingTransactionMonitoring extends BaseTracking
{
    public function __construct(
        string $step,
        ?DateTimeImmutable $completedAt
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
            completedAt: isset($data['completed_at'])
                ? new DateTimeImmutable($data['completed_at'])
                : null
        );
    }

    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}

readonly class TransferTrackingPaymaster extends BaseTracking
{
    public function __construct(
        string $step,
        ?DateTimeImmutable $completedAt
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
            completedAt: isset($data['completed_at'])
                ? new DateTimeImmutable($data['completed_at'])
                : null
        );
    }

    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}

readonly class TransferTrackingBridgeSwap extends BaseTracking
{
    public function __construct(
        string $step,
        ?DateTimeImmutable $completedAt
    ) {
        parent::__construct($step, $completedAt);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            step: $data['step'],
            completedAt: isset($data['completed_at'])
                ? new DateTimeImmutable($data['completed_at'])
                : null
        );
    }

    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}

readonly class CreateTransferQuoteInput
{
    public function __construct(
        public string $walletId,
        public StablecoinToken $senderToken,
        public string $receiverWalletAddress,
        public StablecoinToken $receiverToken,
        public Network $receiverNetwork,
        public int $requestAmount,
        public CurrencyType $amountReference,
        public ?bool $coverFees = null,
        public ?string $partnerFeeId = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'wallet_id' => $this->walletId,
            'sender_token' => $this->senderToken->value,
            'receiver_wallet_address' => $this->receiverWalletAddress,
            'receiver_token' => $this->receiverToken->value,
            'receiver_network' => $this->receiverNetwork->value,
            'request_amount' => $this->requestAmount,
            'amount_reference' => $this->amountReference->value,
        ];

        if ($this->coverFees !== null) {
            $data['cover_fees'] = $this->coverFees;
        }

        if ($this->partnerFeeId !== null) {
            $data['partner_fee_id'] = $this->partnerFeeId;
        }

        return $data;
    }
}

readonly class CreateTransferQuoteResponse
{
    public function __construct(
        public string $id,
        public float $receiverAmount,
        public float $senderAmount,
        public float $flatFee,
        public ?float $expiresAt = null,
        public ?float $commercialQuotation = null,
        public ?float $blindpayQuotation = null,
        public ?float $partnerFeeAmount = null
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
            partnerFeeAmount: isset($data['partner_fee_amount']) ? (float) $data['partner_fee_amount'] : null
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
        public TransactionStatus $status,
        public string $transferQuoteId,
        public string $walletId,
        public StablecoinToken $senderToken,
        public float $senderAmount,
        public float $receiverAmount,
        public StablecoinToken $receiverToken,
        public Network $receiverNetwork,
        public string $receiverWalletAddress,
        public string $receiverId,
        public string $address,
        public Network $network,
        public TransferTrackingTransactionMonitoring $trackingTransactionMonitoring,
        public TransferTrackingPaymaster $trackingPaymaster,
        public TransferTrackingBridgeSwap $trackingBridgeSwap,
        public TrackingComplete $trackingComplete,
        public TrackingPartnerFee $trackingPartnerFee,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?string $imageUrl = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $legalName = null,
        public ?float $partnerFeeAmount = null,
        public ?string $externalId = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            instanceId: $data['instance_id'],
            status: TransactionStatus::from($data['status']),
            transferQuoteId: $data['transfer_quote_id'],
            walletId: $data['wallet_id'],
            senderToken: StablecoinToken::from($data['sender_token']),
            senderAmount: (float) $data['sender_amount'],
            receiverAmount: (float) $data['receiver_amount'],
            receiverToken: StablecoinToken::from($data['receiver_token']),
            receiverNetwork: Network::from($data['receiver_network']),
            receiverWalletAddress: $data['receiver_wallet_address'],
            receiverId: $data['receiver_id'],
            address: $data['address'],
            network: Network::from($data['network']),
            trackingTransactionMonitoring: TransferTrackingTransactionMonitoring::fromArray($data['tracking_transaction_monitoring']),
            trackingPaymaster: TransferTrackingPaymaster::fromArray($data['tracking_paymaster']),
            trackingBridgeSwap: TransferTrackingBridgeSwap::fromArray($data['tracking_bridge_swap']),
            trackingComplete: TrackingComplete::fromArray($data['tracking_complete']),
            trackingPartnerFee: TrackingPartnerFee::fromArray($data['tracking_partner_fee']),
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
            imageUrl: $data['image_url'] ?? null,
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            legalName: $data['legal_name'] ?? null,
            partnerFeeAmount: isset($data['partner_fee_amount']) ? (float) $data['partner_fee_amount'] : null,
            externalId: $data['external_id'] ?? null
        );
    }
}

readonly class ListTransfersInput extends PaginationParams
{
    public function __construct(
        ?int $limit = null,
        ?int $offset = null,
        ?string $startingAfter = null,
        ?string $endingBefore = null
    ) {
        parent::__construct($limit, $offset, $startingAfter, $endingBefore);
    }
}

readonly class ListTransfersResponse
{
    public function __construct(
        public array $data,
        public PaginationMetadata $pagination
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            data: array_map(
                fn (array $item) => Transfer::fromArray($item),
                $data['data']
            ),
            pagination: PaginationMetadata::fromArray($data['pagination'])
        );
    }
}

class Transfers
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * Create a transfer quote
     *
     * @param CreateTransferQuoteInput $input
     * @return BlindPayApiResponse<CreateTransferQuoteResponse>
     */
    public function createQuote(CreateTransferQuoteInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "instances/{$this->instanceId}/transfer-quotes",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateTransferQuoteResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create a transfer
     *
     * @param CreateTransferInput $input
     * @return BlindPayApiResponse<Transfer>
     */
    public function create(CreateTransferInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "instances/{$this->instanceId}/transfers",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                Transfer::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * List transfers
     *
     * @param ListTransfersInput|null $params
     * @return BlindPayApiResponse<ListTransfersResponse>
     */
    public function list(?ListTransfersInput $params = null): BlindPayApiResponse
    {
        $queryParams = $params ? $params->toQueryString() : '';
        $response = $this->client->get("instances/{$this->instanceId}/transfers{$queryParams}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                ListTransfersResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get a transfer by ID
     *
     * @param string $transferId
     * @return BlindPayApiResponse<Transfer>
     */
    public function get(string $transferId): BlindPayApiResponse
    {
        if (empty($transferId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Transfer ID cannot be empty')
            );
        }

        $response = $this->client->get("instances/{$this->instanceId}/transfers/{$transferId}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                Transfer::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get transfer tracking (public endpoint)
     *
     * @param string $transferId
     * @return BlindPayApiResponse<Transfer>
     */
    public function getTrack(string $transferId): BlindPayApiResponse
    {
        if (empty($transferId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Transfer ID cannot be empty')
            );
        }

        $response = $this->client->get("e/transfers/{$transferId}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                Transfer::fromArray($response->data)
            );
        }

        return $response;
    }
}
