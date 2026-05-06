<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum ReceiverStatus: string
{
    case VERIFYING = 'verifying';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case DEPRECATED = 'deprecated';
    case PENDING_REVIEW = 'pending_review';
    case AWAITING_CONTRACT = 'awaiting_contract';
    case COMPLIANCE_REQUEST = 'compliance_request';
}
