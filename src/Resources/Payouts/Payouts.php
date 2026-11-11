<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Payouts;

use BlindPay\SDK\BlindPayApiException;
use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\AutoPager;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Internal\Query;
use BlindPay\SDK\Types\AccountClass;
use BlindPay\SDK\Types\BankAccountType;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Country;
use BlindPay\SDK\Types\Currency;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\ListResponse;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\PaginationParams;
use BlindPay\SDK\Types\PayoutTrackingPayment;
use BlindPay\SDK\Types\Rail;
use BlindPay\SDK\Types\RequestOptions;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TrackingComplete;
use BlindPay\SDK\Types\TrackingLiquidity;
use BlindPay\SDK\Types\TrackingPartnerFee;
use BlindPay\SDK\Types\TrackingTransaction;
use BlindPay\SDK\Types\TransactionDocumentType;
use BlindPay\SDK\Types\TransactionStatus;
use DateTimeImmutable;
use Generator;

enum SpeiProtocol: string
{
    case CLABE = 'clabe';
    case DEBITCARD = 'debitcard';
    case PHONENUM = 'phonenum';
}

enum ArgentinaTransferType: string
{
    case CVU = 'CVU';
    case CBU = 'CBU';
    case ALIAS = 'ALIAS';
}

readonly class Payout
{
    public function __construct(
        public string $customerId,
        public string $id,
        public TransactionStatus $status,
        public string $senderWalletAddress,
        public ?string $signedTransaction,
        public string $quoteId,
        public string $instanceId,
        public TrackingTransaction $trackingTransaction,
        public PayoutTrackingPayment $trackingPayment,
        public ?TrackingLiquidity $trackingLiquidity,
        public TrackingComplete $trackingComplete,
        /** @deprecated The API no longer returns tracking_partner_fee on payouts; always null. */
        public ?TrackingPartnerFee $trackingPartnerFee,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public ?string $imageUrl,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $legalName,
        public Network|string $network,
        public StablecoinToken|string $token,
        public ?string $description,
        public ?float $senderAmount,
        public ?float $receiverAmount,
        public ?float $partnerFeeAmount,
        public ?float $commercialQuotation,
        public ?float $blindpayQuotation,
        public ?float $totalFeeAmount,
        public ?float $customerLocalAmount,
        public Currency|string $currency,
        public ?string $transactionDocumentFile,
        public ?TransactionDocumentType $transactionDocumentType,
        public ?string $transactionDocumentId,
        public ?string $name,
        public Rail|string|null $type,
        public ?string $pixKey,
        public ?string $accountNumber,
        public ?string $routingNumber,
        public ?Country $country,
        public ?AccountClass $accountClass,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $city,
        public ?string $stateProvinceRegion,
        public ?string $postalCode,
        public ?BankAccountType $accountType,
        public ?string $achCopBeneficiaryFirstName,
        public ?string $achCopBankAccount,
        public ?string $achCopBankCode,
        public ?string $achCopBeneficiaryLastName,
        public ?string $achCopDocumentId,
        public ?string $achCopDocumentType,
        public ?string $achCopEmail,
        public ?string $beneficiaryName,
        public ?string $speiClabe,
        public ?SpeiProtocol $speiProtocol,
        public ?string $speiInstitutionCode,
        public ?Country $swiftBeneficiaryCountry,
        public ?string $swiftCodeBic,
        public ?string $swiftAccountHolderName,
        public ?string $swiftAccountNumberIban,
        public ?string $transfersAccount,
        public ?ArgentinaTransferType $transfersType,
        public ?bool $hasVirtualAccount,
        public ?string $partnerFeeId = null,
        public ?string $tedBankCode = null,
        public ?string $tedBranchCode = null,
        public ?string $tedCpfCnpj = null,
        public ?float $billingFeeAmount = null,
        public ?string $cpnPaymentId = null,
        public ?string $senderLegalName = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            customerId: $data['customer_id'],
            id: $data['id'],
            status: TransactionStatus::from($data['status']),
            senderWalletAddress: $data['sender_wallet_address'],
            signedTransaction: $data['signed_transaction'] ?? null,
            quoteId: $data['quote_id'],
            instanceId: $data['instance_id'],
            trackingTransaction: TrackingTransaction::fromArray($data['tracking_transaction']),
            trackingPayment: PayoutTrackingPayment::fromArray($data['tracking_payment']),
            trackingLiquidity: isset($data['tracking_liquidity']) ? TrackingLiquidity::fromArray($data['tracking_liquidity']) : null,
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
            network: Decode::enum(Network::class, $data['network']),
            token: Decode::enum(StablecoinToken::class, $data['token']),
            description: $data['description'] ?? null,
            senderAmount: isset($data['sender_amount']) ? (float) $data['sender_amount'] : null,
            receiverAmount: isset($data['receiver_amount']) ? (float) $data['receiver_amount'] : null,
            partnerFeeAmount: isset($data['partner_fee_amount']) ? (float) $data['partner_fee_amount'] : null,
            commercialQuotation: isset($data['commercial_quotation']) ? (float) $data['commercial_quotation'] : null,
            blindpayQuotation: isset($data['blindpay_quotation']) ? (float) $data['blindpay_quotation'] : null,
            totalFeeAmount: isset($data['total_fee_amount']) ? (float) $data['total_fee_amount'] : null,
            customerLocalAmount: isset($data['customer_local_amount']) ? (float) $data['customer_local_amount'] : null,
            currency: Decode::enum(Currency::class, $data['currency']),
            transactionDocumentFile: $data['transaction_document_file'] ?? null,
            transactionDocumentType: isset($data['transaction_document_type']) ? TransactionDocumentType::from($data['transaction_document_type']) : null,
            transactionDocumentId: $data['transaction_document_id'] ?? null,
            name: $data['name'] ?? null,
            type: Decode::enum(Rail::class, $data['type'] ?? null),
            pixKey: $data['pix_key'] ?? null,
            accountNumber: $data['account_number'] ?? null,
            routingNumber: $data['routing_number'] ?? null,
            country: isset($data['country']) ? Country::from($data['country']) : null,
            accountClass: isset($data['account_class']) ? AccountClass::from($data['account_class']) : null,
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'] ?? null,
            stateProvinceRegion: $data['state_province_region'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            accountType: isset($data['account_type']) ? BankAccountType::from($data['account_type']) : null,
            achCopBeneficiaryFirstName: $data['ach_cop_beneficiary_first_name'] ?? null,
            achCopBankAccount: $data['ach_cop_bank_account'] ?? null,
            achCopBankCode: $data['ach_cop_bank_code'] ?? null,
            achCopBeneficiaryLastName: $data['ach_cop_beneficiary_last_name'] ?? null,
            achCopDocumentId: $data['ach_cop_document_id'] ?? null,
            achCopDocumentType: $data['ach_cop_document_type'] ?? null,
            achCopEmail: $data['ach_cop_email'] ?? null,
            beneficiaryName: $data['beneficiary_name'] ?? null,
            speiClabe: $data['spei_clabe'] ?? null,
            speiProtocol: isset($data['spei_protocol']) ? SpeiProtocol::from($data['spei_protocol']) : null,
            speiInstitutionCode: $data['spei_institution_code'] ?? null,
            swiftBeneficiaryCountry: isset($data['swift_beneficiary_country']) ? Country::from($data['swift_beneficiary_country']) : null,
            swiftCodeBic: $data['swift_code_bic'] ?? null,
            swiftAccountHolderName: $data['swift_account_holder_name'] ?? null,
            swiftAccountNumberIban: $data['swift_account_number_iban'] ?? null,
            transfersAccount: $data['transfers_account'] ?? null,
            transfersType: isset($data['transfers_type']) ? ArgentinaTransferType::from($data['transfers_type']) : null,
            hasVirtualAccount: $data['has_virtual_account'] ?? null,
            partnerFeeId: $data['partner_fee_id'] ?? null,
            tedBankCode: $data['ted_bank_code'] ?? null,
            tedBranchCode: $data['ted_branch_code'] ?? null,
            tedCpfCnpj: $data['ted_cpf_cnpj'] ?? null,
            billingFeeAmount: isset($data['billing_fee_amount']) ? (float) $data['billing_fee_amount'] : null,
            cpnPaymentId: $data['cpn_payment_id'] ?? null,
            senderLegalName: $data['sender_legal_name'] ?? null
        );
    }
}

readonly class ListPayoutsInput extends PaginationParams
{
    public function __construct(
        public ?string $customerId = null,
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

        if ($this->customerId !== null) {
            $params['customer_id'] = $this->customerId;
        }

        return $params;
    }
}

/**
 * @extends ListResponse<Payout>
 */
readonly class ListPayoutsResponse extends ListResponse
{
    /** Accepts both shapes the API returns (see ListResponse). */
    public static function fromArray(array $data): self
    {
        return self::decode($data, Payout::fromArray(...));
    }
}

/*
 * Export payouts input
 */
readonly class ExportPayoutsInput
{
    public function __construct(
        public ?int $limit = null,
        public ?int $offset = null
    ) {}

    public function toArray(): array
    {
        $params = [];

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

        return Query::build($params);
    }
}

readonly class AuthorizeStellarTokenInput
{
    public function __construct(
        public string $quoteId,
        public string $senderWalletAddress
    ) {}

    public function toArray(): array
    {
        return [
            'quote_id' => $this->quoteId,
            'sender_wallet_address' => $this->senderWalletAddress,
        ];
    }
}

readonly class AuthorizeStellarTokenResponse
{
    public function __construct(
        public string $transactionHash
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            transactionHash: $data['transaction_hash']
        );
    }
}

readonly class CreateStellarPayoutInput
{
    public function __construct(
        public string $quoteId,
        public string $senderWalletAddress,
        public ?string $signedTransaction = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'quote_id' => $this->quoteId,
            'sender_wallet_address' => $this->senderWalletAddress,
        ];

        if ($this->signedTransaction !== null) {
            $data['signed_transaction'] = $this->signedTransaction;
        }

        return $data;
    }
}

readonly class CreateStellarPayoutResponse
{
    public function __construct(
        public string $id,
        public TransactionStatus $status,
        public string $senderWalletAddress,
        public ?string $customerId,
        public ?TrackingComplete $trackingComplete = null,
        public ?PayoutTrackingPayment $trackingPayment = null,
        public ?TrackingTransaction $trackingTransaction = null,
        public ?TrackingPartnerFee $trackingPartnerFee = null,
        public ?TrackingLiquidity $trackingLiquidity = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: TransactionStatus::from($data['status']),
            senderWalletAddress: $data['sender_wallet_address'],
            customerId: $data['customer_id'] ?? null,
            trackingComplete: isset($data['tracking_complete']) ? TrackingComplete::fromArray($data['tracking_complete']) : null,
            trackingPayment: isset($data['tracking_payment']) ? PayoutTrackingPayment::fromArray($data['tracking_payment']) : null,
            trackingTransaction: isset($data['tracking_transaction']) ? TrackingTransaction::fromArray($data['tracking_transaction']) : null,
            trackingPartnerFee: isset($data['tracking_partner_fee']) ? TrackingPartnerFee::fromArray($data['tracking_partner_fee']) : null,
            trackingLiquidity: isset($data['tracking_liquidity']) ? TrackingLiquidity::fromArray($data['tracking_liquidity']) : null
        );
    }
}

readonly class CreateEvmPayoutInput
{
    public function __construct(
        public string $quoteId,
        public string $senderWalletAddress
    ) {}

    public function toArray(): array
    {
        return [
            'quote_id' => $this->quoteId,
            'sender_wallet_address' => $this->senderWalletAddress,
        ];
    }
}

readonly class CreateEvmPayoutResponse
{
    public function __construct(
        public string $id,
        public TransactionStatus $status,
        public string $senderWalletAddress,
        public ?string $customerId,
        public ?TrackingComplete $trackingComplete = null,
        public ?PayoutTrackingPayment $trackingPayment = null,
        public ?TrackingTransaction $trackingTransaction = null,
        public ?TrackingPartnerFee $trackingPartnerFee = null,
        public ?TrackingLiquidity $trackingLiquidity = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: TransactionStatus::from($data['status']),
            senderWalletAddress: $data['sender_wallet_address'],
            customerId: $data['customer_id'] ?? null,
            trackingComplete: isset($data['tracking_complete']) ? TrackingComplete::fromArray($data['tracking_complete']) : null,
            trackingPayment: isset($data['tracking_payment']) ? PayoutTrackingPayment::fromArray($data['tracking_payment']) : null,
            trackingTransaction: isset($data['tracking_transaction']) ? TrackingTransaction::fromArray($data['tracking_transaction']) : null,
            trackingPartnerFee: isset($data['tracking_partner_fee']) ? TrackingPartnerFee::fromArray($data['tracking_partner_fee']) : null,
            trackingLiquidity: isset($data['tracking_liquidity']) ? TrackingLiquidity::fromArray($data['tracking_liquidity']) : null
        );
    }
}

readonly class CreateSolanaPayoutInput
{
    public function __construct(
        public string $quoteId,
        public string $senderWalletAddress
    ) {}

    public function toArray(): array
    {
        return [
            'quote_id' => $this->quoteId,
            'sender_wallet_address' => $this->senderWalletAddress,
        ];
    }
}

readonly class CreateSolanaPayoutResponse
{
    public function __construct(
        public string $id,
        public TransactionStatus $status,
        public string $senderWalletAddress,
        public ?string $customerId,
        public ?TrackingComplete $trackingComplete = null,
        public ?PayoutTrackingPayment $trackingPayment = null,
        public ?TrackingTransaction $trackingTransaction = null,
        public ?TrackingPartnerFee $trackingPartnerFee = null,
        public ?TrackingLiquidity $trackingLiquidity = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: TransactionStatus::from($data['status']),
            senderWalletAddress: $data['sender_wallet_address'],
            customerId: $data['customer_id'] ?? null,
            trackingComplete: isset($data['tracking_complete']) ? TrackingComplete::fromArray($data['tracking_complete']) : null,
            trackingPayment: isset($data['tracking_payment']) ? PayoutTrackingPayment::fromArray($data['tracking_payment']) : null,
            trackingTransaction: isset($data['tracking_transaction']) ? TrackingTransaction::fromArray($data['tracking_transaction']) : null,
            trackingPartnerFee: isset($data['tracking_partner_fee']) ? TrackingPartnerFee::fromArray($data['tracking_partner_fee']) : null,
            trackingLiquidity: isset($data['tracking_liquidity']) ? TrackingLiquidity::fromArray($data['tracking_liquidity']) : null
        );
    }
}

readonly class SubmitPayoutDocumentsInput
{
    public function __construct(
        public string $payoutId,
        public TransactionDocumentType $transactionDocumentType,
        public string $transactionDocumentId,
        public string $transactionDocumentFile,
        public ?string $description = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'transaction_document_type' => $this->transactionDocumentType->value,
            'transaction_document_id' => $this->transactionDocumentId,
            'transaction_document_file' => $this->transactionDocumentFile,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }
}

readonly class SubmitPayoutDocumentsResponse
{
    public function __construct(
        public bool $success
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success']
        );
    }
}

class Payouts
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * List payouts
     *
     * @param ListPayoutsInput|null $params
     * @return BlindPayApiResponse<ListPayoutsResponse>
     */
    public function list(?ListPayoutsInput $params = null, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->listPage($params?->toArray() ?? [], $options);
    }

    /**
     * Every payout matching $params, fetched lazily page by page (`limit` 100 unless you set one),
     * following `pagination.next_page` until `has_more` is false. Forward only: `endingBefore` is
     * rejected. A failed page request throws BlindPayApiException (it carries the ErrorResponse).
     *
     *     foreach ($blindpay->payouts->listAutoPaging() as $payout) { ... }
     *
     * @return Generator<int, Payout>
     *
     * @throws BlindPayApiException
     */
    public function listAutoPaging(?ListPayoutsInput $params = null, ?RequestOptions $options = null): Generator
    {
        return AutoPager::iterate($params?->toArray() ?? [], fn (array $query) => $this->listPage($query, $options));
    }

    /** @param array<string, string> $query */
    private function listPage(array $query, ?RequestOptions $options): BlindPayApiResponse
    {
        return $this->client->get(Path::of('instances/%s/payouts', $this->instanceId).Query::build($query), $options)
            ->map(fn (array $data) => ListPayoutsResponse::fromArray($data));
    }

    /*
     * Export payouts
     *
     * @param ExportPayoutsInput|null $params
     * @return BlindPayApiResponse<Payout[]>
     */
    public function export(?ExportPayoutsInput $params = null, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $queryParams = $params ? $params->toQueryString() : '';
        $response = $this->client->get(Path::of('instances/%s/export/payouts', $this->instanceId).$queryParams, $options);

        return $response->map(function (array $data) {
            $payouts = array_map(
                fn (array $item) => Payout::fromArray($item),
                $data
            );

            return $payouts;
        });
    }

    /*
     * Get a payout by ID
     *
     * @param string $payoutId
     * @return BlindPayApiResponse<Payout>
     */
    public function get(string $payoutId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($payoutId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Payout ID cannot be empty')
            );
        }

        $response = $this->client->get(Path::of('instances/%s/payouts/%s', $this->instanceId, $payoutId), $options);

        return $response->map(fn (array $data) => Payout::fromArray($data));
    }

    /*
     * Get payout tracking
     *
     * @param string $payoutId
     * @return BlindPayApiResponse<Payout>
     */
    public function getTrack(string $payoutId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($payoutId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Payout ID cannot be empty')
            );
        }

        $response = $this->client->get(Path::of('e/payouts/%s', $payoutId), $options);

        return $response->map(fn (array $data) => Payout::fromArray($data));
    }

    /*
     * Authorize Stellar token
     *
     * @param AuthorizeStellarTokenInput $input
     * @return BlindPayApiResponse<AuthorizeStellarTokenResponse>
     */
    public function authorizeStellarToken(AuthorizeStellarTokenInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/payouts/stellar/authorize', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => AuthorizeStellarTokenResponse::fromArray($data));
    }

    /*
     * Create Stellar payout
     *
     * @param CreateStellarPayoutInput $input
     * @return BlindPayApiResponse<CreateStellarPayoutResponse>
     */
    public function createStellar(CreateStellarPayoutInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/payouts/stellar', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => CreateStellarPayoutResponse::fromArray($data));
    }

    /*
     * Create EVM payout
     *
     * @param CreateEvmPayoutInput $input
     * @return BlindPayApiResponse<CreateEvmPayoutResponse>
     */
    public function createEvm(CreateEvmPayoutInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/payouts/evm', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => CreateEvmPayoutResponse::fromArray($data));
    }

    /*
     * Create Solana payout
     *
     * @param CreateSolanaPayoutInput $input
     * @return BlindPayApiResponse<CreateSolanaPayoutResponse>
     */
    public function createSolana(CreateSolanaPayoutInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/payouts/solana', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => CreateSolanaPayoutResponse::fromArray($data));
    }

    /*
     * Submit payout documents (required for SWIFT payouts)
     *
     * @param SubmitPayoutDocumentsInput $input
     * @return BlindPayApiResponse<SubmitPayoutDocumentsResponse>
     */
    public function submitDocuments(SubmitPayoutDocumentsInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($input->payoutId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Payout ID cannot be empty')
            );
        }

        $response = $this->client->post(
            Path::of('instances/%s/payouts/%s/documents', $this->instanceId, $input->payoutId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => SubmitPayoutDocumentsResponse::fromArray($data));
    }
}
