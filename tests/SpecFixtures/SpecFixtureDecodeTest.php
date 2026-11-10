<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\SpecFixtures;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\BankAccounts\CreatePixInput;
use BlindPay\SDK\Resources\BankAccounts\DeleteBankAccountInput;
use BlindPay\SDK\Resources\BankAccounts\GetBankAccountInput;
use BlindPay\SDK\Resources\CustodialWallets\CreateCustodialWalletInput;
use BlindPay\SDK\Resources\CustodialWallets\DeleteCustodialWalletInput;
use BlindPay\SDK\Resources\CustodialWallets\GetCustodialWalletInput;
use BlindPay\SDK\Resources\Customers\CreateIndividualWithStandardKYCInput;
use BlindPay\SDK\Resources\Customers\IdentificationDocument;
use BlindPay\SDK\Resources\Customers\LimitIncreaseRequestSupportingDocumentType;
use BlindPay\SDK\Resources\Customers\ListCustomersInput;
use BlindPay\SDK\Resources\Customers\ProofOfAddressDocType;
use BlindPay\SDK\Resources\Customers\RequestLimitIncreaseInput;
use BlindPay\SDK\Resources\Customers\UpdateCustomerInput;
use BlindPay\SDK\Resources\Instances\InstanceMemberRole;
use BlindPay\SDK\Resources\Instances\UpdateInstanceInput;
use BlindPay\SDK\Resources\Instances\UpdateMemberRoleInput;
use BlindPay\SDK\Resources\Ownership\MigrateInstanceOwnershipIn;
use BlindPay\SDK\Resources\PartnerFees\CreatePartnerFeeInput;
use BlindPay\SDK\Resources\Payins\CreatePayinQuoteInput;
use BlindPay\SDK\Resources\Payins\GetPayinFxRateInput;
use BlindPay\SDK\Resources\Payins\ListPayinsInput;
use BlindPay\SDK\Resources\Payins\PaymentMethod as PayinPaymentMethod;
use BlindPay\SDK\Resources\Payouts\AuthorizeStellarTokenInput;
use BlindPay\SDK\Resources\Payouts\CreateEvmPayoutInput;
use BlindPay\SDK\Resources\Payouts\CreateSolanaPayoutInput;
use BlindPay\SDK\Resources\Payouts\CreateStellarPayoutInput;
use BlindPay\SDK\Resources\Payouts\ListPayoutsInput;
use BlindPay\SDK\Resources\Payouts\SubmitPayoutDocumentsInput;
use BlindPay\SDK\Resources\Quotes\CreateQuoteInput;
use BlindPay\SDK\Resources\Quotes\GetFxRateInput;
use BlindPay\SDK\Resources\TermsOfService\InitiateInput;
use BlindPay\SDK\Resources\Transfers\CreateTransferInput;
use BlindPay\SDK\Resources\Transfers\CreateTransferQuoteInput;
use BlindPay\SDK\Resources\Transfers\ListTransfersInput;
use BlindPay\SDK\Resources\Upload\UploadBucket;
use BlindPay\SDK\Resources\Upload\UploadInput;
use BlindPay\SDK\Resources\VirtualAccounts\CreateVirtualAccountInput;
use BlindPay\SDK\Resources\VirtualAccounts\UpdateVirtualAccountInput;
use BlindPay\SDK\Resources\Wallets\CreateBlockchainWalletWithAddressInput;
use BlindPay\SDK\Resources\Wallets\CreateOfframpWalletInput;
use BlindPay\SDK\Resources\Wallets\DeleteBlockchainWalletInput;
use BlindPay\SDK\Resources\Wallets\GetBlockchainWalletInput;
use BlindPay\SDK\Resources\Wallets\GetOfframpWalletInput;
use BlindPay\SDK\Resources\Wallets\ListOfframpWalletsInput;
use BlindPay\SDK\Resources\Wallets\MintUsdbSolanaInput;
use BlindPay\SDK\Resources\Wallets\MintUsdbStellarInput;
use BlindPay\SDK\Resources\Wallets\PrepareSolanaDelegationTransactionInput;
use BlindPay\SDK\Resources\Webhooks\CreateWebhookEndpointInput;
use BlindPay\SDK\Resources\Webhooks\WebhookEvents;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Country;
use BlindPay\SDK\Types\Currency;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\Rail;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TransactionDocumentType;
use Closure;
use ErrorException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Throwable;

/**
 * Decodes every variant of the shared spec-shaped fixtures (tests/Fixtures/spec, copied verbatim
 * from the sdk-parity reference set; see its README.md) through the public resource methods, so
 * list-envelope handling and per-resource parsing are exercised, not only fromArray().
 *
 * PHP warnings (e.g. an undefined array key) are escalated to exceptions here: frameworks such as
 * Laravel do the same in production, so a warning on a spec-shaped body is a crash for them.
 */
final class SpecFixtureDecodeTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__.'/../Fixtures/spec';

    /**
     * Variants this suite requires. `decimals` (1234.56 in decimal fields) is not required yet:
     * several cent amounts are still typed float or int and are fixed in v4.
     */
    private const VARIANTS = ['full', 'required_only', 'nullables_null', 'unknown_enum'];

    /**
     * Operations whose SDK types do not decode every variant yet, with the reason. This list may
     * only shrink: pending_operations_still_fail fails when an entry starts passing, so remove it.
     */
    private const PENDING = [
        'GET /v1/instances/{id}/members' => 'InstanceMember.created_at nullable, role enum closed (audit 4.14)',
        'GET /v1/instances/{instance_id}/customers/{customer_id}/limit-increase' => 'LimitIncreaseRequestStatus closed (audit 4.6)',
        'GET /v1/instances/{instance_id}/customers' => 'audit 8.12',
        'GET /v1/instances/{instance_id}/customers/{id}' => 'audit 8.12',
        'POST /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts' => 'per-rail create responses: nullable fields read as required (audit 4.7)',
        'GET /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts' => 'audit 8.14 (Rail)',
        'GET /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{id}' => 'audit 8.8',
        'POST /v1/instances/{instance_id}/customers/{customer_id}/virtual-accounts' => 'audit 8.15',
        'GET /v1/instances/{instance_id}/customers/{customer_id}/virtual-accounts' => 'audit 8.15',
        'GET /v1/instances/{instance_id}/customers/{customer_id}/virtual-accounts/{id}' => 'audit 8.15',
        'POST /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets' => 'audit 8.14 (Network)',
        'GET /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets' => 'audit 8.14 (Network)',
        'GET /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets/{id}' => 'audit 8.14 (Network)',
        'POST /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{bank_account_id}/offramp-wallets' => 'external_id nullable (audit 4.8)',
        'POST /v1/instances/{instance_id}/quotes' => 'expires_at nullable (audit 4.2)',
        'POST /v1/instances/{instance_id}/quotes/fx' => 'instance_flat_fee optional (audit 4.2)',
        'POST /v1/instances/{instance_id}/customers/{customer_id}/wallets' => 'audit 8.9',
        'GET /v1/instances/{instance_id}/customers/{customer_id}/wallets' => 'audit 8.9',
        'GET /v1/instances/{instance_id}/customers/{customer_id}/wallets/{id}' => 'audit 8.9',
        'GET /v1/instances/{instance_id}/customers/{customer_id}/wallets/{id}/balance' => 'audit 8.9',
        'GET /v1/instances/{instance_id}/webhook-endpoints' => 'audit 8.14 (WebhookEvents)',
        'GET /v1/instances/{instance_id}/partner-fees' => 'audit 8.10',
        'GET /v1/instances/{instance_id}/partner-fees/{id}' => 'audit 8.10',
        'GET /v1/available/bank-details' => 'BankDetail.required optional (audit 4.15)',
        'GET /v1/available/naics' => 'audit 8.11',
        'POST /v1/instances/{instance_id}/mint-usdb-solana' => 'audit 8.13',
        'POST /v1/instances/{instance_id}/prepare-delegate-solana' => 'transaction optional (audit 4.11)',
    ];

    /** Operations the SDK has no method for yet (v4). */
    private const NOT_IMPLEMENTED = [
        'GET /v1/instances/{instance_id}/onboarding/business_details',
        'GET /v1/instances/{instance_id}/onboarding/business_profile',
        'GET /v1/instances/{instance_id}/onboarding/ownership_documents',
        'GET /v1/instances/{id}',
        'GET /v1/instances/{instance_id}/rfi',
        'POST /v1/instances/{instance_id}/rfi',
        'GET /v1/instances/{instance_id}/customers/{customer_id}/rfi',
        'POST /v1/instances/{instance_id}/customers/{customer_id}/rfi',
        'POST /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/plaid',
        'PATCH /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets/{id}',
        'PUT /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{bank_account_id}/offramp-wallets/{id}',
        'GET /v1/instances/{instance_id}/customers/{customer_id}/wallets/{id}/deposits',
        'POST /v1/instances/{instance_id}/payables',
        'GET /v1/instances/{instance_id}/payables',
        'GET /v1/instances/{instance_id}/payables/{id}',
        'DELETE /v1/instances/{instance_id}/payables/{id}',
        'POST /v1/upload/analyze',
        'POST /v1/presign',
    ];

    /** @return array<string, Closure(BlindPay): BlindPayApiResponse> */
    private static function calls(): array
    {
        $c = 'cu_000000000000';
        $id = 'xx_000000000000';

        return [
            // Instances
            'PUT /v1/instances/{id}' => fn (BlindPay $b) => $b->instances->update(new UpdateInstanceInput('Acme')),
            'DELETE /v1/instances/{id}' => fn (BlindPay $b) => $b->instances->delete(),
            'GET /v1/instances/{id}/members' => fn (BlindPay $b) => $b->instances->getMembers(),
            'DELETE /v1/instances/{id}/members/{user_id}' => fn (BlindPay $b) => $b->instances->deleteMember($id),
            'PUT /v1/instances/{id}/members/{user_id}' => fn (BlindPay $b) => $b->instances->updateMemberRole(new UpdateMemberRoleInput($id, InstanceMemberRole::cases()[0])),
            'POST /v1/instances/{id}/ownership' => fn (BlindPay $b) => $b->ownership->migrate(new MigrateInstanceOwnershipIn($id)),
            'POST /v1/e/instances/{instance_id}/tos' => fn (BlindPay $b) => $b->instances->tos->initiate(new InitiateInput('key')),

            // Customers
            'POST /v1/instances/{instance_id}/customers/{customer_id}/limit-increase' => fn (BlindPay $b) => $b->customers->requestLimitIncrease(new RequestLimitIncreaseInput($c, 100.0, 1000.0, 50.0, 'https://example.com/doc.pdf', LimitIncreaseRequestSupportingDocumentType::cases()[0])),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/limit-increase' => fn (BlindPay $b) => $b->customers->getLimitIncreaseRequests($c),
            'POST /v1/instances/{instance_id}/customers' => fn (BlindPay $b) => $b->customers->createIndividualWithStandardKYC(new CreateIndividualWithStandardKYCInput(
                '1 Main St',
                'Austin',
                Country::US,
                '1990-01-01',
                'jane@example.com',
                'Jane',
                null,
                Country::US,
                'https://example.com/id.png',
                IdentificationDocument::cases()[0],
                null,
                'Doe',
                '78701',
                'https://example.com/poa.png',
                ProofOfAddressDocType::cases()[0],
                'TX',
                '123456789',
                'to_000000000000',
            )),
            'GET /v1/instances/{instance_id}/customers' => fn (BlindPay $b) => $b->customers->list(new ListCustomersInput(limit: 10)),
            'GET /v1/instances/{instance_id}/customers/{id}' => fn (BlindPay $b) => $b->customers->get($c),
            'PUT /v1/instances/{instance_id}/customers/{id}' => fn (BlindPay $b) => $b->customers->update(new UpdateCustomerInput($c)),
            'DELETE /v1/instances/{instance_id}/customers/{id}' => fn (BlindPay $b) => $b->customers->delete($c),
            'GET /v1/instances/{instance_id}/limits/customers/{id}' => fn (BlindPay $b) => $b->customers->getLimits($c),

            // Bank accounts
            'POST /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts' => fn (BlindPay $b) => $b->customers->bankAccounts->createPix(new CreatePixInput($c, 'PIX', 'key@example.com')),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts' => fn (BlindPay $b) => $b->customers->bankAccounts->list($c),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{id}' => fn (BlindPay $b) => $b->customers->bankAccounts->get(new GetBankAccountInput($c, $id)),
            'DELETE /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{id}' => fn (BlindPay $b) => $b->customers->bankAccounts->delete(new DeleteBankAccountInput($c, $id)),

            // Virtual accounts
            'POST /v1/instances/{instance_id}/customers/{customer_id}/virtual-accounts' => fn (BlindPay $b) => $b->virtualAccounts->create(new CreateVirtualAccountInput($c, $id, StablecoinToken::USDC)),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/virtual-accounts' => fn (BlindPay $b) => $b->virtualAccounts->list($c),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/virtual-accounts/{id}' => fn (BlindPay $b) => $b->virtualAccounts->get($c, $id),
            'PUT /v1/instances/{instance_id}/customers/{customer_id}/virtual-accounts/{id}' => fn (BlindPay $b) => $b->virtualAccounts->update(new UpdateVirtualAccountInput($c, $id, $id, StablecoinToken::USDC)),

            // Blockchain wallets
            'GET /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets/sign-message' => fn (BlindPay $b) => $b->wallets->blockchain->getMessage($c),
            'POST /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets' => fn (BlindPay $b) => $b->wallets->blockchain->createWithAddress(new CreateBlockchainWalletWithAddressInput($c, 'Main', Network::BASE, '0x0000000000000000000000000000000000000000')),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets' => fn (BlindPay $b) => $b->wallets->blockchain->list($c),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets/{id}' => fn (BlindPay $b) => $b->wallets->blockchain->get(new GetBlockchainWalletInput($c, $id)),
            'DELETE /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets/{id}' => fn (BlindPay $b) => $b->wallets->blockchain->delete(new DeleteBlockchainWalletInput($c, $id)),

            // Offramp wallets
            'POST /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{bank_account_id}/offramp-wallets' => fn (BlindPay $b) => $b->wallets->offramp->create(new CreateOfframpWalletInput($c, $id, 'ext', 'tron')),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{bank_account_id}/offramp-wallets' => fn (BlindPay $b) => $b->wallets->offramp->list(new ListOfframpWalletsInput($c, $id)),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{bank_account_id}/offramp-wallets/{id}' => fn (BlindPay $b) => $b->wallets->offramp->get(new GetOfframpWalletInput($c, $id, $id)),

            // Quotes
            'POST /v1/instances/{instance_id}/quotes' => fn (BlindPay $b) => $b->quotes->create(new CreateQuoteInput($id, CurrencyType::SENDER, 1000.0, null, null, null, null, TransactionDocumentType::cases()[0])),
            'POST /v1/instances/{instance_id}/quotes/fx' => fn (BlindPay $b) => $b->quotes->getFxRate(new GetFxRateInput(CurrencyType::SENDER, Currency::USDC, Currency::BRL, 1000.0)),

            // Payouts
            'GET /v1/instances/{instance_id}/payouts/{id}' => fn (BlindPay $b) => $b->payouts->get($id),
            'GET /v1/e/payouts/{id}' => fn (BlindPay $b) => $b->payouts->getTrack($id),
            'GET /v1/instances/{instance_id}/payouts' => fn (BlindPay $b) => $b->payouts->list(new ListPayoutsInput(limit: 10)),
            'POST /v1/instances/{instance_id}/payouts/stellar/authorize' => fn (BlindPay $b) => $b->payouts->authorizeStellarToken(new AuthorizeStellarTokenInput($id, 'G000')),
            'POST /v1/instances/{instance_id}/payouts/stellar' => fn (BlindPay $b) => $b->payouts->createStellar(new CreateStellarPayoutInput($id, 'G000')),
            'POST /v1/instances/{instance_id}/payouts/solana' => fn (BlindPay $b) => $b->payouts->createSolana(new CreateSolanaPayoutInput($id, 'So11')),
            'POST /v1/instances/{instance_id}/payouts/evm' => fn (BlindPay $b) => $b->payouts->createEvm(new CreateEvmPayoutInput($id, '0x0')),
            'POST /v1/instances/{instance_id}/payouts/{id}/documents' => fn (BlindPay $b) => $b->payouts->submitDocuments(new SubmitPayoutDocumentsInput($id, TransactionDocumentType::cases()[0], 'doc', 'https://example.com/doc.pdf')),

            // Payins
            'POST /v1/instances/{instance_id}/payin-quotes' => fn (BlindPay $b) => $b->payins->quotes->create(new CreatePayinQuoteInput($id, CurrencyType::SENDER, PayinPaymentMethod::cases()[0], 1000.0, StablecoinToken::USDC, null, null)),
            'POST /v1/instances/{instance_id}/payin-quotes/fx' => fn (BlindPay $b) => $b->payins->quotes->getFxRate(new GetPayinFxRateInput(CurrencyType::SENDER, Currency::BRL, Currency::USDC, 1000.0)),
            'GET /v1/instances/{instance_id}/payins/{id}' => fn (BlindPay $b) => $b->payins->get($id),
            'GET /v1/e/payins/{id}' => fn (BlindPay $b) => $b->payins->getTrack($id),
            'GET /v1/instances/{instance_id}/payins' => fn (BlindPay $b) => $b->payins->list(new ListPayinsInput(limit: 10)),
            'POST /v1/instances/{instance_id}/payins/evm' => fn (BlindPay $b) => $b->payins->createEvm($id),

            // Custodial wallets
            'POST /v1/instances/{instance_id}/customers/{customer_id}/wallets' => fn (BlindPay $b) => $b->wallets->custodial->create(new CreateCustodialWalletInput($c, Network::BASE, 'Main')),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/wallets' => fn (BlindPay $b) => $b->wallets->custodial->list($c),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/wallets/{id}' => fn (BlindPay $b) => $b->wallets->custodial->get(new GetCustodialWalletInput($c, $id)),
            'DELETE /v1/instances/{instance_id}/customers/{customer_id}/wallets/{id}' => fn (BlindPay $b) => $b->wallets->custodial->delete(new DeleteCustodialWalletInput($c, $id)),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/wallets/{id}/balance' => fn (BlindPay $b) => $b->wallets->custodial->getBalance(new GetCustodialWalletInput($c, $id)),

            // Transfers
            'POST /v1/instances/{instance_id}/transfer-quotes' => fn (BlindPay $b) => $b->transfers->createQuote(new CreateTransferQuoteInput($id, StablecoinToken::USDC, '0x0', StablecoinToken::USDC, Network::BASE, 1000, CurrencyType::SENDER)),
            'GET /v1/instances/{instance_id}/transfers/{id}' => fn (BlindPay $b) => $b->transfers->get($id),
            'GET /v1/e/transfers/{id}' => fn (BlindPay $b) => $b->transfers->getTrack($id),
            'GET /v1/instances/{instance_id}/transfers' => fn (BlindPay $b) => $b->transfers->list(new ListTransfersInput(limit: 10)),
            'POST /v1/instances/{instance_id}/transfers' => fn (BlindPay $b) => $b->transfers->create(new CreateTransferInput($id)),

            // Webhook endpoints
            'POST /v1/instances/{instance_id}/webhook-endpoints' => fn (BlindPay $b) => $b->instances->webhookEndpoints->create(new CreateWebhookEndpointInput('https://example.com/hook', [WebhookEvents::cases()[0]])),
            'GET /v1/instances/{instance_id}/webhook-endpoints' => fn (BlindPay $b) => $b->instances->webhookEndpoints->list(),
            'DELETE /v1/instances/{instance_id}/webhook-endpoints/{id}' => fn (BlindPay $b) => $b->instances->webhookEndpoints->delete($id),
            'GET /v1/instances/{instance_id}/webhook-endpoints/{id}/secret' => fn (BlindPay $b) => $b->instances->webhookEndpoints->getSecret($id),
            'GET /v1/instances/{instance_id}/webhook-endpoints/portal-access' => fn (BlindPay $b) => $b->instances->webhookEndpoints->getPortalAccessUrl(),

            // Fees and partner fees
            'GET /v1/instances/{instance_id}/billing/fees' => fn (BlindPay $b) => $b->fees->get(),
            'POST /v1/instances/{instance_id}/partner-fees' => fn (BlindPay $b) => $b->partnerFees->create(new CreatePartnerFeeInput('0x0', 'Fee', 100.0, 1.0, 100.0, 1.0)),
            'GET /v1/instances/{instance_id}/partner-fees' => fn (BlindPay $b) => $b->partnerFees->list(),
            'GET /v1/instances/{instance_id}/partner-fees/{id}' => fn (BlindPay $b) => $b->partnerFees->get($id),
            'DELETE /v1/instances/{instance_id}/partner-fees/{id}' => fn (BlindPay $b) => $b->partnerFees->delete($id),

            // Upload (JSON body today; multipart is a v4 change)
            'POST /v1/upload' => fn (BlindPay $b) => $b->upload->create(new UploadInput(UploadBucket::cases()[0], 'data')),

            // Available
            'GET /v1/available/bank-details' => fn (BlindPay $b) => $b->available->getBankDetails(Rail::PIX),
            'GET /v1/available/rails' => fn (BlindPay $b) => $b->available->getRails(),
            'GET /v1/available/naics' => fn (BlindPay $b) => $b->available->getNaicsCodes(),
            'GET /v1/available/swift/{swift}' => fn (BlindPay $b) => $b->available->getSwiftCodeBankDetails('BOFAUS3N'),

            // Solana / Stellar helpers
            'POST /v1/instances/{instance_id}/mint-usdb-solana' => fn (BlindPay $b) => $b->wallets->blockchain->mintUsdbSolana(new MintUsdbSolanaInput('So11', '1000')),
            'POST /v1/instances/{instance_id}/prepare-delegate-solana' => fn (BlindPay $b) => $b->wallets->blockchain->prepareSolanaDelegationTransaction(new PrepareSolanaDelegationTransactionInput('So11', '1000', 'So11')),
            'POST /v1/instances/{instance_id}/create-asset-trustline' => fn (BlindPay $b) => $b->wallets->blockchain->createAssetTrustline('G000'),
            'POST /v1/instances/{instance_id}/mint-usdb-stellar' => fn (BlindPay $b) => $b->wallets->blockchain->mintUsdbStellar(new MintUsdbStellarInput('G000', '1000', 'AAAA')),
        ];
    }

    /** @return array<string, mixed> */
    private static function index(): array
    {
        return json_decode((string) file_get_contents(self::FIXTURE_DIR.'/index.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> variant name => body */
    private static function variants(string $operation): array
    {
        $fixture = json_decode((string) file_get_contents(self::FIXTURE_DIR.'/'.self::index()['operations'][$operation]), true, flags: JSON_THROW_ON_ERROR);
        $out = [];
        foreach ($fixture['responses']['200']['variants'] as $name => $body) {
            if (in_array(explode('.', $name)[0], self::VARIANTS, true)) {
                $out[$name] = $body;
            }
        }

        return $out;
    }

    /** @return array<string, array{string, string}> */
    public static function cases(): array
    {
        $cases = [];
        foreach (array_keys(self::calls()) as $operation) {
            if (isset(self::PENDING[$operation])) {
                continue;
            }
            foreach (array_keys(self::variants($operation)) as $variant) {
                $cases["{$operation} [{$variant}]"] = [$operation, $variant];
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('cases')]
    public function every_spec_variant_decodes(string $operation, string $variant): void
    {
        $error = $this->decode($operation, self::variants($operation)[$variant]);

        $this->assertNull($error, "{$operation} [{$variant}]: {$error}");
    }

    #[Test]
    public function every_spec_operation_is_mapped_pending_or_not_implemented(): void
    {
        $known = [...array_keys(self::calls()), ...self::NOT_IMPLEMENTED];
        $missing = array_diff(array_keys(self::index()['operations']), $known);

        $this->assertSame([], array_values($missing));
        $this->assertSame([], array_values(array_diff(array_keys(self::PENDING), array_keys(self::calls()))));
    }

    #[Test]
    public function pending_operations_still_fail(): void
    {
        foreach (array_keys(self::PENDING) as $operation) {
            $failures = 0;
            foreach (self::variants($operation) as $body) {
                if ($this->decode($operation, $body) !== null) {
                    $failures++;
                }
            }
            $this->assertGreaterThan(0, $failures, "{$operation} now decodes every variant: remove it from PENDING");
        }
        $this->addToAssertionCount(1);
    }

    /** Decode $body through the operation's SDK method. Returns null on success, else the failure. */
    private function decode(string $operation, mixed $body): ?string
    {
        $mock = new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR))]);
        $blindpay = new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000');
        (new ReflectionProperty(BlindPay::class, 'httpClient'))->setValue($blindpay, new Client(['handler' => HandlerStack::create($mock)]));

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $response = self::calls()[$operation]($blindpay);
        } catch (Throwable $e) {
            return get_class($e).': '.$e->getMessage().' at '.basename($e->getFile()).':'.$e->getLine();
        } finally {
            restore_error_handler();
        }

        if (! $response->isSuccess()) {
            return 'error response: '.$response->error?->message;
        }

        return null;
    }
}
