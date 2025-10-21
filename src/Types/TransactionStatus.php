<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum TransactionStatus: string
{
    case REFUNDED = 'refunded';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case ON_HOLD = 'on_hold';
}

