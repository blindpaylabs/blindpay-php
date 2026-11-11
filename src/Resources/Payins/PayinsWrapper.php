<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Payins;

use BlindPay\SDK\Internal\ApiClientInterface;

/**
 * `$blindpay->payins`: every Payins method, plus the payin quotes sub-resource.
 */
class PayinsWrapper extends Payins
{
    public function __construct(
        string $instanceId,
        ApiClientInterface $client,
        public readonly Quotes $quotes,
    ) {
        parent::__construct($instanceId, $client);
    }
}
