<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum Network: string
{
    case BASE = 'base';
    case SEPOLIA = 'sepolia';
    case ARBITRUM_SEPOLIA = 'arbitrum_sepolia';
    case BASE_SEPOLIA = 'base_sepolia';
    case ARBITRUM = 'arbitrum';
    case POLYGON = 'polygon';
    case POLYGON_AMOY = 'polygon_amoy';
    case ETHEREUM = 'ethereum';
    case STELLAR = 'stellar';
    case STELLAR_TESTNET = 'stellar_testnet';
    case TRON = 'tron';
}

