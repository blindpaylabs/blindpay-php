<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Quotes;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Currency;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\RequestOptions;
use BlindPay\SDK\Types\StablecoinToken;

/**
 * A payout quote. Quote a bank account (`bankAccountId`) or a payable (`payableId`): exactly one
 * of them, which the API checks. `requestAmount` is integer cents of the side `currencyType`
 * names (1000 = 10.00); a payable quote takes its amount from the payable.
 */
readonly class CreateQuoteInput
{
    public function __construct(
        public Network $network,
        public StablecoinToken $token,
        public ?string $bankAccountId = null,
        public ?string $payableId = null,
        public ?CurrencyType $currencyType = null,
        public ?int $requestAmount = null,
        public ?bool $coverFees = null,
        public ?string $partnerFeeId = null,
        public ?string $description = null,
        public ?string $refundWalletAddress = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'bank_account_id' => $this->bankAccountId,
            'payable_id' => $this->payableId,
            'network' => $this->network->value,
            'token' => $this->token->value,
            'currency_type' => $this->currencyType?->value,
            'request_amount' => $this->requestAmount,
            'cover_fees' => $this->coverFees,
            'partner_fee_id' => $this->partnerFeeId,
            'description' => $this->description,
            'refund_wallet_address' => $this->refundWalletAddress,
        ], static fn ($value) => $value !== null);
    }
}

readonly class QuoteContract
{
    /**
     * @param  array<array<string, mixed>>  $abi
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
        /** Unix time in milliseconds; quotes expire after 5 minutes */
        public ?int $expiresAt,
        public ?float $commercialQuotation,
        public ?float $blindpayQuotation,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public ?float $receiverAmount,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public ?float $senderAmount,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public ?float $partnerFeeAmount,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public ?float $flatFee,
        public ?QuoteContract $contract,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public ?float $customerLocalAmount,
        public ?string $description,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public ?float $billingFeeAmount = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            expiresAt: isset($data['expires_at']) ? (int) $data['expires_at'] : null,
            commercialQuotation: isset($data['commercial_quotation']) ? (float) $data['commercial_quotation'] : null,
            blindpayQuotation: isset($data['blindpay_quotation']) ? (float) $data['blindpay_quotation'] : null,
            receiverAmount: isset($data['receiver_amount']) ? (float) $data['receiver_amount'] : null,
            senderAmount: isset($data['sender_amount']) ? (float) $data['sender_amount'] : null,
            partnerFeeAmount: isset($data['partner_fee_amount']) ? (float) $data['partner_fee_amount'] : null,
            flatFee: isset($data['flat_fee']) ? (float) $data['flat_fee'] : null,
            contract: isset($data['contract']) ? QuoteContract::fromArray($data['contract']) : null,
            customerLocalAmount: isset($data['customer_local_amount']) ? (float) $data['customer_local_amount'] : null,
            description: $data['description'] ?? null,
            billingFeeAmount: isset($data['billing_fee_amount']) ? (float) $data['billing_fee_amount'] : null,
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
            'contract' => $this->contract?->toArray(),
            'customer_local_amount' => $this->customerLocalAmount,
            'description' => $this->description,
            'billing_fee_amount' => $this->billingFeeAmount,
        ];
    }
}

readonly class GetFxRateInput
{
    public function __construct(
        public CurrencyType $currencyType,
        public Currency $from,
        public Currency $to,
        /** integer cents of the side currencyType names (1000 = 10.00) */
        public int $requestAmount
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
        public ?float $commercialQuotation,
        public ?float $blindpayQuotation,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public float $resultAmount,
        /** Integer cents carried in a float (1000 = 10.00); the type stays float until the next major. */
        public ?float $instanceFlatFee,
        /** basis points (100 = 1%) */
        public float $instancePercentageFee
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            commercialQuotation: isset($data['commercial_quotation']) ? (float) $data['commercial_quotation'] : null,
            blindpayQuotation: isset($data['blindpay_quotation']) ? (float) $data['blindpay_quotation'] : null,
            resultAmount: (float) $data['result_amount'],
            instanceFlatFee: isset($data['instance_flat_fee']) ? (float) $data['instance_flat_fee'] : null,
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

    /**
     * Create a quote
     *
     * @return BlindPayApiResponse<CreateQuoteResponse>
     */
    public function create(
        CreateQuoteInput $input,
        ?RequestOptions $options = null
    ): BlindPayApiResponse {
        $response = $this->client->post(
            Path::of('instances/%s/quotes', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => CreateQuoteResponse::fromArray($data));
    }

    /**
     * Get FX rate
     *
     * @return BlindPayApiResponse<GetFxRateResponse>
     */
    public function getFxRate(GetFxRateInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/quotes/fx', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => GetFxRateResponse::fromArray($data));
    }
}
