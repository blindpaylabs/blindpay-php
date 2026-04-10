<?php

declare(strict_types=1);

namespace BlindPay\SDK\Types;

enum WebhookEvent: string
{
    case RECEIVER_NEW = 'receiver.new';
    case RECEIVER_UPDATE = 'receiver.update';
    case BANK_ACCOUNT_NEW = 'bankAccount.new';
    case PAYOUT_NEW = 'payout.new';
    case PAYOUT_UPDATE = 'payout.update';
    case PAYOUT_COMPLETE = 'payout.complete';
    case PAYOUT_PARTNER_FEE = 'payout.partnerFee';
    case BLOCKCHAIN_WALLET_NEW = 'blockchainWallet.new';
    case PAYIN_NEW = 'payin.new';
    case PAYIN_UPDATE = 'payin.update';
    case PAYIN_COMPLETE = 'payin.complete';
    case PAYIN_PARTNER_FEE = 'payin.partnerFee';
    case TOS_ACCEPT = 'tos.accept';
    case LIMIT_INCREASE_NEW = 'limitIncrease.new';
    case LIMIT_INCREASE_UPDATE = 'limitIncrease.update';
    case VIRTUAL_ACCOUNT_NEW = 'virtualAccount.new';
    case VIRTUAL_ACCOUNT_COMPLETE = 'virtualAccount.complete';
    case TRANSFER_NEW = 'transfer.new';
    case TRANSFER_UPDATE = 'transfer.update';
    case TRANSFER_COMPLETE = 'transfer.complete';
    case WALLET_NEW = 'wallet.new';
    case WALLET_INBOUND = 'wallet.inbound';
}
