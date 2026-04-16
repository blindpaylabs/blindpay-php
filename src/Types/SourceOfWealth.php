<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum SourceOfWealth: string
{
    case BUSINESS_DIVIDENDS_OR_PROFITS = 'business_dividends_or_profits';
    case INVESTMENTS = 'investments';
    case ASSET_SALES = 'asset_sales';
    case CLIENT_INVESTOR_CONTRIBUTIONS = 'client_investor_contributions';
    case GAMBLING = 'gambling';
    case CHARITABLE_CONTRIBUTIONS = 'charitable_contributions';
    case INHERITANCE = 'inheritance';
    case AFFILIATE_OR_ROYALTY_INCOME = 'affiliate_or_royalty_income';
}
