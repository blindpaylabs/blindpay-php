<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Wallets;

use BlindPay\SDK\Resources\CustodialWallets\CustodialWallets;

readonly class WalletsWrapper
{
    public readonly BlockchainWallets $blockchain;

    public readonly OfframpWallets $offramp;

    public readonly CustodialWallets $custodial;

    public function __construct(
        BlockchainWallets $blockchainResource,
        OfframpWallets $offrampResource,
        CustodialWallets $custodialResource
    ) {
        $this->blockchain = $blockchainResource;
        $this->offramp = $offrampResource;
        $this->custodial = $custodialResource;
    }
}
