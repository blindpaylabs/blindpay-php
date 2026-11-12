<?php

declare(strict_types=1);

namespace BlindPay\SDK\Internal;

use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\ErrorType;
use BlindPay\SDK\Types\RequestOptions;
use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use WeakMap;

/**
 * Sends API requests: headers, idempotency keys, the per-attempt timeout, retries (RetryPolicy)
 * and response/error decoding. Immutable once built; per-request options never touch its state.
 *
 * @internal
 */
final class Transport implements ApiClientInterface
{
    private const MAX_IDEMPOTENCY_KEY_LENGTH = 255;

    /** Headers only the SDK sets; RequestOptions::$headers cannot add or override them. */
    private const RESERVED_HEADERS = ['authorization', 'idempotency-key', 'content-type'];

    /** cURL errnos raised before any byte of the request was sent (DNS, connect). */
    private const NOTHING_SENT_ERRNOS = [6, 7];

    /** CURLE_URL_MALFORMAT: the request could not be built. */
    private const MALFORMED_URL_ERRNO = 3;

    private readonly Client $client;

    /** @var array<string, string> */
    private readonly array $headers;

    /**
     * Each transport's API key, held off the instance so that no var_export(), (array) cast,
     * serialize() or debugger view of a Transport (or of the client that owns it) can show it.
     *
     * @var WeakMap<self, string>|null
     */
    private static ?WeakMap $apiKeys = null;

    /** @var Closure(float): void */
    private readonly Closure $sleep;

    /** @var Closure(): float */
    private readonly Closure $random;

    /**
     * @param  callable|null  $handler  a Guzzle handler or HandlerStack (tests, proxies, middleware)
     * @param  callable(float): void|null  $sleep  waits the given seconds between retries
     */
    public function __construct(
        #[\SensitiveParameter]
        string $apiKey,
        string $baseUrl,
        string $userAgent,
        private readonly float $timeout,
        private readonly int $maxRetries,
        ?callable $handler = null,
        ?callable $sleep = null,
    ) {
        $this->headers = [
            'Accept' => 'application/json',
            'User-Agent' => $userAgent,
        ];
        self::$apiKeys ??= new WeakMap;
        self::$apiKeys[$this] = $apiKey;
        $this->client = new Client([
            'base_uri' => rtrim($baseUrl, '/').'/v1/',
            'handler' => $handler instanceof HandlerStack ? $handler : HandlerStack::create($handler),
            'http_errors' => false,
            'allow_redirects' => false,
        ]);
        $this->sleep = $sleep !== null
            ? Closure::fromCallable($sleep)
            : static function (float $seconds): void {
                usleep((int) round($seconds * 1_000_000));
            };
        $this->random = static fn (): float => mt_rand() / mt_getrandmax();
    }

    /** @return BlindPayApiResponse<mixed> */
    public function get(string $path, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->send('GET', $path, null, $options);
    }

    /** @return BlindPayApiResponse<mixed> */
    public function post(string $path, array $body, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->send('POST', $path, $body, $options);
    }

    /** @return BlindPayApiResponse<mixed> */
    public function put(string $path, array $body, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->send('PUT', $path, $body, $options);
    }

    /** @return BlindPayApiResponse<mixed> */
    public function patch(string $path, array $body, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->send('PATCH', $path, $body, $options);
    }

    /** @return BlindPayApiResponse<mixed> */
    public function delete(string $path, ?array $body = null, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->send('DELETE', $path, $body, $options);
    }

    /** @return BlindPayApiResponse<mixed> */
    public function postMultipart(string $path, array $multipart, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->send('POST', $path, null, $options, $multipart);
    }

    /**
     * @param  array<string, mixed>|null  $body  JSON body; serialised once and resent byte for byte
     * @param  list<array<string, mixed>>|null  $multipart  Guzzle multipart parts (never idempotent)
     * @return BlindPayApiResponse<mixed>
     */
    private function send(string $method, string $path, ?array $body, ?RequestOptions $options, ?array $multipart = null): BlindPayApiResponse
    {
        $options ??= new RequestOptions;
        $timeout = $options->timeout ?? $this->timeout;
        $headers = $this->headers;
        foreach (array_keys($options->headers) as $name) {
            if (strcasecmp((string) $name, 'Idempotency-Key') === 0) {
                return BlindPayApiResponse::error(ErrorResponse::validation('Pass the idempotency key as RequestOptions::$idempotencyKey, not as a header.'));
            }
        }
        foreach ($options->headers as $name => $value) {
            if (! $this->isReservedOrPresentHeader($headers, (string) $name)) {
                $headers[(string) $name] = $value;
            }
        }
        $request = ['timeout' => $timeout, 'connect_timeout' => $timeout];

        if ($body !== null) {
            try {
                $request['body'] = $body === [] ? '{}' : json_encode($body, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                return BlindPayApiResponse::error(ErrorResponse::validation('The request body cannot be encoded as JSON: '.$e->getMessage()));
            }
            $headers['Content-Type'] = 'application/json';
        }
        if ($multipart !== null) {
            $request['multipart'] = $multipart;
        }

        $key = null;
        if (RetryPolicy::isMutation($method) && $multipart === null) {
            $key = $options->idempotencyKey;
            if ($key !== null && ($key === '' || $key !== trim($key) || strlen($key) > self::MAX_IDEMPOTENCY_KEY_LENGTH)) {
                return BlindPayApiResponse::error(ErrorResponse::validation('The idempotency key must be 1-255 characters long, with no leading or trailing whitespace.'));
            }
            if ($key === null && $this->maxRetries > 0) {
                $key = Uuid::v4();
            }
            if ($key !== null) {
                $headers['Idempotency-Key'] = $key;
            }
        }
        $request['headers'] = $headers;
        // The key rides on the PSR-7 request object only, never in the plain options array, so no
        // frame argument a handler's backtrace can see holds it as a string.
        $psrRequest = (new Request($method, $path))->withHeader('Authorization', 'Bearer '.self::$apiKeys[$this]);

        for ($attempt = 0; ; $attempt++) {
            $bodyUnreadable = false;

            try {
                $response = $this->client->send($psrRequest, $request);
            } catch (TransferException $e) {
                if (($this->handlerContext($e)['errno'] ?? null) === self::MALFORMED_URL_ERRNO) {
                    return $this->buildError($e, $key);
                }
                // A status line may have arrived before the transfer failed (e.g. a timeout while
                // reading the body). Then the server has handled the request, so it is treated as
                // that response, never as "no response" (a mutation 5xx must not be resent).
                $response = $e instanceof RequestException ? $e->getResponse() : null;
                $seenStatus = $response?->getStatusCode() ?? (int) ($this->handlerContext($e)['http_code'] ?? 0);
                if ($response === null && $seenStatus > 0) {
                    $response = new Response($seenStatus);
                    $bodyUnreadable = true;
                }
                if ($response === null) {
                    $nothingSent = $e instanceof ConnectException
                        && in_array($this->handlerContext($e)['errno'] ?? null, self::NOTHING_SENT_ERRNOS, true);
                    if ($attempt < $this->maxRetries && RetryPolicy::shouldRetryNetworkError($method, $key !== null, $multipart !== null, $nothingSent)) {
                        ($this->sleep)(RetryPolicy::delay($attempt, null, $this->random));

                        continue;
                    }

                    return BlindPayApiResponse::error(new ErrorResponse(
                        message: 'Request failed before a response was received: '.self::withoutQueryStrings($e->getMessage()),
                        idempotencyKey: $key,
                        retryable: $multipart === null && (! RetryPolicy::isMutation($method) || $key !== null),
                        type: ErrorType::NETWORK,
                    ));
                }
            } catch (Throwable $e) {
                return $this->buildError($e, $key);
            }

            $status = $response->getStatusCode();

            try {
                $raw = (string) $response->getBody();
            } catch (Throwable) {
                $raw = '';
                $bodyUnreadable = true;
            }
            if ($bodyUnreadable && $status >= 200 && $status < 300) {
                return BlindPayApiResponse::error(new ErrorResponse(
                    message: "The API answered HTTP {$status} but its body could not be read.",
                    status: $status,
                    idempotencyKey: $key,
                    type: ErrorType::DECODE,
                ));
            }
            $requestId = $response->getHeaderLine('X-Request-Id') !== '' ? $response->getHeaderLine('X-Request-Id') : null;

            if ($status >= 200 && $status < 300) {
                return $this->success($raw, $status, $requestId, $response, $key);
            }

            $error = $this->apiError($method, $status, $raw, $response, $requestId, $key);
            if ($attempt < $this->maxRetries && RetryPolicy::shouldRetryResponse($method, $key !== null, $multipart !== null, $status, $error->message, $error->code)) {
                $retryAfter = $response->getHeaderLine('Retry-After');
                ($this->sleep)(RetryPolicy::delay($attempt, $retryAfter !== '' ? $retryAfter : null, $this->random));

                continue;
            }

            return BlindPayApiResponse::error($error);
        }
    }

    /** @return BlindPayApiResponse<mixed> */
    private function success(string $raw, int $status, ?string $requestId, ResponseInterface $response, ?string $key): BlindPayApiResponse
    {
        $replayed = strtolower($response->getHeaderLine('Idempotency-Replayed')) === 'true';
        if (trim($raw) === '') {
            return BlindPayApiResponse::success(null, $requestId, $replayed, $status, $key, $this->flattenHeaders($response));
        }

        try {
            return BlindPayApiResponse::success(json_decode($raw, true, 512, JSON_THROW_ON_ERROR), $requestId, $replayed, $status, $key, $this->flattenHeaders($response));
        } catch (JsonException $e) {
            return BlindPayApiResponse::error(new ErrorResponse(
                message: "The API returned HTTP {$status} with a body that is not valid JSON: {$e->getMessage()}",
                status: $status,
                requestId: $requestId,
                idempotencyKey: $key,
                type: ErrorType::DECODE,
                headers: $this->flattenHeaders($response),
            ));
        }
    }

    private function apiError(string $method, int $status, string $raw, ResponseInterface $response, ?string $requestId, ?string $key): ErrorResponse
    {
        try {
            $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $body = null;
        }
        $parsed = is_array($body) ? ErrorResponse::fromArray($body) : null;
        $message = $parsed !== null && is_string($body['message'] ?? null)
            ? $parsed->message
            : trim("HTTP {$status} {$response->getReasonPhrase()}");

        return new ErrorResponse(
            message: $message,
            status: $status,
            code: $parsed?->code,
            description: $parsed?->description,
            errors: $parsed->errors ?? [],
            traceId: $parsed?->traceId,
            requestId: $requestId,
            idempotencyKey: $key,
            retryable: RetryPolicy::isRetryableStatus($method, $key !== null, $status, $message, $parsed?->code),
            type: ErrorType::API,
            headers: $this->flattenHeaders($response),
        );
    }

    /** @return array<string, string> */
    private function flattenHeaders(ResponseInterface $response): array
    {
        $headers = [];
        foreach (array_keys($response->getHeaders()) as $name) {
            $headers[strtolower((string) $name)] = $response->getHeaderLine((string) $name);
        }

        return $headers;
    }

    /** @param array<string, string> $headers */
    private function isReservedOrPresentHeader(array $headers, string $name): bool
    {
        foreach (array_keys($headers) as $existing) {
            if (strcasecmp($existing, $name) === 0) {
                return true;
            }
        }

        return in_array(strtolower($name), self::RESERVED_HEADERS, true);
    }

    /**
     * The request could not be built or sent at all (e.g. an invalid URL): retrying cannot help.
     *
     * @return BlindPayApiResponse<never>
     */
    private function buildError(Throwable $e, ?string $key): BlindPayApiResponse
    {
        return BlindPayApiResponse::error(new ErrorResponse(
            message: 'The request could not be sent: '.self::withoutQueryStrings($e->getMessage()),
            idempotencyKey: $key,
            type: ErrorType::VALIDATION,
        ));
    }

    /**
     * Guzzle appends the request URL to transport errors ("... for https://.../customers?..."). Query
     * strings can carry personal data (names, emails, IDs), so error messages keep only the
     * scheme, host and path.
     */
    private static function withoutQueryStrings(string $message): string
    {
        return (string) preg_replace('~(\bhttps?://[^\s?#]*)[?#]\S*~i', '$1', $message);
    }

    /** @return array<string, mixed> */
    private function handlerContext(TransferException $e): array
    {
        return $e instanceof RequestException || $e instanceof ConnectException ? $e->getHandlerContext() : [];
    }

    /** Never expose the API key in var_dump() / print_r(). */
    public function __debugInfo(): array
    {
        return ['timeout' => $this->timeout, 'maxRetries' => $this->maxRetries, 'apiKey' => '[redacted]'];
    }
}
