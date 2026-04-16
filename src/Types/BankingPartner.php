<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum BankingPartner: string
{
    case JPMORGAN = 'jpmorgan';
    case CITI = 'citi';
    case HSBC = 'hsbc';
    case CFSB = 'cfsb';
}
