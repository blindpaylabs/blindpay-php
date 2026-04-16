<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum SoleProprietorDocType: string
{
    case MASTER_SERVICE_AGREEMENT = 'master_service_agreement';
    case SALARY_SLIP = 'salary_slip';
    case BANK_STATEMENT = 'bank_statement';
}
