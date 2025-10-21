<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

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
