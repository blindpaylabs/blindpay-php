<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

/**
 * The error side of a BlindPayApiResponse.
 *
 * API error bodies always carry `message`; `code` (UPPERCASE, the stable key to branch on),
 * `description`, `errors` and `trace_id` are present only on some of them, so they are nullable
 * here and `errors` defaults to an empty list.
 *
 * The message never contains the request body or the API key.
 */
readonly class ErrorResponse
{
    /**
     * @param  string  $message  the API `message` (e.g. `not_enough_balance`), or an SDK description
     * @param  int|null  $status  HTTP status; null when no response was received
     * @param  string|null  $code  API error code, e.g. `PAYOUTS_INSUFFICIENT_BALANCE`
     * @param  list<mixed>  $errors  the raw `errors` array of the body
     * @param  string|null  $requestId  the `X-Request-Id` response header
     * @param  string|null  $idempotencyKey  the `Idempotency-Key` the request carried (yours or the
     *                                       generated one): resend with it to resume safely
     * @param  bool  $retryable  whether resending the same request (with the same idempotency key)
     *                           may succeed. A mutation that got a 5xx is retryable but is never
     *                           auto-retried: look the resource up first, then resend with this key.
     * @param  array<string, string>  $headers  response headers (lower-cased names), if a response arrived
     */
    public function __construct(
        public string $message,
        public ?int $status = null,
        public ?string $code = null,
        public ?string $description = null,
        public array $errors = [],
        public ?string $traceId = null,
        public ?string $requestId = null,
        public ?string $idempotencyKey = null,
        public bool $retryable = false,
        public ErrorType $type = ErrorType::API,
        public array $headers = [],
    ) {}

    /** Build from an API error body (the `{success:false, code, message, ...}` envelope). */
    public static function fromArray(array $data): self
    {
        return new self(
            message: is_string($data['message'] ?? null) ? $data['message'] : 'Unknown error',
            code: is_string($data['code'] ?? null) ? $data['code'] : null,
            description: is_string($data['description'] ?? null) ? $data['description'] : null,
            errors: is_array($data['errors'] ?? null) ? array_values($data['errors']) : [],
            traceId: is_string($data['trace_id'] ?? null) ? $data['trace_id'] : null,
        );
    }

    /** A call rejected by the SDK before anything was sent. */
    public static function validation(string $message): self
    {
        return new self(message: $message, type: ErrorType::VALIDATION);
    }

    public function isNetworkError(): bool
    {
        return $this->type === ErrorType::NETWORK;
    }

    public function isApiError(): bool
    {
        return $this->type === ErrorType::API;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'status' => $this->status,
            'code' => $this->code,
            'description' => $this->description,
            'errors' => $this->errors,
            'trace_id' => $this->traceId,
            'request_id' => $this->requestId,
            'idempotency_key' => $this->idempotencyKey,
            'retryable' => $this->retryable,
            'type' => $this->type->value,
        ];
    }
}
