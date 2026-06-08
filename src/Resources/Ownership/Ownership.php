<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Ownership;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;

readonly class MigrateInstanceOwnershipIn
{
    public function __construct(
        public string $userId
    ) {}

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
        ];
    }
}

readonly class MigrateInstanceOwnershipResponse
{
    public function __construct(
        public bool $success
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success']
        );
    }
}

class Ownership
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * Migrate instance ownership to another user
     *
     * @param MigrateInstanceOwnershipIn $input
     * @return BlindPayApiResponse<MigrateInstanceOwnershipResponse>
     */
    public function migrate(MigrateInstanceOwnershipIn $input): BlindPayApiResponse
    {
        $response = $this->client->post(
            "instances/{$this->instanceId}/ownership",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                MigrateInstanceOwnershipResponse::fromArray($response->data)
            );
        }

        return $response;
    }
}
