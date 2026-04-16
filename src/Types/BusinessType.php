<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum BusinessType: string
{
    case CORPORATION = 'corporation';
    case LLC = 'llc';
    case PARTNERSHIP = 'partnership';
    case SOLE_PROPRIETORSHIP = 'sole_proprietorship';
    case TRUST = 'trust';
    case NON_PROFIT = 'non_profit';
}
