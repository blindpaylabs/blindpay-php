<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Payins;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Currency;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\StablecoinToken;

enum PaymentMethod: string
{
    case ACH = 'ach';
    case WIRE = 'wire';
    case PIX = 'pix';
    case SPEI = 'spei';
}

readonly class PayerRules
{
    public function __construct(
        public array $pixAllowedTaxIds
    ) {}

    public function toArray(): array
    {
        return [
            'pix_allowed_tax_ids' => $this->pixAllowedTaxIds,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            pixAllowedTaxIds: $data['pix_allowed_tax_ids'] ?? []
        );
    }
}

readonly class CreatePayinQuoteInput
{
    public function __construct(
        public string $blockchainWalletId,
        public CurrencyType $currencyType,
        public PaymentMethod $paymentMethod,
        public float $requestAmount,
        public StablecoinToken $token,
        public bool $coverFees,
        public PayerRules $payerRules,
        public ?string $partnerFeeId = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'blockchain_wallet_id' => $this->blockchainWalletId,
            'currency_type' => $this->currencyType->value,
            'payment_method' => $this->paymentMethod->value,
            'request_amount' => $this->requestAmount,
            'token' => $this->token->value,
            'cover_fees' => $this->coverFees,
            'payer_rules' => $this->payerRules->toArray(),
        ];

        if ($this->partnerFeeId !== null) {
            $data['partner_fee_id'] = $this->partnerFeeId;
        }

        return $data;
    }
}

readonly class CreatePayinQuoteResponse
{
    public function __construct(
        public string $id,
        public int $expiresAt,
        public float $commercialQuotation,
        public float $blindpayQuotation,
        public float $receiverAmount,
        public float $senderAmount,
        public ?float $partnerFeeAmount = null,
        public ?float $flatFee = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            expiresAt: (int) $data['expires_at'],
            commercialQuotation: (float) $data['commercial_quotation'],
            blindpayQuotation: (float) $data['blindpay_quotation'],
            receiverAmount: (float) $data['receiver_amount'],
            senderAmount: (float) $data['sender_amount'],
            partnerFeeAmount: isset($data['partner_fee_amount']) ? (float) $data['partner_fee_amount'] : null,
            flatFee: isset($data['flat_fee']) ? (float) $data['flat_fee'] : null
        );
    }
}

readonly class GetPayinFxRateInput
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

readonly class GetPayinFxRateResponse
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
}

class Quotes
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * Create a payin quote
     *
     * @param CreatePayinQuoteInput $input The quote input parameters
     * @return BlindPayApiResponse<CreatePayinQuoteResponse>
     */
    public function create(CreatePayinQuoteInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "instances/{$this->instanceId}/payin-quotes",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreatePayinQuoteResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get FX rate for payin conversion
     *
     * @param GetPayinFxRateInput $input The FX rate request parameters
     * @return BlindPayApiResponse<GetPayinFxRateResponse>
     */
    public function getFxRate(GetPayinFxRateInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "instances/{$this->instanceId}/payin-quotes/fx",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                GetPayinFxRateResponse::fromArray($response->data)
            );
        }

        return $response;
    }
}
