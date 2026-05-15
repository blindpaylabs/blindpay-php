<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum SwiftPaymentCode: string
{
    case HK_SWIFT_CHARITABLEDONATION = 'hk_swift_charitabledonation';
    case HK_SWIFT_GOODS = 'hk_swift_goods';
    case HK_SWIFT_PERSONAL = 'hk_swift_personal';
    case HK_SWIFT_SERVICES = 'hk_swift_services';
}
