<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum StablecoinToken: string
{
    case USDC = 'USDC';
    case USDT = 'USDT';
    case USDB = 'USDB';
}
