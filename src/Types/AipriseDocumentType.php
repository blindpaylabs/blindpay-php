<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum AipriseDocumentType: string
{
    case ADDRESS_PROOF_DOCUMENT = 'ADDRESS_PROOF_DOCUMENT';
    case BANK_STATEMENT_DOCUMENT = 'BANK_STATEMENT_DOCUMENT';
    case OTHER = 'OTHER';
    case SOURCE_OF_FUNDS_DOCUMENT = 'SOURCE_OF_FUNDS_DOCUMENT';
    case TAX_CERTIFICATE = 'TAX_CERTIFICATE';
    case USER_SELFIE = 'USER_SELFIE';
    case VISA_DOCUMENT = 'VISA_DOCUMENT';
}
