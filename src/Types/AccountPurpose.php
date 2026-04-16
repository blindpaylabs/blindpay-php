<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum AccountPurpose: string
{
    case CHARITABLE_DONATIONS = 'charitable_donations';
    case ECOMMERCE_RETAIL_PAYMENTS = 'ecommerce_retail_payments';
    case INVESTMENT_PURPOSES = 'investment_purposes';
    case BUSINESS_EXPENSES = 'business_expenses';
    case PAYMENTS_TO_FRIENDS_OR_FAMILY_ABROAD = 'payments_to_friends_or_family_abroad';
    case PERSONAL_OR_LIVING_EXPENSES = 'personal_or_living_expenses';
    case PROTECT_WEALTH = 'protect_wealth';
    case PURCHASE_GOODS_AND_SERVICES = 'purchase_goods_and_services';
    case RECEIVE_PAYMENTS_FOR_GOODS_AND_SERVICES = 'receive_payments_for_goods_and_services';
    case TAX_OPTIMIZATION = 'tax_optimization';
    case THIRD_PARTY_MONEY_TRANSMISSION = 'third_party_money_transmission';
    case PAYROLL = 'payroll';
    case TREASURY_MANAGEMENT = 'treasury_management';
    case OTHER = 'other';
}
