<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum PaymentMethod: string
{
    case ACH = 'ach';
    case WIRE = 'wire';
    case PIX = 'pix';
    case SPEI = 'spei';
    case TRANSFERS = 'transfers';
    case PSE = 'pse';
    case INTERNATIONAL_SWIFT = 'international_swift';
    case RTP = 'rtp';
    case TED = 'ted';
}
