<?php

declare(strict_types=1);

namespace BlindPay\SDK;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\BlindPayException;
use BlindPay\SDK\Resources\ApiKeys\ApiKeys;
use BlindPay\SDK\Resources\Available\Available;
use BlindPay\SDK\Resources\BankAccounts\BankAccounts;
use BlindPay\SDK\Resources\Instances\Instances;
use BlindPay\SDK\Resources\Instances\InstancesWrapper;
use BlindPay\SDK\Resources\PartnerFees\PartnerFees;
use BlindPay\SDK\Resources\Payins\Payins;
use BlindPay\SDK\Resources\Payins\PayinsWrapper;
use BlindPay\SDK\Resources\Payins\Quotes as PayinQuotes;
use BlindPay\SDK\Resources\Payouts\Payouts;
use BlindPay\SDK\Resources\Quotes\Quotes;
use BlindPay\SDK\Resources\Receivers\Receivers;
use BlindPay\SDK\Resources\Receivers\ReceiversWrapper;
use BlindPay\SDK\Resources\VirtualAccounts\VirtualAccounts;
use BlindPay\SDK\Resources\Wallets\BlockchainWallets;
use BlindPay\SDK\Resources\Wallets\OfframpWallets;
use BlindPay\SDK\Resources\Wallets\WalletsWrapper;
use BlindPay\SDK\Resources\Webhooks\Webhooks;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

class BlindPay implements ApiClientInterface
{
    private const BASE_URL = 'https://api.blindpay.com/v1/';

    private const VERSION = '1.0.0';

    private Client $httpClient;

    private array $headers;

    public readonly Available $available;

    public readonly PartnerFees $partnerFees;

    public readonly Quotes $quotes;

    public readonly Payouts $payouts;

    public readonly VirtualAccounts $virtualAccounts;

    public readonly InstancesWrapper $instances;

    public readonly PayinsWrapper $payins;

    public readonly ReceiversWrapper $receivers;

    public readonly WalletsWrapper $wallets;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $instanceId
    ) {
        if (empty($this->apiKey)) {
            throw new BlindPayException('API key not provided, get your API key on BlindPay dashboard');
        }

        if (empty($this->instanceId)) {
            throw new BlindPayException('Instance ID not provided, get your instance ID on BlindPay dashboard');
        }

        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'blindpay-php/'.self::VERSION,
            'Authorization' => 'Bearer '.$this->apiKey,
        ];

        $this->httpClient = new Client([
            'base_uri' => self::BASE_URL,
            'headers' => $this->headers,
            'http_errors' => false,
        ]);

        $this->available = new Available($this);
        $this->partnerFees = new PartnerFees($this->instanceId, $this);
        $this->quotes = new Quotes($this->instanceId, $this);
        $this->payouts = new Payouts($this->instanceId, $this);
        $this->virtualAccounts = new VirtualAccounts($this->instanceId, $this);

        $this->initializeInstances();
        $this->initializePayins();
        $this->initializeReceivers();
        $this->initializeWallets();
    }

    private function initializeInstances(): void
    {
        $instancesResource = new Instances($this->instanceId, $this);
        $apiKeysResource = new ApiKeys($this->instanceId, $this);
        $webhooksResource = new Webhooks($this->instanceId, $this);

        $this->instances = new InstancesWrapper(
            $instancesResource,
            $apiKeysResource,
            $webhooksResource
        );
    }

    private function initializePayins(): void
    {
        $payinsResource = new Payins($this->instanceId, $this);
        $quotesResource = new PayinQuotes($this->instanceId, $this);

        $this->payins = new PayinsWrapper($payinsResource, $quotesResource);
    }

    private function initializeReceivers(): void
    {
        $receiversResource = new Receivers($this->instanceId, $this);
        $bankAccountsResource = new BankAccounts($this->instanceId, $this);

        $this->receivers = new ReceiversWrapper($receiversResource, $bankAccountsResource);
    }

    private function initializeWallets(): void
    {
        $blockchainResource = new BlockchainWallets($this->instanceId, $this);
        $offrampResource = new OfframpWallets($this->instanceId, $this);

        $this->wallets = new WalletsWrapper($blockchainResource, $offrampResource);
    }

    private function request(string $method, string $path, ?array $body = null): BlindPayApiResponse
    {
        try {
            $options = [];
            if ($body !== null) {
                $options['json'] = $body;
            }

            $response = $this->httpClient->request($method, $path, $options);
            $statusCode = $response->getStatusCode();
            $content = $response->getBody()->getContents();
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return BlindPayApiResponse::error(
                    new ErrorResponse('JSON decode error: '.json_last_error_msg().' | Raw response: '.substr($content, 0, 200))
                );
            }

            if ($statusCode >= 400) {
                $errorMessage = $data['message'] ?? 'Unknown error';

                return BlindPayApiResponse::error(
                    new ErrorResponse($errorMessage)
                );
            }

            return BlindPayApiResponse::success($data);
        } catch (GuzzleException $e) {
            return BlindPayApiResponse::error(
                new ErrorResponse($e->getMessage())
            );
        } catch (Throwable $e) {
            return BlindPayApiResponse::error(
                new ErrorResponse($e->getMessage())
            );
        }
    }

    public function get(string $path): BlindPayApiResponse
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $body): BlindPayApiResponse
    {
        return $this->request('POST', $path, $body);
    }

    public function put(string $path, array $body): BlindPayApiResponse
    {
        return $this->request('PUT', $path, $body);
    }

    public function patch(string $path, array $body): BlindPayApiResponse
    {
        return $this->request('PATCH', $path, $body);
    }

    public function delete(string $path, ?array $body = null): BlindPayApiResponse
    {
        return $this->request('DELETE', $path, $body);
    }

    /*
     * Verifies the BlindPay webhook signature
     *
     * @param string $secret The webhook secret from BlindPay dashboard
     * @param string $id The value of the `svix-id` header
     * @param string $timestamp The value of the `svix-timestamp` header
     * @param string $payload The raw request body
     * @param string $svixSignature The value of the `svix-signature` header
     * @return bool True if the signature is valid, false otherwise
     */
    public function verifyWebhookSignature(
        string $secret,
        string $id,
        string $timestamp,
        string $payload,
        string $svixSignature
    ): bool {
        $signedContent = "{$id}.{$timestamp}.{$payload}";
        $secretParts = explode('_', $secret);
        $secretBytes = base64_decode($secretParts[1]);

        $expectedSignature = base64_encode(
            hash_hmac('sha256', $signedContent, $secretBytes, true)
        );

        return strlen($svixSignature) === strlen($expectedSignature)
            && hash_equals($expectedSignature, $svixSignature);
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }
}
