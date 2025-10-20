<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Webhooks;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use DateTimeImmutable;

enum WebhookEvents: string
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
}

readonly class WebhookEndpoint
{
    /**
     * @param  WebhookEvents[]  $events
     */
    public function __construct(
        public string $id,
        public string $url,
        public array $events,
        public string $lastEventAt,
        public string $instanceId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            url: $data['url'],
            events: array_map(
                fn (string $event) => WebhookEvents::from($event),
                $data['events']
            ),
            lastEventAt: $data['last_event_at'],
            instanceId: $data['instance_id'],
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at'])
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'events' => array_map(
                fn (WebhookEvents $event) => $event->value,
                $this->events
            ),
            'last_event_at' => $this->lastEventAt,
            'instance_id' => $this->instanceId,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
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
    public function list(): BlindPayApiResponse
    {
        $response = $this->client->get("/instances/{$this->instanceId}/webhook-endpoints");

        if ($response->isSuccess() && is_array($response->data)) {
            $webhooks = array_map(
                fn (array $item) => WebhookEndpoint::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($webhooks);
        }

        return $response;
    }

    /**
     * Create a webhook endpoint
     *
     * @return BlindPayApiResponse<CreateWebhookEndpointResponse>
     */
    public function create(CreateWebhookEndpointInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/webhook-endpoints",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateWebhookEndpointResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /**
     * Delete a webhook endpoint
     *
     * @return BlindPayApiResponse<null>
     */
    public function delete(string $id): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('ID cannot be empty')
            );
        }

        return $this->client->delete("/instances/{$this->instanceId}/webhook-endpoints/{$id}");
    }

    /**
     * Get webhook endpoint secret
     *
     * @return BlindPayApiResponse<GetWebhookEndpointSecretResponse>
     */
    public function getSecret(string $id): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('ID cannot be empty')
            );
        }

        $response = $this->client->get("/instances/{$this->instanceId}/webhook-endpoints/{$id}/secret");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                GetWebhookEndpointSecretResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /**
     * Get portal access URL
     *
     * @return BlindPayApiResponse<GetPortalAccessUrlResponse>
     */
    public function getPortalAccessUrl(): BlindPayApiResponse
    {
        $response = $this->client->get("/instances/{$this->instanceId}/webhook-endpoints/portal-access");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                GetPortalAccessUrlResponse::fromArray($response->data)
            );
        }

        return $response;
    }
}
