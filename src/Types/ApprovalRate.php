<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum ApprovalRate: string
{
    case HIGH = 'high';
    case LOW = 'low';
    case MEDIUM = 'medium';
}
