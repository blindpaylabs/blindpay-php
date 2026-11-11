<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum BankingPartner: string
{
    case JPMORGAN = 'jpmorgan';
    case CITI = 'citi';
    case HSBC = 'hsbc';
    case CFSB = 'cfsb';
    case PORTAGE = 'portage';
    case BRE_B = 'bre_b';
    case SPEI = 'spei';
    case PORTAGE_G = 'portage_g';
    /** @deprecated No longer returned by the API. */
}
