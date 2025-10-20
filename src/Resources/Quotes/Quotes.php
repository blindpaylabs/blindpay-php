<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Quotes;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Currency;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TransactionDocumentType;

readonly class CreateQuoteInput
{
    public function __construct(
        public string $bankAccountId,
        public CurrencyType $currencyType,
        public float $requestAmount,
        public ?bool $coverFees,
        public ?string $partnerFeeId,
        public ?string $transactionDocumentFile,
        public ?string $transactionDocumentId,
        public TransactionDocumentType $transactionDocumentType,
        public ?Network $network = null,
        public ?StablecoinToken $token = null,
        public ?string $description = null
    ) {}

    public function toArray(): array
    {
        return [
            'bank_account_id' => $this->bankAccountId,
            'currency_type' => $this->currencyType->value,
            'request_amount' => $this->requestAmount,
            'cover_fees' => $this->coverFees,
            'partner_fee_id' => $this->partnerFeeId,
            'transaction_document_file' => $this->transactionDocumentFile,
            'transaction_document_id' => $this->transactionDocumentId,
            'transaction_document_type' => $this->transactionDocumentType->value,
            'network' => $this->network?->value,
            'token' => $this->token?->value,
            'description' => $this->description,
        ];
    }
}

readonly class QuoteContract
{
    /*
     * @param array<array<string, mixed>> $abi
     */
    public function __construct(
        public array $abi,
        public string $address,
        public string $functionName,
        public string $blindpayContractAddress,
        public string $amount,
        public QuoteContractNetwork $network
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            abi: $data['abi'],
            address: $data['address'],
            functionName: $data['functionName'],
            blindpayContractAddress: $data['blindpayContractAddress'],
            amount: $data['amount'],
            network: QuoteContractNetwork::fromArray($data['network'])
        );
    }

    public function toArray(): array
    {
        return [
            'abi' => $this->abi,
            'address' => $this->address,
            'functionName' => $this->functionName,
            'blindpayContractAddress' => $this->blindpayContractAddress,
            'amount' => $this->amount,
            'network' => $this->network->toArray(),
        ];
    }
}

readonly class QuoteContractNetwork
{
    public function __construct(
        public string $name,
        public int $chainId
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            chainId: $data['chainId']
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'chainId' => $this->chainId,
        ];
    }
}

readonly class CreateQuoteResponse
{
    public function __construct(
        public string $id,
        public int $expiresAt,
        public float $commercialQuotation,
        public float $blindpayQuotation,
        public float $receiverAmount,
        public float $senderAmount,
        public float $partnerFeeAmount,
        public float $flatFee,
        public QuoteContract $contract,
        public float $receiverLocalAmount,
        public string $description
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            expiresAt: $data['expires_at'],
            commercialQuotation: (float) $data['commercial_quotation'],
            blindpayQuotation: (float) $data['blindpay_quotation'],
            receiverAmount: (float) $data['receiver_amount'],
            senderAmount: (float) $data['sender_amount'],
            partnerFeeAmount: (float) $data['partner_fee_amount'],
            flatFee: (float) $data['flat_fee'],
            contract: QuoteContract::fromArray($data['contract']),
            receiverLocalAmount: (float) $data['receiver_local_amount'],
            description: $data['description']
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'expires_at' => $this->expiresAt,
            'commercial_quotation' => $this->commercialQuotation,
            'blindpay_quotation' => $this->blindpayQuotation,
            'receiver_amount' => $this->receiverAmount,
            'sender_amount' => $this->senderAmount,
            'partner_fee_amount' => $this->partnerFeeAmount,
            'flat_fee' => $this->flatFee,
            'contract' => $this->contract->toArray(),
            'receiver_local_amount' => $this->receiverLocalAmount,
            'description' => $this->description,
        ];
    }
}

readonly class GetFxRateInput
{
    public function __construct(
        public CurrencyType $currencyType,
        public Currency $from,
        public Currency $to,
        public float $requestAmount
    ) {}

    public function toArray(): array
    {
        return [
            'currency_type' => $this->currencyType->value,
            'from' => $this->from->value,
            'to' => $this->to->value,
            'request_amount' => $this->requestAmount,
        ];
    }
}

readonly class GetFxRateResponse
{
    public function __construct(
        public float $commercialQuotation,
        public float $blindpayQuotation,
        public float $resultAmount,
        public float $instanceFlatFee,
        public float $instancePercentageFee
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            commercialQuotation: (float) $data['commercial_quotation'],
            blindpayQuotation: (float) $data['blindpay_quotation'],
            resultAmount: (float) $data['result_amount'],
            instanceFlatFee: (float) $data['instance_flat_fee'],
            instancePercentageFee: (float) $data['instance_percentage_fee']
        );
    }

    public function toArray(): array
    {
        return [
            'commercial_quotation' => $this->commercialQuotation,
            'blindpay_quotation' => $this->blindpayQuotation,
            'result_amount' => $this->resultAmount,
            'instance_flat_fee' => $this->instanceFlatFee,
            'instance_percentage_fee' => $this->instancePercentageFee,
        ];
    }
}

class Quotes
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * Create a quote
     *
     * @param CreateQuoteInput $input
     * @return BlindPayApiResponse<CreateQuoteResponse>
     */
    public function create(CreateQuoteInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/quotes",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateQuoteResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get FX rate
     *
     * @param GetFxRateInput $input
     * @return BlindPayApiResponse<GetFxRateResponse>
     */
    public function getFxRate(GetFxRateInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/quotes/fx",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                GetFxRateResponse::fromArray($response->data)
            );
        }

        return $response;
    }
}
