<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Ownership;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\RequestOptions;

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

    /**
     * Migrate instance ownership to another user
     *
     * @return BlindPayApiResponse<MigrateInstanceOwnershipResponse>
     */
    public function migrate(
        MigrateInstanceOwnershipIn $input,
        ?RequestOptions $options = null
    ): BlindPayApiResponse {
        $response = $this->client->post(
            Path::of('instances/%s/ownership', $this->instanceId),
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => MigrateInstanceOwnershipResponse::fromArray($data));
    }
}
