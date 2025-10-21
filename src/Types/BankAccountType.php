<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum BankAccountType: string
{
    case CHECKING = 'checking';
    case SAVINGS = 'savings';
}
