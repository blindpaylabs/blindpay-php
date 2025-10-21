<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum AccountClass: string
{
    case INDIVIDUAL = 'individual';
    case BUSINESS = 'business';
}
