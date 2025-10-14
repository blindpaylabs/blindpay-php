<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

readonly class ErrorResponse
{
    public function __construct(
        public string $message
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            message: $data['message'] ?? 'Unknown error'
        );
    }
}

