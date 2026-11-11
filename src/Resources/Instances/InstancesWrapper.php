<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Instances;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Resources\TermsOfService\TermsOfService;
use BlindPay\SDK\Resources\Webhooks\Webhooks;

/**
 * `$blindpay->instances`: every Instances method, plus the webhook endpoints and terms of service
 * sub-resources.
 */
class InstancesWrapper extends Instances
{
    public function __construct(
        string $instanceId,
        ApiClientInterface $client,
        public readonly Webhooks $webhookEndpoints,
        public readonly TermsOfService $tos,
    ) {
        parent::__construct($instanceId, $client);
    }
}
