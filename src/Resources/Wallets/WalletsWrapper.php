<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Wallets;

readonly class WalletsWrapper
{
    public readonly BlockchainWallets $blockchain;

    public readonly OfframpWallets $offramp;

    public function __construct(
        BlockchainWallets $blockchainResource,
        OfframpWallets $offrampResource
    ) {
        $this->blockchain = $blockchainResource;
        $this->offramp = $offrampResource;
    }
}
