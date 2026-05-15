<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum ReceiverType: string
{
    case BUSINESS = 'business';
    case INDIVIDUAL = 'individual';
}
