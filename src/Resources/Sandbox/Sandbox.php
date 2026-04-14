<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Sandbox;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use DateTimeImmutable;

enum SandboxStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

readonly class SandboxItem
{
    public function __construct(
        public string $id,
        public string $name,
        public SandboxStatus $status,
        public ?array $metadata = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            status: SandboxStatus::from($data['status']),
            metadata: $data['metadata'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null
        );
    }
}

readonly class CreateSandboxInput
{
    public function __construct(
        public string $name,
        public ?array $metadata = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
        ];

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }
}

readonly class UpdateSandboxInput
{
    public function __construct(
        public ?string $name = null,
        public ?array $metadata = null
    ) {}

    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }
}

class Sandbox
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * List all sandbox items
     *
     * @return BlindPayApiResponse<SandboxItem[]>
     */
    public function list(): BlindPayApiResponse
    {
        $response = $this->client->get("instances/{$this->instanceId}/sandbox");

        if ($response->isSuccess() && is_array($response->data)) {
            $items = array_map(
                fn (array $item) => SandboxItem::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($items);
        }

        return $response;
    }

    /*
     * Get a sandbox item by ID
     *
     * @param string $id
     * @return BlindPayApiResponse<SandboxItem>
     */
    public function get(string $id): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Sandbox ID cannot be empty')
            );
        }

        $response = $this->client->get("instances/{$this->instanceId}/sandbox/{$id}");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                SandboxItem::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Create a sandbox item
     *
     * @param CreateSandboxInput $input
     * @return BlindPayApiResponse<SandboxItem>
     */
    public function create(CreateSandboxInput $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "instances/{$this->instanceId}/sandbox",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                SandboxItem::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Update a sandbox item
     *
     * @param string $id
     * @param UpdateSandboxInput $input
     * @return BlindPayApiResponse<SandboxItem>
     */
    public function update(string $id, UpdateSandboxInput $input): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Sandbox ID cannot be empty')
            );
        }

        $response = $this->client->patch(
            "instances/{$this->instanceId}/sandbox/{$id}",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                SandboxItem::fromArray($response->data)
            );
        }

        return $response;
    }

    /*
     * Delete a sandbox item
     *
     * @param string $id
     * @return BlindPayApiResponse
     */
    public function delete(string $id): BlindPayApiResponse
    {
        if (empty($id)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Sandbox ID cannot be empty')
            );
        }

        return $this->client->delete("instances/{$this->instanceId}/sandbox/{$id}");
    }
}
