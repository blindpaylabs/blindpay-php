<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

readonly class BankDetailItem
{
    public function __construct(
        public string $label,
        public string $value,
        public ?bool $isActive = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'],
            value: $data['value'],
            isActive: $data['is_active'] ?? null
        );
    }
}

