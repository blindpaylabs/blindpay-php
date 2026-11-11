<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Customers;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Resources\BankAccounts\BankAccounts;
use BlindPay\SDK\Resources\Rfi\CustomerRfis;

/**
 * `$blindpay->customers`: every Customers method, plus the bank accounts and RFI sub-resources.
 */
class CustomersWrapper extends Customers
{
    public function __construct(
        string $instanceId,
        ApiClientInterface $client,
        public readonly BankAccounts $bankAccounts,
        public readonly CustomerRfis $rfi,
    ) {
        parent::__construct($instanceId, $client);
    }
}
