<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum Decision: string
{
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
