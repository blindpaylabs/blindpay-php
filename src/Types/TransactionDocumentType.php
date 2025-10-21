<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum TransactionDocumentType: string
{
    case INVOICE = 'invoice';
    case PURCHASE_ORDER = 'purchase_order';
    case DELIVERY_SLIP = 'delivery_slip';
    case CONTRACT = 'contract';
    case CUSTOMS_DECLARATION = 'customs_declaration';
    case BILL_OF_LADING = 'bill_of_lading';
    case OTHERS = 'others';
}
