<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum AmlStatus: string
{
    case CLEAR = 'clear';
    case HIT = 'hit';
    case ERROR = 'error';
}
