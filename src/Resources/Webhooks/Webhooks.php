<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Webhooks;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\RequestOptions;
use DateTimeImmutable;

enum WebhookEvents: string
{
    case CUSTOMER_NEW = 'customer.new';
    case CUSTOMER_UPDATE = 'customer.update';
    case CUSTOMER_DELETE = 'customer.delete';
    case BANK_ACCOUNT_NEW = 'bankAccount.new';
    case PAYOUT_NEW = 'payout.new';
    case PAYOUT_UPDATE = 'payout.update';
    case PAYOUT_COMPLETE = 'payout.complete';
    case PAYOUT_PARTNER_FEE = 'payout.partnerFee';
    case BLOCKCHAIN_WALLET_NEW = 'blockchainWallet.new';
    case BLOCKCHAIN_WALLET_UPDATE = 'blockchainWallet.update';
    case PAYIN_NEW = 'payin.new';
    case PAYIN_UPDATE = 'payin.update';
    case PAYIN_COMPLETE = 'payin.complete';
    case PAYIN_PARTNER_FEE = 'payin.partnerFee';
    case TOS_ACCEPTED = 'tos.accept';
    case LIMIT_INCREASE_NEW = 'limitIncrease.new';
    case LIMIT_INCREASE_UPDATE = 'limitIncrease.update';
    case TRANSFER_NEW = 'transfer.new';
    case TRANSFER_COMPLETE = 'transfer.complete';
    case TRANSFER_UPDATE = 'transfer.update';
    case VIRTUAL_ACCOUNT_NEW = 'virtualAccount.new';
    case VIRTUAL_ACCOUNT_COMPLETE = 'virtualAccount.complete';
    case WALLET_INBOUND = 'wallet.inbound';
    case WALLET_NEW = 'wallet.new';
    case WALLET_UPDATE = 'wallet.update';
    case PAYABLE_NEW = 'payable.new';
    case PAYABLE_UPDATE = 'payable.update';
    case PAYABLE_COMPLETE = 'payable.complete';
}

readonly class WebhookEndpoint
{
    /**
     * @param  array<WebhookEvents|string>  $events  an event this SDK version does not know yet is kept as its raw name
     */
    public function __construct(
        public string $id,
        public string $url,
        public array $events,
        public ?string $lastEventAt,
        public string $instanceId,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            url: $data['url'],
            events: array_map(
                fn (string $event) => Decode::enum(WebhookEvents::class, $event),
                $data['events']
            ),
            lastEventAt: $data['last_event_at'] ?? null,
            instanceId: $data['instance_id'],
            createdAt: isset($data['created_at'])
                ? new DateTimeImmutable($data['created_at'])
                : null,
            updatedAt: isset($data['updated_at'])
                ? new DateTimeImmutable($data['updated_at'])
                : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'events' => array_map(Decode::wire(...), $this->events),
            'last_event_at' => $this->lastEventAt,
            'instance_id' => $this->instanceId,
            'created_at' => $this->createdAt?->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}

readonly class CreateWebhookEndpointInput
{
    /**
     * @param  WebhookEvents[]  $events
     */
    public function __construct(
        public string $url,
        public array $events
    ) {}

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'events' => array_map(
                fn (WebhookEvents $event) => $event->value,
                $this->events
            ),
        ];
    }
}

readonly class CreateWebhookEndpointResponse
{
    public function __construct(
        public string $id
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id']
        );
    }
}

readonly class GetWebhookEndpointSecretResponse
{
    public function __construct(
        public string $key
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key']
        );
    }
}

readonly class GetPortalAccessUrlResponse
{
    public function __construct(
        public string $url
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url']
        );
    }
}

class Webhooks
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /**
     * List all webhook endpoints
     *
     * @return BlindPayApiResponse<WebhookEndpoint[]>
     */
    public function list(?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->get(Path::of('instances/%s/webhook-endpoints', $this->instanceId), $options);

        return $response->map(function (array $data) {
            $webhooks = array_map(
                fn (array $item) => WebhookEndpoint::fromArray($item),
                $data
            );

            return $webhooks;
        });
    }

    /**
     * Create a webhook endpoint
     *
     * @return BlindPayApiResponse<CreateWebhookEndpointResponse>
     */
    public function create(CreateWebhookEndpointInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->post(
            Path::of('instances/%s/webhook-endpoints', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => CreateWebhookEndpointResponse::fromArray($data));
    }

    /**
     * Delete a webhook endpoint
     *
     * @return BlindPayApiResponse<null>
     */
    public function delete(string $id, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('ID cannot be empty')
            );
        }

        return $this->client->delete(Path::of('instances/%s/webhook-endpoints/%s', $this->instanceId, $id), null, $options);
    }

    /**
     * Get webhook endpoint secret
     *
     * @return BlindPayApiResponse<GetWebhookEndpointSecretResponse>
     */
    public function getSecret(string $id, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('ID cannot be empty')
            );
        }

        $response = $this->client->get(Path::of('instances/%s/webhook-endpoints/%s/secret', $this->instanceId, $id), $options);

        return $response->map(fn (array $data) => GetWebhookEndpointSecretResponse::fromArray($data));
    }

    /**
     * Get portal access URL
     *
     * @return BlindPayApiResponse<GetPortalAccessUrlResponse>
     */
    public function getPortalAccessUrl(?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->get(Path::of('instances/%s/webhook-endpoints/portal-access', $this->instanceId), $options);

        return $response->map(fn (array $data) => GetPortalAccessUrlResponse::fromArray($data));
    }
}
