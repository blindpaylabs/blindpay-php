<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

use DateTimeImmutable;

abstract readonly class BaseTracking
{
    public function __construct(
        public string $step,
        public ?DateTimeImmutable $completedAt
    ) {}

    abstract public function toArray(): array;
}
