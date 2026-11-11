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
use BlindPay\SDK\Resources\CustodialWallets\GetWalletDepositsInput;
use BlindPay\SDK\Resources\Customers\CreateIndividualWithStandardKYCInput;
use BlindPay\SDK\Resources\Customers\Customers;
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
use BlindPay\SDK\Resources\Payables\CreatePayableInput;
use BlindPay\SDK\Resources\Payables\ListPayablesInput;
use BlindPay\SDK\Resources\Payables\PayableStatus;
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
use BlindPay\SDK\Resources\Upload\AnalyzeUploadInput;
use BlindPay\SDK\Resources\Upload\UploadAnalyzeType;
use BlindPay\SDK\Resources\Upload\UploadBucket;
use BlindPay\SDK\Resources\Upload\UploadFile;
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
use BlindPay\SDK\Resources\Wallets\SetSelfCustodyInput;
use BlindPay\SDK\Resources\Wallets\UpdateOfframpWalletInput;
use BlindPay\SDK\Resources\Webhooks\CreateWebhookEndpointInput;
use BlindPay\SDK\Resources\Webhooks\WebhookEvents;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Country;
use BlindPay\SDK\Types\Currency;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\Rail;
use BlindPay\SDK\Types\RequestOptions;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TransactionDocumentType;
use Closure;
use ErrorException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
     * only shrink: it_keeps_pending_operations_failing fails when an entry starts passing, so remove it.
     */
    private const PENDING = [
        'POST /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts' => 'the ten per-rail Create*Response types read their own rail fields as required. The API echoes those from the request, so live creates decode; only the spec\'s all-rail shape (every rail field nullable) fails. Not a crash fix, left for v4 (audit 4.7)',
    ];

    /** @return array<string, Closure(BlindPay, ?RequestOptions): BlindPayApiResponse> */
    private static function calls(): array
    {
        $c = 'cu_000000000000';
        $id = 'xx_000000000000';

        return [
            // Instances
            'PUT /v1/instances/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->update(new UpdateInstanceInput('Acme'), $o),
            'DELETE /v1/instances/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->delete($o),
            'GET /v1/instances/{id}/members' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->getMembers($o),
            'DELETE /v1/instances/{id}/members/{user_id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->deleteMember($id, $o),
            'PUT /v1/instances/{id}/members/{user_id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->updateMemberRole(new UpdateMemberRoleInput($id, InstanceMemberRole::cases()[0]), $o),
            'POST /v1/instances/{id}/ownership' => fn (BlindPay $b, ?RequestOptions $o) => $b->ownership->migrate(new MigrateInstanceOwnershipIn($id), $o),
            'POST /v1/e/instances/{instance_id}/tos' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->tos->initiate(new InitiateInput('key'), $o),

            // Customers
            'POST /v1/instances/{instance_id}/customers/{customer_id}/limit-increase' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->requestLimitIncrease(new RequestLimitIncreaseInput($c, 100.0, 1000.0, 50.0, 'https://example.com/doc.pdf', LimitIncreaseRequestSupportingDocumentType::cases()[0]), $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/limit-increase' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->getLimitIncreaseRequests($c, $o),
            'POST /v1/instances/{instance_id}/customers' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->createIndividualWithStandardKYC(new CreateIndividualWithStandardKYCInput(
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
            ), $o),
            'GET /v1/instances/{instance_id}/customers' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->list(new ListCustomersInput(limit: 10), $o),
            'GET /v1/instances/{instance_id}/customers/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->get($c, $o),
            'PUT /v1/instances/{instance_id}/customers/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->update(new UpdateCustomerInput($c), $o),
            'DELETE /v1/instances/{instance_id}/customers/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->delete($c, $o),
            'GET /v1/instances/{instance_id}/limits/customers/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->getLimits($c, $o),

            // Bank accounts
            'POST /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->bankAccounts->createPix(new CreatePixInput($c, 'PIX', 'key@example.com'), $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->bankAccounts->list($c, $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->bankAccounts->get(new GetBankAccountInput($c, $id), $o),
            'DELETE /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->bankAccounts->delete(new DeleteBankAccountInput($c, $id), $o),

            // Virtual accounts
            'POST /v1/instances/{instance_id}/customers/{customer_id}/virtual-accounts' => fn (BlindPay $b, ?RequestOptions $o) => $b->virtualAccounts->create(new CreateVirtualAccountInput($c, $id, StablecoinToken::USDC), $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/virtual-accounts' => fn (BlindPay $b, ?RequestOptions $o) => $b->virtualAccounts->list($c, $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/virtual-accounts/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->virtualAccounts->get($c, $id, $o),
            'PUT /v1/instances/{instance_id}/customers/{customer_id}/virtual-accounts/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->virtualAccounts->update(new UpdateVirtualAccountInput($c, $id, $id, StablecoinToken::USDC), $o),

            // Blockchain wallets
            'GET /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets/sign-message' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->blockchain->getMessage($c, $o),
            'POST /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->blockchain->createWithAddress(new CreateBlockchainWalletWithAddressInput($c, 'Main', Network::BASE, '0x0000000000000000000000000000000000000000'), $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->blockchain->list($c, $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->blockchain->get(new GetBlockchainWalletInput($c, $id), $o),
            'DELETE /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->blockchain->delete(new DeleteBlockchainWalletInput($c, $id), $o),

            // Offramp wallets
            'POST /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{bank_account_id}/offramp-wallets' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->offramp->create(new CreateOfframpWalletInput($c, $id, 'ext', 'tron'), $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{bank_account_id}/offramp-wallets' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->offramp->list(new ListOfframpWalletsInput($c, $id), $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{bank_account_id}/offramp-wallets/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->offramp->get(new GetOfframpWalletInput($c, $id, $id), $o),

            // Quotes
            'POST /v1/instances/{instance_id}/quotes' => fn (BlindPay $b, ?RequestOptions $o) => $b->quotes->create(new CreateQuoteInput($id, CurrencyType::SENDER, 1000.0, null, null, null, null, TransactionDocumentType::cases()[0]), $o),
            'POST /v1/instances/{instance_id}/quotes/fx' => fn (BlindPay $b, ?RequestOptions $o) => $b->quotes->getFxRate(new GetFxRateInput(CurrencyType::SENDER, Currency::USDC, Currency::BRL, 1000.0), $o),

            // Payouts
            'GET /v1/instances/{instance_id}/payouts/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->payouts->get($id, $o),
            'GET /v1/e/payouts/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->payouts->getTrack($id, $o),
            'GET /v1/instances/{instance_id}/payouts' => fn (BlindPay $b, ?RequestOptions $o) => $b->payouts->list(new ListPayoutsInput(limit: 10), $o),
            'POST /v1/instances/{instance_id}/payouts/stellar/authorize' => fn (BlindPay $b, ?RequestOptions $o) => $b->payouts->authorizeStellarToken(new AuthorizeStellarTokenInput($id, 'G000'), $o),
            'POST /v1/instances/{instance_id}/payouts/stellar' => fn (BlindPay $b, ?RequestOptions $o) => $b->payouts->createStellar(new CreateStellarPayoutInput($id, 'G000'), $o),
            'POST /v1/instances/{instance_id}/payouts/solana' => fn (BlindPay $b, ?RequestOptions $o) => $b->payouts->createSolana(new CreateSolanaPayoutInput($id, 'So11'), $o),
            'POST /v1/instances/{instance_id}/payouts/evm' => fn (BlindPay $b, ?RequestOptions $o) => $b->payouts->createEvm(new CreateEvmPayoutInput($id, '0x0'), $o),
            'POST /v1/instances/{instance_id}/payouts/{id}/documents' => fn (BlindPay $b, ?RequestOptions $o) => $b->payouts->submitDocuments(new SubmitPayoutDocumentsInput($id, TransactionDocumentType::cases()[0], 'doc', 'https://example.com/doc.pdf'), $o),

            // Payins
            'POST /v1/instances/{instance_id}/payin-quotes' => fn (BlindPay $b, ?RequestOptions $o) => $b->payins->quotes->create(new CreatePayinQuoteInput($id, CurrencyType::SENDER, PayinPaymentMethod::cases()[0], 1000.0, StablecoinToken::USDC, null, null), $o),
            'POST /v1/instances/{instance_id}/payin-quotes/fx' => fn (BlindPay $b, ?RequestOptions $o) => $b->payins->quotes->getFxRate(new GetPayinFxRateInput(CurrencyType::SENDER, Currency::BRL, Currency::USDC, 1000.0), $o),
            'GET /v1/instances/{instance_id}/payins/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->payins->get($id, $o),
            'GET /v1/e/payins/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->payins->getTrack($id, $o),
            'GET /v1/instances/{instance_id}/payins' => fn (BlindPay $b, ?RequestOptions $o) => $b->payins->list(new ListPayinsInput(limit: 10), $o),
            'POST /v1/instances/{instance_id}/payins/evm' => fn (BlindPay $b, ?RequestOptions $o) => $b->payins->createEvm($id, $o),

            // Custodial wallets
            'POST /v1/instances/{instance_id}/customers/{customer_id}/wallets' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->custodial->create(new CreateCustodialWalletInput($c, Network::BASE, 'Main'), $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/wallets' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->custodial->list($c, $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/wallets/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->custodial->get(new GetCustodialWalletInput($c, $id), $o),
            'DELETE /v1/instances/{instance_id}/customers/{customer_id}/wallets/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->custodial->delete(new DeleteCustodialWalletInput($c, $id), $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/wallets/{id}/balance' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->custodial->getBalance(new GetCustodialWalletInput($c, $id), $o),

            // Transfers
            'POST /v1/instances/{instance_id}/transfer-quotes' => fn (BlindPay $b, ?RequestOptions $o) => $b->transfers->createQuote(new CreateTransferQuoteInput($id, StablecoinToken::USDC, '0x0', StablecoinToken::USDC, Network::BASE, 1000, CurrencyType::SENDER), $o),
            'GET /v1/instances/{instance_id}/transfers/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->transfers->get($id, $o),
            'GET /v1/e/transfers/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->transfers->getTrack($id, $o),
            'GET /v1/instances/{instance_id}/transfers' => fn (BlindPay $b, ?RequestOptions $o) => $b->transfers->list(new ListTransfersInput(limit: 10), $o),
            'POST /v1/instances/{instance_id}/transfers' => fn (BlindPay $b, ?RequestOptions $o) => $b->transfers->create(new CreateTransferInput($id), $o),

            // Webhook endpoints
            'POST /v1/instances/{instance_id}/webhook-endpoints' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->webhookEndpoints->create(new CreateWebhookEndpointInput('https://example.com/hook', [WebhookEvents::cases()[0]]), $o),
            'GET /v1/instances/{instance_id}/webhook-endpoints' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->webhookEndpoints->list($o),
            'DELETE /v1/instances/{instance_id}/webhook-endpoints/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->webhookEndpoints->delete($id, $o),
            'GET /v1/instances/{instance_id}/webhook-endpoints/{id}/secret' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->webhookEndpoints->getSecret($id, $o),
            'GET /v1/instances/{instance_id}/webhook-endpoints/portal-access' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->webhookEndpoints->getPortalAccessUrl($o),

            // Fees and partner fees
            'GET /v1/instances/{instance_id}/billing/fees' => fn (BlindPay $b, ?RequestOptions $o) => $b->fees->get($o),
            'POST /v1/instances/{instance_id}/partner-fees' => fn (BlindPay $b, ?RequestOptions $o) => $b->partnerFees->create(new CreatePartnerFeeInput('0x0', 'Fee', 100.0, 1.0, 100.0, 1.0), $o),
            'GET /v1/instances/{instance_id}/partner-fees' => fn (BlindPay $b, ?RequestOptions $o) => $b->partnerFees->list($o),
            'GET /v1/instances/{instance_id}/partner-fees/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->partnerFees->get($id, $o),
            'DELETE /v1/instances/{instance_id}/partner-fees/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->partnerFees->delete($id, $o),

            // Upload
            'POST /v1/upload' => fn (BlindPay $b, ?RequestOptions $o) => $b->upload->create(new UploadInput(UploadBucket::DOCUMENTS, new UploadFile('%PDF-1.4', 'doc.pdf', 'application/pdf')), $o),
            'POST /v1/upload/analyze' => fn (BlindPay $b, ?RequestOptions $o) => $b->upload->analyze(new AnalyzeUploadInput(new UploadFile('%PDF-1.4', 'doc.pdf', 'application/pdf'), UploadAnalyzeType::PROOF_OF_ADDRESS, ['full_name' => 'Jane Doe']), $o),
            'POST /v1/presign' => fn (BlindPay $b, ?RequestOptions $o) => $b->upload->presign('https://example.com/doc.pdf', $o),

            // Payables
            'POST /v1/instances/{instance_id}/payables' => fn (BlindPay $b, ?RequestOptions $o) => $b->payables->create(new CreatePayableInput($c, Currency::BRL, boletoBarcode: '00190000090'), $o),
            'GET /v1/instances/{instance_id}/payables' => fn (BlindPay $b, ?RequestOptions $o) => $b->payables->list(new ListPayablesInput(status: PayableStatus::DRAFT, limit: 10), $o),
            'GET /v1/instances/{instance_id}/payables/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->payables->get($id, $o),
            'DELETE /v1/instances/{instance_id}/payables/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->payables->delete($id, $o),

            // Instance, onboarding and RFIs
            'GET /v1/instances/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->get($o),
            'GET /v1/instances/{instance_id}/onboarding/business_details' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->onboarding->getBusinessDetails($o),
            'GET /v1/instances/{instance_id}/onboarding/business_profile' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->onboarding->getBusinessProfile($o),
            'GET /v1/instances/{instance_id}/onboarding/ownership_documents' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->onboarding->getOwnershipDocuments($o),
            'GET /v1/instances/{instance_id}/rfi' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->rfi->get($o),
            'POST /v1/instances/{instance_id}/rfi' => fn (BlindPay $b, ?RequestOptions $o) => $b->instances->rfi->submit(['company_website' => 'https://example.com'], $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/rfi' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->rfi->get($c, $o),
            'POST /v1/instances/{instance_id}/customers/{customer_id}/rfi' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->rfi->submit($c, ['occupation' => 'engineer'], $o),

            // Wallet extras
            'POST /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/plaid' => fn (BlindPay $b, ?RequestOptions $o) => $b->customers->bankAccounts->createPlaidLink($c, 'https://example.com/done', $o),
            'PATCH /v1/instances/{instance_id}/customers/{customer_id}/blockchain-wallets/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->blockchain->setSelfCustody(new SetSelfCustodyInput($c, $id, true), $o),
            'PUT /v1/instances/{instance_id}/customers/{customer_id}/bank-accounts/{bank_account_id}/offramp-wallets/{id}' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->offramp->update(new UpdateOfframpWalletInput($c, $id, $id, null), $o),
            'GET /v1/instances/{instance_id}/customers/{customer_id}/wallets/{id}/deposits' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->custodial->getDeposits(new GetWalletDepositsInput($c, $id, limit: 5), $o),

            // Available
            'GET /v1/available/bank-details' => fn (BlindPay $b, ?RequestOptions $o) => $b->available->getBankDetails(Rail::PIX, $o),
            'GET /v1/available/rails' => fn (BlindPay $b, ?RequestOptions $o) => $b->available->getRails($o),
            'GET /v1/available/naics' => fn (BlindPay $b, ?RequestOptions $o) => $b->available->getNaicsCodes($o),
            'GET /v1/available/swift/{swift}' => fn (BlindPay $b, ?RequestOptions $o) => $b->available->getSwiftCodeBankDetails('BOFAUS3N', $o),

            // Solana / Stellar helpers
            'POST /v1/instances/{instance_id}/mint-usdb-solana' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->blockchain->mintUsdbSolana(new MintUsdbSolanaInput('So11', '1000'), $o),
            'POST /v1/instances/{instance_id}/prepare-delegate-solana' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->blockchain->prepareSolanaDelegationTransaction(new PrepareSolanaDelegationTransactionInput('So11', '1000', 'So11'), $o),
            'POST /v1/instances/{instance_id}/create-asset-trustline' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->blockchain->createAssetTrustline('G000', $o),
            'POST /v1/instances/{instance_id}/mint-usdb-stellar' => fn (BlindPay $b, ?RequestOptions $o) => $b->wallets->blockchain->mintUsdbStellar(new MintUsdbStellarInput('G000', '1000', 'AAAA'), $o),
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
        $variants = $fixture['responses']['200']['variants'];
        foreach ($variants as $name => $body) {
            if (! in_array(explode('.', $name)[0], self::VARIANTS, true)) {
                continue;
            }
            $out[$name] = $body;
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
    public function it_decodes_every_spec_variant(string $operation, string $variant): void
    {
        $error = $this->decode($operation, self::variants($operation)[$variant]);

        $this->assertNull($error, "{$operation} [{$variant}]: {$error}");
    }

    #[Test]
    public function it_maps_every_spec_operation_or_lists_it_as_pending_or_not_implemented(): void
    {
        $known = array_keys(self::calls());
        $missing = array_diff(array_keys(self::index()['operations']), $known);

        $this->assertSame([], array_values($missing));
        $this->assertSame([], array_values(array_diff(array_keys(self::PENDING), array_keys(self::calls()))));
    }

    #[Test]
    public function it_keeps_pending_operations_failing_so_the_list_only_shrinks(): void
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

    /**
     * The op table is also the request-assertion harness (A8): every method sends the documented
     * verb and path, and forwards its RequestOptions (an extra header on every call; the
     * Idempotency-Key on every JSON mutation).
     */
    #[Test]
    public function it_sends_each_operation_to_its_route_and_forwards_request_options(): void
    {
        foreach (self::calls() as $operation => $call) {
            [$method, $template] = explode(' ', $operation, 2);
            $history = [];
            $options = new RequestOptions(idempotencyKey: 'guard-key', headers: ['X-Guard' => 'forwarded']);

            $this->decode($operation, self::variants($operation)['full'] ?? [], $options, $history);

            $this->assertCount(1, $history, $operation);
            $request = $history[0]['request'];
            $pattern = '#^'.preg_replace('#\\\{[^/]+?\\\}#', '[^/]+', preg_quote($template, '#')).'$#';
            $this->assertSame($method, $request->getMethod(), $operation);
            $this->assertMatchesRegularExpression($pattern, $request->getUri()->getPath(), $operation);
            $this->assertSame('forwarded', $request->getHeaderLine('X-Guard'), "{$operation} drops its RequestOptions");
            $multipart = str_starts_with($request->getHeaderLine('Content-Type'), 'multipart/');
            $expectKey = $method !== 'GET' && ! $multipart ? 'guard-key' : '';
            $this->assertSame($expectKey, $request->getHeaderLine('Idempotency-Key'), $operation);
        }
    }

    /**
     * Decode $body through the operation's SDK method. Returns null on success, else the failure.
     *
     * @param  list<array{request: \Psr\Http\Message\RequestInterface}>  $history
     */
    private function decode(string $operation, mixed $body, ?RequestOptions $options = null, array &$history = []): ?string
    {
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR))]));
        $stack->push(Middleware::history($history));
        $blindpay = new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000', maxRetries: 0, handler: $stack);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $response = self::calls()[$operation]($blindpay, $options);
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
