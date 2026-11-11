<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Instances;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Resources\Rfi\InstanceRfis;
use BlindPay\SDK\Resources\TermsOfService\TermsOfService;
use BlindPay\SDK\Resources\Webhooks\Webhooks;

/**
 * `$blindpay->instances`: every Instances method, plus the webhook endpoints, terms of service,
 * onboarding and RFI sub-resources.
 */
class InstancesWrapper extends Instances
{
    public function __construct(
        string $instanceId,
        ApiClientInterface $client,
        public readonly Webhooks $webhookEndpoints,
        public readonly TermsOfService $tos,
        public readonly Onboarding $onboarding,
        public readonly InstanceRfis $rfi,
    ) {
        parent::__construct($instanceId, $client);
    }
}
