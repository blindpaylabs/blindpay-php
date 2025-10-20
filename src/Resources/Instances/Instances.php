<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Instances;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use DateTimeImmutable;

enum InstanceMemberRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case FINANCE = 'finance';
    case CHECKER = 'checker';
    case OPERATIONS = 'operations';
    case DEVELOPER = 'developer';
    case VIEWER = 'viewer';
}

readonly class InstanceMember
{
    public function __construct(
        public string $id,
        public string $email,
        public string $firstName,
        public string $middleName,
        public string $lastName,
        public string $imageUrl,
        public DateTimeImmutable $createdAt,
        public InstanceMemberRole $role
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            email: $data['email'],
            firstName: $data['first_name'],
            middleName: $data['middle_name'],
            lastName: $data['last_name'],
            imageUrl: $data['image_url'],
            createdAt: new DateTimeImmutable($data['created_at']),
            role: InstanceMemberRole::from($data['role'])
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'image_url' => $this->imageUrl,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'role' => $this->role->value,
        ];
    }
}

readonly class UpdateInstanceInput
{
    public function __construct(
        public string $name,
        public ?string $receiverInviteRedirectUrl = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
        ];

        if ($this->receiverInviteRedirectUrl !== null) {
            $data['receiver_invite_redirect_url'] = $this->receiverInviteRedirectUrl;
        }

        return $data;
    }
}

readonly class UpdateInstanceMemberRoleInput
{
    public function __construct(
        public string $memberId,
        public InstanceMemberRole $role
    ) {}
}

class Instances
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * Get all members of the instance
     *
     * @return BlindPayApiResponse<InstanceMember[]>
     */
    public function getMembers(): BlindPayApiResponse
    {
        $response = $this->client->get("/instances/{$this->instanceId}/members");

        if ($response->isSuccess() && is_array($response->data)) {
            $members = array_map(
                fn (array $item) => InstanceMember::fromArray($item),
                $response->data
            );

            return BlindPayApiResponse::success($members);
        }

        return $response;
    }

    /*
     * Update instance details
     *
     * @param UpdateInstanceInput $input
     * @return BlindPayApiResponse<null>
     */
    public function update(UpdateInstanceInput $input): BlindPayApiResponse
    {
        return $this->client->put(
            "/instances/{$this->instanceId}",
            $input->toArray()
        );
    }

    /*
     * Delete the instance
     *
     * @return BlindPayApiResponse<null>
     */
    public function delete(): BlindPayApiResponse
    {
        return $this->client->delete("/instances/{$this->instanceId}");
    }

    /*
     * Delete a member from the instance
     *
     * @param string $memberId
     * @return BlindPayApiResponse<null>
     */
    public function deleteMember(string $memberId): BlindPayApiResponse
    {
        if (empty($memberId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Member ID cannot be empty')
            );
        }

        return $this->client->delete("/instances/{$this->instanceId}/members/{$memberId}");
    }

    /*
     * Update a member's role
     *
     * @param UpdateInstanceMemberRoleInput $input
     * @return BlindPayApiResponse<null>
     */
    public function updateMemberRole(UpdateInstanceMemberRoleInput $input): BlindPayApiResponse
    {
        if (empty($input->memberId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Member ID cannot be empty')
            );
        }

        return $this->client->put(
            "/instances/{$this->instanceId}/members/{$input->memberId}",
            ['role' => $input->role->value]
        );
    }
}
