<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum PurposeOfTransactions: string
{
    case BUSINESS_TRANSACTIONS = 'business_transactions';
    case CHARITABLE_DONATIONS = 'charitable_donations';
    case INVESTMENT_PURPOSES = 'investment_purposes';
    case OTHER = 'other';
    case PAYMENTS_TO_FRIENDS_OR_FAMILY_ABROAD = 'payments_to_friends_or_family_abroad';
    case PERSONAL_OR_LIVING_EXPENSES = 'personal_or_living_expenses';
    case PROTECT_WEALTH = 'protect_wealth';
    case PURCHASE_GOOD_AND_SERVICES = 'purchase_good_and_services';
    case RECEIVE_PAYMENT_FOR_FREELANCING = 'receive_payment_for_freelancing';
    case RECEIVE_SALARY = 'receive_salary';
}
