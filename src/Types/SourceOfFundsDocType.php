<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum SourceOfFundsDocType: string
{
    case BUSINESS_INCOME = 'business_income';
    case ESOPS = 'esops';
    case GAMBLING_PROCEEDS = 'gambling_proceeds';
    case GIFTS = 'gifts';
    case GOVERNMENT_BENEFITS = 'government_benefits';
    case INHERITANCE = 'inheritance';
    case INVESTMENT_LOANS = 'investment_loans';
    case INVESTMENT_PROCEEDS = 'investment_proceeds';
    case PENSION_RETIREMENT = 'pension_retirement';
    case SALARY = 'salary';
    case SALE_OF_ASSETS_REAL_ESTATE = 'sale_of_assets_real_estate';
    case SAVINGS = 'savings';
    case SOMEONE_ELSE_FUNDS = 'someone_else_funds';
}
