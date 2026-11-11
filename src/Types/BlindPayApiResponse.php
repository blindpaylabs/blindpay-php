<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

use BlindPay\SDK\BlindPayApiException;
use ErrorException;
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
     */
    public function __construct(
        public mixed $data,
        public ?ErrorResponse $error,
        public ?string $requestId = null,
        public bool $idempotencyReplayed = false,
    ) {}

    /**
     * @template TSuccess
     *
     * @param  TSuccess  $data
     * @return self<TSuccess>
     */
    public static function success(mixed $data, ?string $requestId = null, bool $idempotencyReplayed = false): self
    {
        return new self(
            data: $data,
            error: null,
            requestId: $requestId,
            idempotencyReplayed: $idempotencyReplayed,
        );
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

        set_error_handler(static function (int $severity, string $message): bool {
            throw new ErrorException($message, 0, $severity);
        }, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);

        try {
            $data = $decode($this->data);
        } catch (Throwable $e) {
            return self::error(new ErrorResponse(
                message: 'Failed to decode the API response: '.$e->getMessage(),
                requestId: $this->requestId,
                type: ErrorType::DECODE,
            ));
        } finally {
            restore_error_handler();
        }

        return new self($data, null, $this->requestId, $this->idempotencyReplayed);
    }

    public function isSuccess(): bool
    {
        return $this->error === null;
    }

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
