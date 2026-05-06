<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum ActorType: string
{
    case API_KEY = 'api_key';
    case USER = 'user';
}
