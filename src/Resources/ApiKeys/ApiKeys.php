<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\ApiKeys;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\BlindPayException;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\Permission;
use DateTimeImmutable;
use DateTimeInterface;

readonly class ApiKey
{
    public function __construct(
        public string $id,
        public string $name,
        public Permission $permission,
        public string $token,
        public array $ipWhitelist,
        public string $unkeyId,
        public ?DateTimeImmutable $lastUsedAt,
        public string $instanceId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            permission: Permission::from($data['permission']),
            token: $data['token'],
            ipWhitelist: $data['ip_whitelist'] ?? [],
            unkeyId: $data['unkey_id'],
            lastUsedAt: isset($data['last_used_at']) && $data['last_used_at'] !== null
                ? new DateTimeImmutable($data['last_used_at'])
                : null,
            instanceId: $data['instance_id'],
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at'])
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'permission' => $this->permission->value,
            'token' => $this->token,
            'ip_whitelist' => $this->ipWhitelist,
            'unkey_id' => $this->unkeyId,
            'last_used_at' => $this->lastUsedAt?->format(DateTimeInterface::ATOM),
            'instance_id' => $this->instanceId,
            'created_at' => $this->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}

readonly class CreateApiKeyInput
{
    /*
     * @param string[] $ipWhitelist
     */
    public function __construct(
        public string $name,
        public Permission $permission = Permission::FULL_ACCESS,
        public array $ipWhitelist = []
    ) {
        if (empty($this->name)) {
            throw BlindPayException::validation('API key name cannot be empty');
        }

        foreach ($this->ipWhitelist as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                throw BlindPayException::validation("Invalid IP address: {$ip}");
            }
        }
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'permission' => $this->permission->value,
        ];

        if (! empty($this->ipWhitelist)) {
            $data['ip_whitelist'] = $this->ipWhitelist;
        }

        return $data;
    }
}

readonly class CreateApiKeyResponse
{
    public function __construct(
        public string $id,
        public string $token
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            token: $data['token']
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
        ];
    }
}

class ApiKeys
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * List all API keys
     *
     * @return BlindPayApiResponse<ApiKey[]>
     */
    public function list(): BlindPayApiResponse
    {
        $response = $this->client->get("/instances/{$this->instanceId}/api-keys");

        if ($response->isSuccess() && is_array($response->data)) {
            $apiKeys = array_map(
                fn (array $item) => ApiKey::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($apiKeys);
        }

        return $response;
    }

    /*
     * Create a new API key
     *
     * @param CreateApiKeyInput $input
     * @return BlindPayApiResponse<CreateApiKeyResponse>
     */
    public function create(CreateApiKeyInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "/instances/{$this->instanceId}/api-keys",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                CreateApiKeyResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Get an API key by ID
     *
     * @param string $id
     * @return BlindPayApiResponse<ApiKey>
     */
    public function get(string $id): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('ID cannot be empty')
            );
        }

        $response = $this->client->get("/instances/{$this->instanceId}/api-keys/{$id}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                ApiKey::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Delete an API key
     *
     * @param string $id
     * @return BlindPayApiResponse<null>
     */
    public function delete(string $id): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('ID cannot be empty')
            );
        }

        return $this->client->delete("/instances/{$this->instanceId}/api-keys/{$id}");
    }
}
