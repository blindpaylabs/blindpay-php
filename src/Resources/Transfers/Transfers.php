<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Transfers;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Currency;
use BlindPay\SDK\Types\PaginationMetadata;
use BlindPay\SDK\Types\PaginationParams;
use BlindPay\SDK\Types\TransactionStatus;
use DateTimeImmutable;

readonly class TransferTrackingStep
{
    public function __construct(
        public string $status,
        public ?DateTimeImmutable $date = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            date: isset($data['date']) ? new DateTimeImmutable($data['date']) : null
        );
    }
}

readonly class TransferTrackingTransactionMonitoring
{
    public function __construct(
        public string $status,
        public ?DateTimeImmutable $date = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            date: isset($data['date']) ? new DateTimeImmutable($data['date']) : null
        );
    }
}

readonly class Transfer
{
    public function __construct(
        public string $id,
        public string $instanceId,
        public TransactionStatus $status,
        public string $quoteId,
        public string $sourceWalletId,
        public string $destinationWalletId,
        public float $amount,
        public Currency $currency,
        public TransferTrackingStep $trackingTransaction,
        public TransferTrackingTransactionMonitoring $trackingTransactionMonitoring,
        public TransferTrackingStep $trackingComplete,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            instanceId: $data['instance_id'],
            status: TransactionStatus::from($data['status']),
            quoteId: $data['quote_id'],
            sourceWalletId: $data['source_wallet_id'],
            destinationWalletId: $data['destination_wallet_id'],
            amount: (float) $data['amount'],
            currency: Currency::from($data['currency']),
            trackingTransaction: TransferTrackingStep::fromArray($data['tracking_transaction']),
            trackingTransactionMonitoring: TransferTrackingTransactionMonitoring::fromArray($data['tracking_transaction_monitoring']),
            trackingComplete: TransferTrackingStep::fromArray($data['tracking_complete']),
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at'])
        );
    }
}

readonly class CreateTransferQuoteInput
{
    public function __construct(
        public string $sourceWalletId,
        public string $destinationWalletId,
        public float $amount
    ) {}

    public function toArray(): array
    {
        return [
            'source_wallet_id' => $this->sourceWalletId,
            'destination_wallet_id' => $this->destinationWalletId,
            'amount' => $this->amount,
        ];
    }
}

readonly class CreateTransferQuoteResponse
{
    public function __construct(
        public string $id,
        public float $amount,
        public Currency $currency,
        public float $feeAmount,
        public string $sourceWalletId,
        public string $destinationWalletId
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            amount: (float) $data['amount'],
            currency: Currency::from($data['currency']),
            feeAmount: (float) $data['fee_amount'],
            sourceWalletId: $data['source_wallet_id'],
            destinationWalletId: $data['destination_wallet_id']
        );
    }
}

readonly class CreateTransferInput
{
    public function __construct(
        public string $quoteId
    ) {}

    public function toArray(): array
    {
        return [
            'quote_id' => $this->quoteId,
        ];
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
