<?php

declare(strict_types=1);

namespace BlindPay\SDK\Webhooks;

use BlindPay\SDK\Resources\BankAccounts\BankAccountListItem;
use BlindPay\SDK\Resources\CustodialWallets\CustodialWallet;
use BlindPay\SDK\Resources\Customers\BusinessWithStandardKYB;
use BlindPay\SDK\Resources\Customers\Customers;
use BlindPay\SDK\Resources\Customers\IndividualWithEnhancedKYC;
use BlindPay\SDK\Resources\Customers\IndividualWithStandardKYC;
use BlindPay\SDK\Resources\Customers\LimitIncreaseRequest;
use BlindPay\SDK\Resources\Payables\Payable;
use BlindPay\SDK\Resources\Payins\CreateEvmPayinResponse;
use BlindPay\SDK\Resources\Payouts\CreateEvmPayoutResponse;
use BlindPay\SDK\Resources\Transfers\CreateTransferResponse;
use BlindPay\SDK\Resources\VirtualAccounts\VirtualAccount;
use BlindPay\SDK\Resources\Wallets\BlockchainWallet;
use BlindPay\SDK\Resources\Webhooks\WebhookEvents;

/**
 * A verified webhook delivery, from WebhookVerifier::constructEvent().
 *
 * `type` is the `webhook_event` name: a WebhookEvents case for the events this SDK version knows,
 * or the raw string for one added later. `data` is the typed payload (see the table below) and is
 * null for unknown events; `raw` is always the decoded JSON body.
 *
 *     match ($event->type) {
 *         WebhookEvents::PAYOUT_COMPLETE, WebhookEvents::PAYOUT_UPDATE => handlePayout($event->data),
 *         default => null, // unknown or unhandled: acknowledge with 2xx anyway
 *     };
 *
 * A failed payout can arrive as `payout.update` *or* `payout.complete`: branch on
 * `$event->data->status`, not on the event name.
 *
 * | Events                                         | `data`                                     |
 * |------------------------------------------------|--------------------------------------------|
 * | customer.new, customer.update                  | IndividualWithStandardKYC, IndividualWithEnhancedKYC or BusinessWithStandardKYB |
 * | customer.delete                                | CustomerDeletedPayload                     |
 * | bankAccount.new                                | BankAccountListItem                        |
 * | blockchainWallet.new, blockchainWallet.update  | BlockchainWallet                           |
 * | limitIncrease.new, limitIncrease.update        | LimitIncreaseRequest                       |
 * | payout.new/update/complete/partnerFee          | CreateEvmPayoutResponse                    |
 * | payin.new/update/complete/partnerFee           | CreateEvmPayinResponse                     |
 * | transfer.new/update/complete                   | CreateTransferResponse                     |
 * | payable.new/update/complete                    | Payable                                    |
 * | virtualAccount.new, virtualAccount.complete    | VirtualAccount                             |
 * | wallet.new, wallet.update                      | CustodialWallet                            |
 * | wallet.inbound                                 | WalletInboundPayload                       |
 * | tos.accept                                     | TosAcceptedPayload                         |
 */
final readonly class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $raw  the decoded JSON body
     */
    public function __construct(
        public WebhookEvents|string $type,
        public ?object $data,
        public array $raw,
    ) {}

    /** Whether this SDK version knows the event type (and so decoded `data`). */
    public function isKnown(): bool
    {
        return $this->type instanceof WebhookEvents;
    }

    /**
     * Decode an already-verified webhook body. Use WebhookVerifier::constructEvent() to verify and
     * decode in one step.
     *
     * @param  array<string, mixed>  $body
     *
     * @throws WebhookException when the body has no `webhook_event` or a known event's payload
     *                          does not decode
     */
    public static function fromArray(array $body): self
    {
        $name = $body['webhook_event'] ?? null;
        if (! is_string($name) || $name === '') {
            throw new WebhookException('The webhook body has no webhook_event.');
        }
        $type = WebhookEvents::tryFrom($name);
        if ($type === null) {
            return new self($name, null, $body);
        }

        set_error_handler(static function (int $severity, string $message): bool {
            throw new \ErrorException($message, 0, $severity);
        }, E_WARNING | E_NOTICE);

        try {
            $data = self::decode($type, $body);
        } catch (\Throwable $e) {
            throw new WebhookException("The {$name} payload could not be decoded: {$e->getMessage()}", previous: $e instanceof \Exception ? $e : null);
        } finally {
            restore_error_handler();
        }

        return new self($type, $data, $body);
    }

    private static function decode(WebhookEvents $type, array $body): object
    {
        return match ($type) {
            WebhookEvents::CUSTOMER_NEW, WebhookEvents::CUSTOMER_UPDATE => Customers::customerFromArray($body),
            WebhookEvents::CUSTOMER_DELETE => CustomerDeletedPayload::fromArray($body),
            WebhookEvents::BANK_ACCOUNT_NEW => BankAccountListItem::fromArray($body),
            WebhookEvents::BLOCKCHAIN_WALLET_NEW, WebhookEvents::BLOCKCHAIN_WALLET_UPDATE => BlockchainWallet::fromArray($body),
            WebhookEvents::LIMIT_INCREASE_NEW, WebhookEvents::LIMIT_INCREASE_UPDATE => LimitIncreaseRequest::fromArray($body),
            WebhookEvents::PAYOUT_NEW, WebhookEvents::PAYOUT_UPDATE, WebhookEvents::PAYOUT_COMPLETE,
            WebhookEvents::PAYOUT_PARTNER_FEE => CreateEvmPayoutResponse::fromArray($body),
            WebhookEvents::PAYIN_NEW, WebhookEvents::PAYIN_UPDATE, WebhookEvents::PAYIN_COMPLETE,
            WebhookEvents::PAYIN_PARTNER_FEE => CreateEvmPayinResponse::fromArray($body),
            WebhookEvents::TRANSFER_NEW, WebhookEvents::TRANSFER_UPDATE, WebhookEvents::TRANSFER_COMPLETE => CreateTransferResponse::fromArray($body),
            WebhookEvents::PAYABLE_NEW, WebhookEvents::PAYABLE_UPDATE, WebhookEvents::PAYABLE_COMPLETE => Payable::fromArray($body),
            WebhookEvents::VIRTUAL_ACCOUNT_NEW, WebhookEvents::VIRTUAL_ACCOUNT_COMPLETE => VirtualAccount::fromArray($body),
            WebhookEvents::WALLET_NEW, WebhookEvents::WALLET_UPDATE => CustodialWallet::fromArray($body),
            WebhookEvents::WALLET_INBOUND => WalletInboundPayload::fromArray($body),
            WebhookEvents::TOS_ACCEPTED => TosAcceptedPayload::fromArray($body),
        };
    }
}
