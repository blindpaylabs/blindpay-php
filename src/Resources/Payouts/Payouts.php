<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Payouts;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\AccountClass;
use BlindPay\SDK\Types\BankAccountType;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Country;
use BlindPay\SDK\Types\Currency;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\PaginationMetadata;
use BlindPay\SDK\Types\PaginationParams;
use BlindPay\SDK\Types\Rail;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TrackingComplete;
use BlindPay\SDK\Types\TrackingLiquidity;
use BlindPay\SDK\Types\TrackingPartnerFee;
use BlindPay\SDK\Types\TrackingPayment;
use BlindPay\SDK\Types\TrackingTransaction;
use BlindPay\SDK\Types\TransactionDocumentType;
use BlindPay\SDK\Types\TransactionStatus;
use DateTimeImmutable;

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
        public string $receiverId,
        public string $id,
        public TransactionStatus $status,
        public string $senderWalletAddress,
        public string $signedTransaction,
        public string $quoteId,
        public string $instanceId,
        public TrackingTransaction $trackingTransaction,
        public TrackingPayment $trackingPayment,
        public TrackingLiquidity $trackingLiquidity,
        public TrackingComplete $trackingComplete,
        public TrackingPartnerFee $trackingPartnerFee,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public string $imageUrl,
        public string $firstName,
        public string $lastName,
        public string $legalName,
        public Network $network,
        public StablecoinToken $token,
        public string $description,
        public float $senderAmount,
        public float $receiverAmount,
        public float $partnerFeeAmount,
        public float $commercialQuotation,
        public float $blindpayQuotation,
        public float $totalFeeAmount,
        public float $receiverLocalAmount,
        public Currency $currency,
        public string $transactionDocumentFile,
        public TransactionDocumentType $transactionDocumentType,
        public string $transactionDocumentId,
        public string $name,
        public Rail $type,
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
        public bool $hasVirtualAccount
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            receiverId: $data['receiver_id'],
            id: $data['id'],
            status: TransactionStatus::from($data['status']),
            senderWalletAddress: $data['sender_wallet_address'],
            signedTransaction: $data['signed_transaction'],
            quoteId: $data['quote_id'],
            instanceId: $data['instance_id'],
            trackingTransaction: TrackingTransaction::fromArray($data['tracking_transaction']),
            trackingPayment: TrackingPayment::fromArray($data['tracking_payment']),
            trackingLiquidity: TrackingLiquidity::fromArray($data['tracking_liquidity']),
            trackingComplete: TrackingComplete::fromArray($data['tracking_complete']),
            trackingPartnerFee: TrackingPartnerFee::fromArray($data['tracking_partner_fee']),
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
            imageUrl: $data['image_url'],
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            legalName: $data['legal_name'],
            network: Network::from($data['network']),
            token: StablecoinToken::from($data['token']),
            description: $data['description'],
            senderAmount: (float) $data['sender_amount'],
            receiverAmount: (float) $data['receiver_amount'],
            partnerFeeAmount: (float) $data['partner_fee_amount'],
            commercialQuotation: (float) $data['commercial_quotation'],
            blindpayQuotation: (float) $data['blindpay_quotation'],
            totalFeeAmount: (float) $data['total_fee_amount'],
            receiverLocalAmount: (float) $data['receiver_local_amount'],
            currency: Currency::from($data['currency']),
            transactionDocumentFile: $data['transaction_document_file'],
            transactionDocumentType: TransactionDocumentType::from($data['transaction_document_type']),
            transactionDocumentId: $data['transaction_document_id'],
            name: $data['name'],
            type: Rail::from($data['type']),
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
            hasVirtualAccount: $data['has_virtual_account']
        );
    }
}

readonly class ListPayoutsInput extends PaginationParams
{
    public function __construct(
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

        if ($this->receiverId !== null) {
            $params['receiver_id'] = $this->receiverId;
        }

        return $params;
    }
}

readonly class ListPayoutsResponse
{
    public function __construct(
        public array $data,
        public PaginationMetadata $pagination
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            data: array_map(
                fn (array $item) => Payout::fromArray($item),
                $data['data']
            ),
            pagination: PaginationMetadata::fromArray($data['pagination'])
        );
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

        return empty($params) ? '' : '?'.http_build_query($params);
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
        public string $receiverId,
        public ?TrackingComplete $trackingComplete = null,
        public ?TrackingPayment $trackingPayment = null,
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
            receiverId: $data['receiver_id'],
            trackingComplete: isset($data['tracking_complete']) ? TrackingComplete::fromArray($data['tracking_complete']) : null,
            trackingPayment: isset($data['tracking_payment']) ? TrackingPayment::fromArray($data['tracking_payment']) : null,
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
        public string $receiverId,
        public ?TrackingComplete $trackingComplete = null,
        public ?TrackingPayment $trackingPayment = null,
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
            receiverId: $data['receiver_id'],
            trackingComplete: isset($data['tracking_complete']) ? TrackingComplete::fromArray($data['tracking_complete']) : null,
            trackingPayment: isset($data['tracking_payment']) ? TrackingPayment::fromArray($data['tracking_payment']) : null,
            trackingTransaction: isset($data['tracking_transaction']) ? TrackingTransaction::fromArray($data['tracking_transaction']) : null,
            trackingPartnerFee: isset($data['tracking_partner_fee']) ? TrackingPartnerFee::fromArray($data['tracking_partner_fee']) : null,
            trackingLiquidity: isset($data['tracking_liquidity']) ? TrackingLiquidity::fromArray($data['tracking_liquidity']) : null
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
    public function list(?ListPayoutsInput $params = null): BlindPayApiResponse
    {
        $queryParams = $params ? $params->toQueryString() : '';
        $response = $this->client->get("/instances/{$this->instanceId}/payouts{$queryParams}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                ListPayoutsResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Export payouts
     *
     * @param ExportPayoutsInput|null $params
     * @return BlindPayApiResponse<Payout[]>
     */
    public function export(?ExportPayoutsInput $params = null): BlindPayApiResponse
    {
        $queryParams = $params ? $params->toQueryString() : '';
        $response = $this->client->get("/instances/{$this->instanceId}/export/payouts{$queryParams}");

        if ($response->isSuccess() && is_array($response->data)) {
            $payouts = array_map(
                fn (array $item) => Payout::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($payouts);
        }

        return $response;
    }

    /*
     * Get a payout by ID
     *
     * @param string $payoutId
     * @return BlindPayApiResponse<Payout>
     */
    public function get(string $payoutId): BlindPayApiResponse
    {
        if (empty($payoutId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Payout ID cannot be empty')
            );
        }

        $response = $this->client->get("/instances/{$this->instanceId}/payouts/{$payoutId}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                Payout::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get payout tracking
     *
     * @param string $payoutId
     * @return BlindPayApiResponse<Payout>
     */
    public function getTrack(string $payoutId): BlindPayApiResponse
    {
        if (empty($payoutId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Payout ID cannot be empty')
            );
        }

        $response = $this->client->get("/e/payouts/{$payoutId}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                Payout::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Authorize Stellar token
     *
     * @param AuthorizeStellarTokenInput $input
     * @return BlindPayApiResponse<AuthorizeStellarTokenResponse>
     */
    public function authorizeStellarToken(AuthorizeStellarTokenInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/payouts/stellar/authorize",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                AuthorizeStellarTokenResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create Stellar payout
     *
     * @param CreateStellarPayoutInput $input
     * @return BlindPayApiResponse<CreateStellarPayoutResponse>
     */
    public function createStellar(CreateStellarPayoutInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/payouts/stellar",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateStellarPayoutResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create EVM payout
     *
     * @param CreateEvmPayoutInput $input
     * @return BlindPayApiResponse<CreateEvmPayoutResponse>
     */
    public function createEvm(CreateEvmPayoutInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/payouts/evm",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateEvmPayoutResponse::fromArray($response->data)
            );
        }

        return $response;
    }
}
