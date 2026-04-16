<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum EstimatedAnnualRevenue: string
{
    case RANGE_0_99999 = '0_99999';
    case RANGE_100000_999999 = '100000_999999';
    case RANGE_1000000_9999999 = '1000000_9999999';
    case RANGE_10000000_49999999 = '10000000_49999999';
    case RANGE_50000000_249999999 = '50000000_249999999';
    case RANGE_2500000000_PLUS = '2500000000_plus';
}
