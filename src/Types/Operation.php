<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum Operation: string
{
    case CREATE = 'create';
    case DELETE = 'delete';
    case UPDATE = 'update';
}
