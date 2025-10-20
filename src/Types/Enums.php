<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum CurrencyType: string
{
    case SENDER = 'sender';
    case RECEIVER = 'receiver';
}

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

enum StablecoinToken: string
{
    case USDC = 'USDC';
    case USDT = 'USDT';
    case USDB = 'USDB';
}

enum TransactionDocumentType: string
{
    case INVOICE = 'invoice';
    case PURCHASE_ORDER = 'purchase_order';
    case DELIVERY_SLIP = 'delivery_slip';
    case CONTRACT = 'contract';
    case CUSTOMS_DECLARATION = 'customs_declaration';
    case BILL_OF_LADING = 'bill_of_lading';
    case OTHERS = 'others';
}

enum BankAccountType: string
{
    case CHECKING = 'checking';
    case SAVINGS = 'savings';
}

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

enum Rail: string
{
    case WIRE = 'wire';
    case ACH = 'ach';
    case PIX = 'pix';
    case SPEI_BITSO = 'spei_bitso';
    case TRANSFERS_BITSO = 'transfers_bitso';
    case ACH_COP_BITSO = 'ach_cop_bitso';
    case INTERNATIONAL_SWIFT = 'international_swift';
    case RTP = 'rtp';
}

enum AccountClass: string
{
    case INDIVIDUAL = 'individual';
    case BUSINESS = 'business';
}

enum TransactionStatus: string
{
    case REFUNDED = 'refunded';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case ON_HOLD = 'on_hold';
}

enum Permission: string
{
    case FULL_ACCESS = 'full_access';
}
