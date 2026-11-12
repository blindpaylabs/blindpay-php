<?php

declare(strict_types=1);

namespace BlindPay\SDK\Webhooks;

use BlindPay\SDK\BlindPayException;

/** Thrown by WebhookVerifier::constructEvent() when a delivery fails verification or decoding. */
final class WebhookException extends BlindPayException {}
