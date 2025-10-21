<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum CurrencyType: string
{
    case SENDER = 'sender';
    case RECEIVER = 'receiver';
}

