<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

readonly class ErrorResponse
{
    public function __construct(
        public string $message
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            message: $data['message'] ?? 'Unknown error'
        );
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}

/*
 * @template T
 */
readonly class BlindPayApiResponse
{
    /*
     * @param T|null $data
     * @param ErrorResponse|null $error
     */
    public function __construct(
        public mixed $data,
        public ?ErrorResponse $error
    ) {}

    /*
     * @template TSuccess
     * @param TSuccess $data
     * @return self<TSuccess>
     */
    public static function success(mixed $data): self
    {
        return new self(
            data: $data,
            error: null
        );
    }

    /*
     * @return self<null>
     */
    public static function error(ErrorResponse $error): self
    {
        return new self(
            data: null,
            error: $error
        );
    }

    public function isSuccess(): bool
    {
        return $this->error === null;
    }

    public function isError(): bool
    {
        return $this->error !== null;
    }

    /*
     * Get data or throw exception if error
     *
     * @return T
     * @throws \RuntimeException
     */
    public function getDataOrFail(): mixed
    {
        if ($this->isError()) {
            throw new \RuntimeException($this->error->message);
        }

        return $this->data;
    }
}
