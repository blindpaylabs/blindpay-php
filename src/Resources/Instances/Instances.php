<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Instances;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\RequestOptions;
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

enum InstanceType: string
{
    case PRODUCTION = 'production';
    case DEVELOPMENT = 'development';
}

enum OnboardingStep: string
{
    case INITIAL = 'initial';
    case BUSINESS_DETAILS = 'business_details';
    case BUSINESS_PROFILE = 'business_profile';
    case DOCUMENTS = 'documents';
    case APPLICANT = 'applicant';
    case COMPLETED = 'completed';
    case BLOCKED = 'blocked';
}

/**
 * Your instance. On a `development` instance settlement is simulated with the test token USDB and
 * no real money moves.
 */
readonly class Instance
{
    public function __construct(
        public string $id,
        public string $name,
        public InstanceType|string $type,
        public OnboardingStep|string $onboardingStep,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            type: Decode::enum(InstanceType::class, $data['type']),
            onboardingStep: Decode::enum(OnboardingStep::class, $data['onboarding_step']),
        );
    }
}

readonly class InstanceMember
{
    public function __construct(
        public string $id,
        public string $email,
        public string $firstName,
        public ?string $middleName,
        public string $lastName,
        public ?string $imageUrl,
        public ?DateTimeImmutable $createdAt,
        public InstanceMemberRole|string $role
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            email: $data['email'],
            firstName: $data['first_name'],
            middleName: $data['middle_name'] ?? null,
            lastName: $data['last_name'],
            imageUrl: $data['image_url'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            role: Decode::enum(InstanceMemberRole::class, $data['role'])
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
            'created_at' => $this->createdAt?->format(\DateTimeInterface::ATOM),
            'role' => Decode::wire($this->role),
        ];
    }
}

readonly class UpdateInstanceInput
{
    public function __construct(
        public string $name,
        public ?string $customerInviteRedirectUrl = null,
        public ?bool $emailNotifications = null,
        public ?bool $requirePasskey = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
        ];

        if ($this->customerInviteRedirectUrl !== null) {
            $data['customer_invite_redirect_url'] = $this->customerInviteRedirectUrl;
        }

        if ($this->emailNotifications !== null) {
            $data['email_notifications'] = $this->emailNotifications;
        }

        if ($this->requirePasskey !== null) {
            $data['require_passkey'] = $this->requirePasskey;
        }

        return $data;
    }
}

readonly class UpdateMemberRoleInput
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

    /**
     * Your instance (always the client's).
     *
     * @return BlindPayApiResponse<Instance>
     */
    public function get(?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->client->get(Path::of('instances/%s', $this->instanceId), $options)
            ->map(fn (array $data) => Instance::fromArray($data));
    }

    /*
     * Get all members of the instance
     *
     * @return BlindPayApiResponse<InstanceMember[]>
     */
    public function getMembers(?RequestOptions $options = null): BlindPayApiResponse
    {
        $response = $this->client->get(Path::of('instances/%s/members', $this->instanceId), $options);

        return $response->map(function (array $data) {
            $members = array_map(
                fn (array $item) => InstanceMember::fromArray($item),
                $data
            );

            return $members;
        });
    }

    /*
     * Update instance details
     *
     * @param UpdateInstanceInput $input
     * @return BlindPayApiResponse<null>
     */
    public function update(UpdateInstanceInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->client->put(
            Path::of('instances/%s', $this->instanceId),
            $input->toArray(),
            $options
        );
    }

    /*
     * Delete the instance
     *
     * @return BlindPayApiResponse<null>
     */
    public function delete(?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->client->delete(Path::of('instances/%s', $this->instanceId), null, $options);
    }

    /*
     * Delete a member from the instance
     *
     * @param string $memberId
     * @return BlindPayApiResponse<null>
     */
    public function deleteMember(string $memberId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($memberId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Member ID cannot be empty')
            );
        }

        return $this->client->delete(Path::of('instances/%s/members/%s', $this->instanceId, $memberId), null, $options);
    }

    /*
     * Update a member's role
     *
     * @param UpdateMemberRoleInput $input
     * @return BlindPayApiResponse<null>
     */
    public function updateMemberRole(UpdateMemberRoleInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if (empty($input->memberId)) {
            return BlindPayApiResponse::error(
                ErrorResponse::validation('Member ID cannot be empty')
            );
        }

        return $this->client->put(
            Path::of('instances/%s/members/%s', $this->instanceId, $input->memberId),
            ['user_role' => $input->role->value],
            $options
        );
    }
}
