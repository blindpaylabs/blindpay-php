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
        public TransactionStatus|string $status,
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
        public TransactionDocumentType|string|null $transactionDocumentType,
        public ?string $transactionDocumentId,
        public ?string $name,
        public Rail|string|null $type,
        public ?string $pixKey,
        public ?string $accountNumber,
        public ?string $routingNumber,
        public Country|string|null $country,
        public AccountClass|string|null $accountClass,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $city,
        public ?string $stateProvinceRegion,
        public ?string $postalCode,
        public BankAccountType|string|null $accountType,
        public ?string $achCopBeneficiaryFirstName,
        public ?string $achCopBankAccount,
        public ?string $achCopBankCode,
        public ?string $achCopBeneficiaryLastName,
        public ?string $achCopDocumentId,
        public ?string $achCopDocumentType,
        public ?string $achCopEmail,
        public ?string $beneficiaryName,
        public ?string $speiClabe,
        public SpeiProtocol|string|null $speiProtocol,
        public ?string $speiInstitutionCode,
        public Country|string|null $swiftBeneficiaryCountry,
        public ?string $swiftCodeBic,
        public ?string $swiftAccountHolderName,
        public ?string $swiftAccountNumberIban,
        public ?string $transfersAccount,
        public ArgentinaTransferType|string|null $transfersType,
        public ?bool $hasVirtualAccount,
        public ?string $partnerFeeId = null,
        public ?string $tedBankCode = null,
        public ?string $tedBranchCode = null,
        public ?string $tedCpfCnpj = null,
        public ?float $billingFeeAmount = null,
        public ?string $cpnPaymentId = null,
        public ?string $senderLegalName = null,
        public ?int $partnerFee = null,
        public ?array $trackingDocuments = null,
        public ?array $jpmTrackData = null,
        public ?float $transactionFeeAmount = null,
        public ?string $pixSafeBankCode = null,
        public ?string $pixSafeBranchCode = null,
        public ?string $pixSafeCpfCnpj = null,
        public ?string $sepaIban = null,
        public ?string $sepaBeneficiaryLegalName = null,
        public ?string $sepaBeneficiaryAddressLine1 = null,
        public ?string $sepaBeneficiaryCity = null,
        public ?string $sepaBeneficiaryPostalCode = null,
        public Country|string|null $sepaBeneficiaryCountry = null,
        public ?string $fasterPaymentsSortCode = null,
        public ?string $fasterPaymentsAccountNumber = null,
        public ?string $fasterPaymentsBeneficiaryLegalName = null,
        public ?string $fasterPaymentsBeneficiaryAddressLine1 = null,
        public ?string $fasterPaymentsBeneficiaryCity = null,
        public ?string $fasterPaymentsBeneficiaryPostalCode = null,
        public Country|string|null $fasterPaymentsBeneficiaryCountry = null,
        public ?string $bankAccountId = null,
        public ?string $payableId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            customerId: $data['customer_id'],
            id: $data['id'],
            status: Decode::enum(TransactionStatus::class, $data['status']),
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
            transactionDocumentType: Decode::enum(TransactionDocumentType::class, $data['transaction_document_type'] ?? null),
            transactionDocumentId: $data['transaction_document_id'] ?? null,
            name: $data['name'] ?? null,
            type: Decode::enum(Rail::class, $data['type'] ?? null),
            pixKey: $data['pix_key'] ?? null,
            accountNumber: $data['account_number'] ?? null,
            routingNumber: $data['routing_number'] ?? null,
            country: Decode::enum(Country::class, $data['country'] ?? null),
            accountClass: Decode::enum(AccountClass::class, $data['account_class'] ?? null),
            addressLine1: $data['address_line_1'] ?? null,
            addressLine2: $data['address_line_2'] ?? null,
            city: $data['city'] ?? null,
            stateProvinceRegion: $data['state_province_region'] ?? null,
            postalCode: $data['postal_code'] ?? null,
            accountType: Decode::enum(BankAccountType::class, $data['account_type'] ?? null),
            achCopBeneficiaryFirstName: $data['ach_cop_beneficiary_first_name'] ?? null,
            achCopBankAccount: $data['ach_cop_bank_account'] ?? null,
            achCopBankCode: $data['ach_cop_bank_code'] ?? null,
            achCopBeneficiaryLastName: $data['ach_cop_beneficiary_last_name'] ?? null,
            achCopDocumentId: $data['ach_cop_document_id'] ?? null,
            achCopDocumentType: $data['ach_cop_document_type'] ?? null,
            achCopEmail: $data['ach_cop_email'] ?? null,
            beneficiaryName: $data['beneficiary_name'] ?? null,
            speiClabe: $data['spei_clabe'] ?? null,
            speiProtocol: Decode::enum(SpeiProtocol::class, $data['spei_protocol'] ?? null),
            speiInstitutionCode: $data['spei_institution_code'] ?? null,
            swiftBeneficiaryCountry: Decode::enum(Country::class, $data['swift_beneficiary_country'] ?? null),
            swiftCodeBic: $data['swift_code_bic'] ?? null,
            swiftAccountHolderName: $data['swift_account_holder_name'] ?? null,
            swiftAccountNumberIban: $data['swift_account_number_iban'] ?? null,
            transfersAccount: $data['transfers_account'] ?? null,
            transfersType: Decode::enum(ArgentinaTransferType::class, $data['transfers_type'] ?? null),
            hasVirtualAccount: $data['has_virtual_account'] ?? null,
            partnerFeeId: $data['partner_fee_id'] ?? null,
            tedBankCode: $data['ted_bank_code'] ?? null,
            tedBranchCode: $data['ted_branch_code'] ?? null,
            tedCpfCnpj: $data['ted_cpf_cnpj'] ?? null,
            billingFeeAmount: isset($data['billing_fee_amount']) ? (float) $data['billing_fee_amount'] : null,
            cpnPaymentId: $data['cpn_payment_id'] ?? null,
            senderLegalName: $data['sender_legal_name'] ?? null,
            partnerFee: isset($data['partner_fee']) ? (int) $data['partner_fee'] : null,
            trackingDocuments: $data['tracking_documents'] ?? null,
            jpmTrackData: $data['jpm_track_data'] ?? null,
            transactionFeeAmount: isset($data['transaction_fee_amount']) ? (float) $data['transaction_fee_amount'] : null,
            pixSafeBankCode: $data['pix_safe_bank_code'] ?? null,
            pixSafeBranchCode: $data['pix_safe_branch_code'] ?? null,
            pixSafeCpfCnpj: $data['pix_safe_cpf_cnpj'] ?? null,
            sepaIban: $data['sepa_iban'] ?? null,
            sepaBeneficiaryLegalName: $data['sepa_beneficiary_legal_name'] ?? null,
            sepaBeneficiaryAddressLine1: $data['sepa_beneficiary_address_line_1'] ?? null,
            sepaBeneficiaryCity: $data['sepa_beneficiary_city'] ?? null,
            sepaBeneficiaryPostalCode: $data['sepa_beneficiary_postal_code'] ?? null,
            sepaBeneficiaryCountry: Decode::enum(Country::class, $data['sepa_beneficiary_country'] ?? null),
            fasterPaymentsSortCode: $data['faster_payments_sort_code'] ?? null,
            fasterPaymentsAccountNumber: $data['faster_payments_account_number'] ?? null,
            fasterPaymentsBeneficiaryLegalName: $data['faster_payments_beneficiary_legal_name'] ?? null,
            fasterPaymentsBeneficiaryAddressLine1: $data['faster_payments_beneficiary_address_line_1'] ?? null,
            fasterPaymentsBeneficiaryCity: $data['faster_payments_beneficiary_city'] ?? null,
            fasterPaymentsBeneficiaryPostalCode: $data['faster_payments_beneficiary_postal_code'] ?? null,
            fasterPaymentsBeneficiaryCountry: Decode::enum(Country::class, $data['faster_payments_beneficiary_country'] ?? null),
            bankAccountId: $data['bank_account_id'] ?? null,
            payableId: $data['payable_id'] ?? null,
        );
    }
}

readonly class ListPayoutsInput extends PaginationParams
{
    /**
     * @param  int|null  $offset  deprecated: ignored by the API; use $startingAfter
     */
    public function __construct(
        public ?string $customerId = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $startingAfter = null,
        ?string $endingBefore = null,
        public ?TransactionStatus $status = null,
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
            'customer_id' => $this->customerId,
            'status' => $this->status?->value,
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

/**
 * Pay from a wallet: exactly one of `senderWalletAddress` (a customer's blockchain wallet address)
 * or `walletId` (a BlindPay managed wallet); the API checks it.
 */
readonly class AuthorizeStellarTokenInput
{
    public function __construct(
        public string $quoteId,
        public ?string $senderWalletAddress = null,
        public ?string $walletId = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'quote_id' => $this->quoteId,
            'sender_wallet_address' => $this->senderWalletAddress,
            'wallet_id' => $this->walletId,
        ], static fn ($value) => $value !== null);
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

/**
 * Pay from a wallet: exactly one of `senderWalletAddress` (a customer's blockchain wallet address)
 * or `walletId` (a BlindPay managed wallet); the API checks it.
 */
readonly class CreateStellarPayoutInput
{
    public function __construct(
        public string $quoteId,
        public ?string $senderWalletAddress = null,
        /** the signed transaction, for the second step of a self-signed payout */
        public ?string $signedTransaction = null,
        public ?string $walletId = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'quote_id' => $this->quoteId,
            'sender_wallet_address' => $this->senderWalletAddress,
            'wallet_id' => $this->walletId,
            'signed_transaction' => $this->signedTransaction,
        ], static fn ($value) => $value !== null);
    }
}

readonly class CreateStellarPayoutResponse
{
    public function __construct(
        public string $id,
        public TransactionStatus|string $status,
        public string $senderWalletAddress,
        public ?string $customerId,
        public ?TrackingComplete $trackingComplete = null,
        public ?PayoutTrackingPayment $trackingPayment = null,
        public ?TrackingTransaction $trackingTransaction = null,
        public ?TrackingPartnerFee $trackingPartnerFee = null,
        public ?TrackingLiquidity $trackingLiquidity = null,
        public ?float $billingFeeAmount = null,
        public ?float $transactionFeeAmount = null,
        public ?int $partnerFee = null,
        public ?array $trackingDocuments = null,
        public ?string $bankAccountId = null,
        public ?string $offrampWalletId = null,
        public ?string $payableId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: Decode::enum(TransactionStatus::class, $data['status']),
            senderWalletAddress: $data['sender_wallet_address'],
            customerId: $data['customer_id'] ?? null,
            trackingComplete: isset($data['tracking_complete']) ? TrackingComplete::fromArray($data['tracking_complete']) : null,
            trackingPayment: isset($data['tracking_payment']) ? PayoutTrackingPayment::fromArray($data['tracking_payment']) : null,
            trackingTransaction: isset($data['tracking_transaction']) ? TrackingTransaction::fromArray($data['tracking_transaction']) : null,
            trackingPartnerFee: isset($data['tracking_partner_fee']) ? TrackingPartnerFee::fromArray($data['tracking_partner_fee']) : null,
            trackingLiquidity: isset($data['tracking_liquidity']) ? TrackingLiquidity::fromArray($data['tracking_liquidity']) : null,
            billingFeeAmount: isset($data['billing_fee_amount']) ? (float) $data['billing_fee_amount'] : null,
            transactionFeeAmount: isset($data['transaction_fee_amount']) ? (float) $data['transaction_fee_amount'] : null,
            partnerFee: isset($data['partner_fee']) ? (int) $data['partner_fee'] : null,
            trackingDocuments: $data['tracking_documents'] ?? null,
            bankAccountId: $data['bank_account_id'] ?? null,
            offrampWalletId: $data['offramp_wallet_id'] ?? null,
            payableId: $data['payable_id'] ?? null,
        );
    }
}

/**
 * Pay from a wallet: exactly one of `senderWalletAddress` (a customer's blockchain wallet address)
 * or `walletId` (a BlindPay managed wallet); the API checks it.
 */
readonly class CreateEvmPayoutInput
{
    public function __construct(
        public string $quoteId,
        public ?string $senderWalletAddress = null,
        public ?string $walletId = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'quote_id' => $this->quoteId,
            'sender_wallet_address' => $this->senderWalletAddress,
            'wallet_id' => $this->walletId,
        ], static fn ($value) => $value !== null);
    }
}

readonly class CreateEvmPayoutResponse
{
    public function __construct(
        public string $id,
        public TransactionStatus|string $status,
        public string $senderWalletAddress,
        public ?string $customerId,
        public ?TrackingComplete $trackingComplete = null,
        public ?PayoutTrackingPayment $trackingPayment = null,
        public ?TrackingTransaction $trackingTransaction = null,
        public ?TrackingPartnerFee $trackingPartnerFee = null,
        public ?TrackingLiquidity $trackingLiquidity = null,
        public ?float $billingFeeAmount = null,
        public ?float $transactionFeeAmount = null,
        public ?int $partnerFee = null,
        public ?array $trackingDocuments = null,
        public ?string $bankAccountId = null,
        public ?string $offrampWalletId = null,
        public ?string $payableId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: Decode::enum(TransactionStatus::class, $data['status']),
            senderWalletAddress: $data['sender_wallet_address'],
            customerId: $data['customer_id'] ?? null,
            trackingComplete: isset($data['tracking_complete']) ? TrackingComplete::fromArray($data['tracking_complete']) : null,
            trackingPayment: isset($data['tracking_payment']) ? PayoutTrackingPayment::fromArray($data['tracking_payment']) : null,
            trackingTransaction: isset($data['tracking_transaction']) ? TrackingTransaction::fromArray($data['tracking_transaction']) : null,
            trackingPartnerFee: isset($data['tracking_partner_fee']) ? TrackingPartnerFee::fromArray($data['tracking_partner_fee']) : null,
            trackingLiquidity: isset($data['tracking_liquidity']) ? TrackingLiquidity::fromArray($data['tracking_liquidity']) : null,
            billingFeeAmount: isset($data['billing_fee_amount']) ? (float) $data['billing_fee_amount'] : null,
            transactionFeeAmount: isset($data['transaction_fee_amount']) ? (float) $data['transaction_fee_amount'] : null,
            partnerFee: isset($data['partner_fee']) ? (int) $data['partner_fee'] : null,
            trackingDocuments: $data['tracking_documents'] ?? null,
            bankAccountId: $data['bank_account_id'] ?? null,
            offrampWalletId: $data['offramp_wallet_id'] ?? null,
            payableId: $data['payable_id'] ?? null,
        );
    }
}

/**
 * Pay from a wallet: exactly one of `senderWalletAddress` (a customer's blockchain wallet address)
 * or `walletId` (a BlindPay managed wallet); the API checks it.
 */
readonly class CreateSolanaPayoutInput
{
    public function __construct(
        public string $quoteId,
        public ?string $senderWalletAddress = null,
        /** the signed transaction, for the second step of a self-signed payout */
        public ?string $signedTransaction = null,
        public ?string $walletId = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'quote_id' => $this->quoteId,
            'sender_wallet_address' => $this->senderWalletAddress,
            'wallet_id' => $this->walletId,
            'signed_transaction' => $this->signedTransaction,
        ], static fn ($value) => $value !== null);
    }
}

readonly class CreateSolanaPayoutResponse
{
    public function __construct(
        public string $id,
        public TransactionStatus|string $status,
        public string $senderWalletAddress,
        public ?string $customerId,
        public ?TrackingComplete $trackingComplete = null,
        public ?PayoutTrackingPayment $trackingPayment = null,
        public ?TrackingTransaction $trackingTransaction = null,
        public ?TrackingPartnerFee $trackingPartnerFee = null,
        public ?TrackingLiquidity $trackingLiquidity = null,
        public ?float $billingFeeAmount = null,
        public ?float $transactionFeeAmount = null,
        public ?int $partnerFee = null,
        public ?array $trackingDocuments = null,
        public ?string $bankAccountId = null,
        public ?string $offrampWalletId = null,
        public ?string $payableId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: Decode::enum(TransactionStatus::class, $data['status']),
            senderWalletAddress: $data['sender_wallet_address'],
            customerId: $data['customer_id'] ?? null,
            trackingComplete: isset($data['tracking_complete']) ? TrackingComplete::fromArray($data['tracking_complete']) : null,
            trackingPayment: isset($data['tracking_payment']) ? PayoutTrackingPayment::fromArray($data['tracking_payment']) : null,
            trackingTransaction: isset($data['tracking_transaction']) ? TrackingTransaction::fromArray($data['tracking_transaction']) : null,
            trackingPartnerFee: isset($data['tracking_partner_fee']) ? TrackingPartnerFee::fromArray($data['tracking_partner_fee']) : null,
            trackingLiquidity: isset($data['tracking_liquidity']) ? TrackingLiquidity::fromArray($data['tracking_liquidity']) : null,
            billingFeeAmount: isset($data['billing_fee_amount']) ? (float) $data['billing_fee_amount'] : null,
            transactionFeeAmount: isset($data['transaction_fee_amount']) ? (float) $data['transaction_fee_amount'] : null,
            partnerFee: isset($data['partner_fee']) ? (int) $data['partner_fee'] : null,
            trackingDocuments: $data['tracking_documents'] ?? null,
            bankAccountId: $data['bank_account_id'] ?? null,
            offrampWalletId: $data['offramp_wallet_id'] ?? null,
            payableId: $data['payable_id'] ?? null,
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

    /**
     * List payouts
     *
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

    /**
     * @param  array<string, string>  $query
     * @return BlindPayApiResponse<ListPayoutsResponse>
     */
    private function listPage(array $query, ?RequestOptions $options): BlindPayApiResponse
    {
        return $this->client->get(Path::of('instances/%s/payouts', $this->instanceId).Query::build($query), $options)
            ->map(fn (array $data) => ListPayoutsResponse::fromArray($data));
    }

    /**
     * Get a payout by ID
     *
     * @return BlindPayApiResponse<Payout>
     */
    public function get(string $payoutId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($payoutId === '') {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Payout ID cannot be empty')
            );
        }

        $response = $this->client->get(Path::of('instances/%s/payouts/%s', $this->instanceId, $payoutId), $options);

        return $response->map(fn (array $data) => Payout::fromArray($data));
    }

    /**
     * Get payout tracking
     *
     * Public tracking endpoint for BlindPay's web and iOS apps: a server-side call gets 401 in
     * production. Use get() instead.
     *
     * @return BlindPayApiResponse<Payout>
     */
    public function getTrack(string $payoutId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($payoutId === '') {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Payout ID cannot be empty')
            );
        }

        $response = $this->client->get(Path::of('e/payouts/%s', $payoutId), $options);

        return $response->map(fn (array $data) => Payout::fromArray($data));
    }

    /**
     * Authorize Stellar token
     *
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

    /**
     * Create Stellar payout
     *
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

    /**
     * Create EVM payout
     *
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

    /**
     * Create Solana payout
     *
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

    /**
     * Submit payout documents (required for SWIFT payouts)
     *
     * @return BlindPayApiResponse<SubmitPayoutDocumentsResponse>
     */
    public function submitDocuments(SubmitPayoutDocumentsInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($input->payoutId === '') {
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
