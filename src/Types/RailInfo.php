<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

readonly class RailInfo
{
    public function __construct(
        public string $label,
        public string $value,
        public string $country
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'],
            value: $data['value'],
            country: $data['country']
        );
    }
}
