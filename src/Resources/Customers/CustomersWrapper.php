<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Customers;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Resources\BankAccounts\BankAccounts;

/**
 * `$blindpay->customers`: every Customers method, plus the bank accounts sub-resource.
 */
class CustomersWrapper extends Customers
{
    public function __construct(
        string $instanceId,
        ApiClientInterface $client,
        public readonly BankAccounts $bankAccounts,
    ) {
        parent::__construct($instanceId, $client);
    }
}
