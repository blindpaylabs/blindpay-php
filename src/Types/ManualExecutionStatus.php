<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum ManualExecutionStatus: string
{
    case PENDING = 'pending';
    case CONCLUDED = 'concluded';
    case FAILED = 'failed';
}
