<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Payins;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Currency;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\RequestOptions;
use BlindPay\SDK\Types\StablecoinToken;

enum PaymentMethod: string
{
    case ACH = 'ach';
    case ACH_PULL = 'ach_pull';
    case WIRE = 'wire';
    case PIX = 'pix';
    case CARD = 'card';
    case TED = 'ted';
    case SPEI = 'spei';
    case TRANSFERS = 'transfers';
    case PSE = 'pse';
    case INTERNATIONAL_SWIFT = 'international_swift';
    case RTP = 'rtp';
}

readonly class PayerRules
{
    public function __construct(
        public ?array $pixAllowedTaxIds,
        public ?string $transfersAllowedTaxId = null,
        public ?array $pseAllowedTaxIds = null,
        public ?string $pseFullName = null,
        public ?string $pseDocumentType = null,
        public ?string $pseDocumentNumber = null,
        public ?string $pseEmail = null,
        public ?string $psePhone = null,
        public ?string $pseBankCode = null
    ) {}

    public function toArray(): array
    {
        return [
            'pix_allowed_tax_ids' => $this->pixAllowedTaxIds,
            'transfers_allowed_tax_id' => $this->transfersAllowedTaxId,
            'pse_allowed_tax_ids' => $this->pseAllowedTaxIds,
            'pse_full_name' => $this->pseFullName,
            'pse_document_type' => $this->pseDocumentType,
            'pse_document_number' => $this->pseDocumentNumber,
            'pse_email' => $this->pseEmail,
            'pse_phone' => $this->psePhone,
            'pse_bank_code' => $this->pseBankCode,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            pixAllowedTaxIds: $data['pix_allowed_tax_ids'] ?? null,
            transfersAllowedTaxId: $data['transfers_allowed_tax_id'] ?? null,
            pseAllowedTaxIds: $data['pse_allowed_tax_ids'] ?? null,
            pseFullName: $data['pse_full_name'] ?? null,
            pseDocumentType: $data['pse_document_type'] ?? null,
            pseDocumentNumber: $data['pse_document_number'] ?? null,
            pseEmail: $data['pse_email'] ?? null,
            psePhone: $data['pse_phone'] ?? null,
            pseBankCode: $data['pse_bank_code'] ?? null
        );
    }
}

/**
 * A payin quote. `requestAmount` is integer cents of the side `currencyType` names (1000 = 10.00);
 * for whole-unit sender currencies, a `sender` amount must be a multiple of 100. Deliver to a
 * blockchain wallet (`blockchainWalletId`) or a managed wallet (`walletId`).
 */
readonly class CreatePayinQuoteInput
{
    public function __construct(
        public ?string $blockchainWalletId,
        public CurrencyType $currencyType,
        public PaymentMethod $paymentMethod,
        public int $requestAmount,
        public StablecoinToken $token,
        public ?bool $coverFees = null,
        public ?PayerRules $payerRules = null,
        public ?string $partnerFeeId = null,
        public ?bool $isOtc = null,
        public ?string $walletId = null,
        public ?string $fundingBankAccountId = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'blockchain_wallet_id' => $this->blockchainWalletId,
            'wallet_id' => $this->walletId,
            'currency_type' => $this->currencyType->value,
            'payment_method' => $this->paymentMethod->value,
            'request_amount' => $this->requestAmount,
            'token' => $this->token->value,
            'cover_fees' => $this->coverFees,
            'payer_rules' => $this->payerRules?->toArray(),
            'partner_fee_id' => $this->partnerFeeId,
            'is_otc' => $this->isOtc,
            'funding_bank_account_id' => $this->fundingBankAccountId,
        ], static fn ($value) => $value !== null);
    }
}

readonly class CreatePayinQuoteResponse
{
    public function __construct(
        public string $id,
        /** Unix time in milliseconds */
        public ?int $expiresAt,
        public ?float $commercialQuotation,
        public ?float $blindpayQuotation,
        public ?float $receiverAmount,
        public ?float $senderAmount,
        public ?float $partnerFeeAmount = null,
        public ?float $flatFee = null,
        public ?float $billingFeeAmount = null,
        public ?bool $isOtc = null,
        /** card payment instructions, for `card` quotes */
        public ?array $cardInstruction = null,
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
            billingFeeAmount: isset($data['billing_fee_amount']) ? (float) $data['billing_fee_amount'] : null,
            isOtc: $data['is_otc'] ?? null,
            cardInstruction: $data['card_instruction'] ?? null,
        );
    }
}

readonly class GetPayinFxRateInput
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

    /**
     * Create a payin quote
     *
     * @param  CreatePayinQuoteInput  $input  The quote input parameters
     * @return BlindPayApiResponse<CreatePayinQuoteResponse>
     */
    public function create(
        CreatePayinQuoteInput $input,
        ?RequestOptions $options = null
    ): BlindPayApiResponse {
        $response = $this->client->post(
            Path::of('instances/%s/payin-quotes', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => CreatePayinQuoteResponse::fromArray($data));
    }

    /**
     * Get FX rate for payin conversion
     *
     * @param  GetPayinFxRateInput  $input  The FX rate request parameters
     * @return BlindPayApiResponse<GetPayinFxRateResponse>
     */
    public function getFxRate(GetPayinFxRateInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/payin-quotes/fx', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => GetPayinFxRateResponse::fromArray($data));
    }
}
