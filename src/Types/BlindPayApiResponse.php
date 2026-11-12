<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

use BlindPay\SDK\BlindPayApiException;
use BlindPay\SDK\Internal\ErrorGuard;
use Throwable;

/**
 * The result of every resource method: either `data` (on success) or `error`. Resource methods
 * never throw for API, network or decoding failures; check isError().
 *
 * @template-covariant T
 */
readonly class BlindPayApiResponse
{
    /**
     * @param  T|null  $data
     * @param  string|null  $requestId  the `X-Request-Id` response header
     * @param  bool  $idempotencyReplayed  true when the API replayed a stored response for the idempotency key
     * @param  int|null  $status  HTTP status of the response, when one was received
     * @param  string|null  $idempotencyKey  the `Idempotency-Key` the request carried
     * @param  array<string, string>  $headers  response headers (lower-cased names)
     */
    public function __construct(
        public mixed $data,
        public ?ErrorResponse $error,
        public ?string $requestId = null,
        public bool $idempotencyReplayed = false,
        public ?int $status = null,
        public ?string $idempotencyKey = null,
        public array $headers = [],
    ) {}

    /**
     * @template TSuccess
     *
     * @param  TSuccess  $data
     * @param  array<string, string>  $headers
     * @return self<TSuccess>
     */
    public static function success(mixed $data, ?string $requestId = null, bool $idempotencyReplayed = false, ?int $status = null, ?string $idempotencyKey = null, array $headers = []): self
    {
        return new self($data, null, $requestId, $idempotencyReplayed, $status, $idempotencyKey, $headers);
    }

    /**
     * @return self<never>
     */
    public static function error(ErrorResponse $error): self
    {
        return new self(
            data: null,
            error: $error,
            requestId: $error->requestId,
            status: $error->status,
            idempotencyKey: $error->idempotencyKey,
            headers: $error->headers,
        );
    }

    /**
     * Decode the success data with $decode. Any failure while decoding (a TypeError, a ValueError,
     * an undefined-key warning, ...) becomes an ErrorType::DECODE error instead of escaping, so
     * resource methods keep the "check isError()" contract. Errors pass through untouched.
     *
     * @template TOut
     *
     * @param  callable(mixed): TOut  $decode
     * @return self<TOut>
     */
    public function map(callable $decode): self
    {
        if ($this->error !== null) {
            return $this;
        }

        try {
            $data = ErrorGuard::run(fn () => $decode($this->data));
        } catch (Throwable $e) {
            return self::error(new ErrorResponse(
                message: 'Failed to decode the API response: '.$e->getMessage(),
                status: $this->status,
                requestId: $this->requestId,
                idempotencyKey: $this->idempotencyKey,
                type: ErrorType::DECODE,
                headers: $this->headers,
            ));
        }

        return new self($data, null, $this->requestId, $this->idempotencyReplayed, $this->status, $this->idempotencyKey, $this->headers);
    }

    /**
     * True when the call succeeded: `error` is null and `data` holds the decoded value (static
     * analysers narrow both after this check).
     *
     * @phpstan-assert-if-true null $this->error
     * @phpstan-assert-if-true =T $this->data
     *
     * @phpstan-assert-if-false !null $this->error
     *
     * @psalm-assert-if-true null $this->error
     *
     * @psalm-assert-if-false !null $this->error
     */
    public function isSuccess(): bool
    {
        return $this->error === null;
    }

    /**
     * True when the call failed: `error` is set. After `if ($r->isError()) { return; }`, static
     * analysers know `error` is null and `data` holds the decoded value.
     *
     * @phpstan-assert-if-true !null $this->error
     *
     * @phpstan-assert-if-false null $this->error
     * @phpstan-assert-if-false =T $this->data
     *
     * @psalm-assert-if-true !null $this->error
     *
     * @psalm-assert-if-false null $this->error
     */
    public function isError(): bool
    {
        return $this->error !== null;
    }

    /**
     * The data, or throws BlindPayApiException (carrying the ErrorResponse) on error.
     *
     * @return T
     *
     * @throws BlindPayApiException
     */
    public function getDataOrFail(): mixed
    {
        if ($this->error !== null) {
            throw new BlindPayApiException($this->error);
        }

        return $this->data;
    }
}
