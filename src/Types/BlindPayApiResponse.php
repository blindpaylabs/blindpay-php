<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

readonly class BlindPayApiResponse
{
    public function __construct(
        public mixed $data,
        public ?ErrorResponse $error
    ) {
    }

    public static function success(mixed $data): self
    {
        return new self(
            data: $data,
            error: null
        );
    }

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
}

