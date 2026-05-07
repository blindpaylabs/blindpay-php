<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum Currency: string
{
    case USDC = 'USDC';
    case USDT = 'USDT';
    case USDB = 'USDB';
    case BRL = 'BRL';
    case USD = 'USD';
    case MXN = 'MXN';
    case COP = 'COP';
    case ARS = 'ARS';
}
