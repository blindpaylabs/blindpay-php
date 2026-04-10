<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum RecipientRelationship: string
{
    case FIRST_PARTY = 'first_party';
    case EMPLOYEE = 'employee';
    case INDEPENDENT_CONTRACTOR = 'independent_contractor';
    case VENDOR_OR_SUPPLIER = 'vendor_or_supplier';
    case SUBSIDIARY_OR_AFFILIATE = 'subsidiary_or_affiliate';
    case MERCHANT_OR_PARTNER = 'merchant_or_partner';
    case CUSTOMER = 'customer';
    case LANDLORD = 'landlord';
    case FAMILY = 'family';
    case OTHER = 'other';
}
