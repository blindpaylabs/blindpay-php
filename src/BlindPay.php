<?php

declare(strict_types=1);

namespace BlindPay\SDK;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Transport;
use BlindPay\SDK\Resources\Available\Available;
use BlindPay\SDK\Resources\BankAccounts\BankAccounts;
use BlindPay\SDK\Resources\CustodialWallets\CustodialWallets;
use BlindPay\SDK\Resources\Customers\CustomersWrapper;
use BlindPay\SDK\Resources\Fees\Fees;
use BlindPay\SDK\Resources\Instances\InstancesWrapper;
use BlindPay\SDK\Resources\Instances\Onboarding;
use BlindPay\SDK\Resources\Ownership\Ownership;
use BlindPay\SDK\Resources\PartnerFees\PartnerFees;
use BlindPay\SDK\Resources\Payables\Payables;
use BlindPay\SDK\Resources\Payins\PayinsWrapper;
use BlindPay\SDK\Resources\Payins\Quotes as PayinQuotes;
use BlindPay\SDK\Resources\Payouts\Payouts;
use BlindPay\SDK\Resources\Quotes\Quotes;
use BlindPay\SDK\Resources\Rfi\CustomerRfis;
use BlindPay\SDK\Resources\Rfi\InstanceRfis;
use BlindPay\SDK\Resources\TermsOfService\TermsOfService;
use BlindPay\SDK\Resources\Transfers\Transfers;
use BlindPay\SDK\Resources\Upload\Upload;
use BlindPay\SDK\Resources\VirtualAccounts\VirtualAccounts;
use BlindPay\SDK\Resources\Wallets\BlockchainWallets;
use BlindPay\SDK\Resources\Wallets\OfframpWallets;
use BlindPay\SDK\Resources\Wallets\WalletsWrapper;
use BlindPay\SDK\Resources\Webhooks\Webhooks;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\RequestOptions;
use BlindPay\SDK\Webhooks\WebhookVerifier;

/**
 * The BlindPay API client.
 *
 *     $blindpay = new BlindPay(apiKey: getenv('BLINDPAY_API_KEY'), instanceId: 'in_...');
 *
 * A configured client is immutable and safe to share; per-request options (RequestOptions)
 * never change it. var_dump()/print_r() of the client never show the API key.
 */
class BlindPay implements ApiClientInterface
{
    public const DEFAULT_BASE_URL = 'https://api.blindpay.com';

    /** Seconds allowed per attempt (connect + send + receive). */
    public const DEFAULT_TIMEOUT = 60.0;

    /** Retries after the first attempt (so 3 attempts in total); 0 disables retries. */
    public const DEFAULT_MAX_RETRIES = 2;

    private const VERSION = '4.1.0';

    private readonly Transport $transport;

    public readonly Available $available;

    public readonly PartnerFees $partnerFees;

    public readonly Quotes $quotes;

    public readonly Payouts $payouts;

    public readonly VirtualAccounts $virtualAccounts;

    public readonly InstancesWrapper $instances;

    public readonly Ownership $ownership;

    public readonly PayinsWrapper $payins;

    public readonly CustomersWrapper $customers;

    public readonly WalletsWrapper $wallets;

    public readonly Transfers $transfers;

    public readonly Fees $fees;

    public readonly Upload $upload;

    public readonly Payables $payables;

    /**
     * @param  string  $apiKey  your API key (sent as `Authorization: Bearer ...`)
     * @param  string  $instanceId  your instance ID (`in_...`)
     * @param  string  $baseUrl  API origin without the `/v1` prefix. Override it only for tests or a
     *                           proxy you trust: every request, with your API key, goes there.
     * @param  float  $timeout  seconds allowed per attempt (default 60)
     * @param  int  $maxRetries  retries after the first attempt (default 2; 0 disables). GETs are
     *                           retried on network errors, 408, 429, 500, 502, 503 and 504; POST/PUT/PATCH/DELETE
     *                           only on network errors, 429 and 409 idempotency_key_in_flight, always
     *                           with the same Idempotency-Key (generated per call when you pass none).
     * @param  callable|null  $handler  a Guzzle handler or HandlerStack, e.g. a MockHandler in tests or
     *                                  a stack with your own middleware (logging, metrics)
     * @param  callable(float): void|null  $sleep  waits the given seconds between retries (test seam;
     *                                             defaults to usleep)
     *
     * @throws BlindPayException on an empty API key or instance ID, or an invalid option
     */
    public function __construct(
        #[\SensitiveParameter]
        string $apiKey,
        private readonly string $instanceId,
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = self::DEFAULT_TIMEOUT,
        int $maxRetries = self::DEFAULT_MAX_RETRIES,
        ?callable $handler = null,
        ?callable $sleep = null,
    ) {
        if ($apiKey === '') {
            throw new BlindPayException('API key not provided, get your API key on BlindPay dashboard');
        }

        if ($this->instanceId === '') {
            throw new BlindPayException('Instance ID not provided, get your instance ID on BlindPay dashboard');
        }

        if ($timeout <= 0) {
            throw BlindPayException::configuration('timeout must be greater than 0 seconds');
        }

        if ($maxRetries < 0) {
            throw BlindPayException::configuration('maxRetries must be 0 or greater');
        }

        if (! preg_match('~^https?://[^/?#]+~i', $baseUrl)) {
            throw BlindPayException::configuration('baseUrl must be an absolute http(s) URL');
        }

        $this->transport = new Transport($apiKey, $baseUrl, 'blindpay-php/'.self::VERSION, $timeout, $maxRetries, $handler, $sleep);

        $this->available = new Available($this);
        $this->partnerFees = new PartnerFees($this->instanceId, $this);
        $this->quotes = new Quotes($this->instanceId, $this);
        $this->payouts = new Payouts($this->instanceId, $this);
        $this->virtualAccounts = new VirtualAccounts($this->instanceId, $this);
        $this->transfers = new Transfers($this->instanceId, $this);
        $this->fees = new Fees($this->instanceId, $this);
        $this->ownership = new Ownership($this->instanceId, $this);
        $this->upload = new Upload($this->instanceId, $this);
        $this->payables = new Payables($this->instanceId, $this);

        $this->instances = new InstancesWrapper(
            $this->instanceId,
            $this,
            new Webhooks($this->instanceId, $this),
            new TermsOfService($this->instanceId, $this),
            new Onboarding($this->instanceId, $this),
            new InstanceRfis($this->instanceId, $this),
        );
        $this->payins = new PayinsWrapper($this->instanceId, $this, new PayinQuotes($this->instanceId, $this));
        $this->customers = new CustomersWrapper(
            $this->instanceId,
            $this,
            new BankAccounts($this->instanceId, $this),
            new CustomerRfis($this->instanceId, $this),
        );
        $blockchainResource = new BlockchainWallets($this->instanceId, $this);
        $offrampResource = new OfframpWallets($this->instanceId, $this);
        $custodialResource = new CustodialWallets($this->instanceId, $this);

        $this->wallets = new WalletsWrapper($blockchainResource, $offrampResource, $custodialResource);
    }

    /** @return BlindPayApiResponse<mixed> */
    public function get(string $path, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->transport->get($path, $options);
    }

    /** @return BlindPayApiResponse<mixed> */
    public function post(string $path, array $body, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->transport->post($path, $body, $options);
    }

    /** @return BlindPayApiResponse<mixed> */
    public function put(string $path, array $body, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->transport->put($path, $body, $options);
    }

    /** @return BlindPayApiResponse<mixed> */
    public function patch(string $path, array $body, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->transport->patch($path, $body, $options);
    }

    /** @return BlindPayApiResponse<mixed> */
    public function delete(string $path, ?array $body = null, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->transport->delete($path, $body, $options);
    }

    /** @return BlindPayApiResponse<mixed> */
    public function postMultipart(string $path, array $multipart, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->transport->postMultipart($path, $multipart, $options);
    }

    /**
     * Verifies the BlindPay webhook signature.
     *
     * Accepts the `svix-signature` header as sent (`v1,<sig>`, possibly several space-separated
     * entries) and rejects timestamps more than $tolerance seconds (default 5 minutes) from the
     * current time. For backwards
     * compatibility a bare signature without the `v1,` prefix is still accepted here (earlier
     * versions compared the whole header to a bare signature, so callers had to strip it).
     *
     * @deprecated Use \BlindPay\SDK\Webhooks\WebhookVerifier::verify(), which needs no API client
     *             and takes the request headers directly.
     *
     * @param  string  $secret  The webhook secret from BlindPay dashboard
     * @param  string  $id  The value of the `svix-id` header
     * @param  string  $timestamp  The value of the `svix-timestamp` header
     * @param  string  $payload  The raw request body
     * @param  string  $svixSignature  The value of the `svix-signature` header
     * @param  int  $tolerance  Maximum allowed |now - timestamp|, in seconds
     * @return bool True if the signature is valid, false otherwise
     */
    public function verifyWebhookSignature(
        #[\SensitiveParameter]
        string $secret,
        string $id,
        string $timestamp,
        string $payload,
        string $svixSignature,
        int $tolerance = WebhookVerifier::DEFAULT_TOLERANCE
    ): bool {
        $signatureHeader = str_contains($svixSignature, ',') ? $svixSignature : "v1,{$svixSignature}";

        return WebhookVerifier::verifySignature($payload, $id, $timestamp, $signatureHeader, $secret, $tolerance, time());
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    /** Never expose the API key in var_dump() / print_r(). */
    public function __debugInfo(): array
    {
        return ['instanceId' => $this->instanceId, 'transport' => $this->transport];
    }
}
