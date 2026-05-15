<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Rfi;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ReceiverType;
use DateTimeImmutable;

enum RfiStatus: string
{
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}

readonly class RfiSection
{
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public ?string $description = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            title: $data['title'],
            description: $data['description'] ?? null
        );
    }
}

readonly class RfiResponse
{
    /**
     * @param  RfiSection[]  $request
     */
    public function __construct(
        public string $id,
        public string $receiverId,
        public string $instanceId,
        public RfiStatus $status,
        public array $request,
        public mixed $response,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $submittedAt,
        public DateTimeImmutable $createdAt,
        public ReceiverType $receiverType,
        public ?string $receiverAipriseSessionId,
        public string $receiverKycStatus
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            receiverId: $data['receiver_id'],
            instanceId: $data['instance_id'],
            status: RfiStatus::from($data['status']),
            request: array_map(
                fn (array $item) => RfiSection::fromArray($item),
                $data['request']
            ),
            response: $data['response'],
            expiresAt: new DateTimeImmutable($data['expires_at']),
            submittedAt: isset($data['submitted_at']) ? new DateTimeImmutable($data['submitted_at']) : null,
            createdAt: new DateTimeImmutable($data['created_at']),
            receiverType: ReceiverType::from($data['receiver_type']),
            receiverAipriseSessionId: $data['receiver_aiprise_session_id'] ?? null,
            receiverKycStatus: $data['receiver_kyc_status']
        );
    }
}

readonly class SubmitRfiInput
{
    public function __construct(
        public string $receiverId,
        public mixed $response
    ) {}

    public function toArray(): array
    {
        return [
            'response' => $this->response,
        ];
    }
}

readonly class SubmitRfiResponse
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

class Rfi
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /**
     * Get Open RFI for Receiver
     *
     * @return BlindPayApiResponse<RfiResponse>
     */
    public function get(string $receiverId): BlindPayApiResponse
    {
        if (empty($receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        $response = $this->client->get("instances/{$this->instanceId}/receivers/{$receiverId}/rfi");

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                RfiResponse::fromArray($response->data)
            );
        }

        return $response;
    }

    /**
     * Submit RFI Response
     *
     * @return BlindPayApiResponse<SubmitRfiResponse>
     */
    public function submit(SubmitRfiInput $input): BlindPayApiResponse
    {
        if (empty($input->receiverId)) {
            return BlindPayApiResponse::error(
                new \BlindPay\SDK\Types\ErrorResponse('Receiver ID cannot be empty')
            );
        }

        $response = $this->client->post(
            "instances/{$this->instanceId}/receivers/{$input->receiverId}/rfi",
            $input->toArray()
        );

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                SubmitRfiResponse::fromArray($response->data)
            );
        }

        return $response;
    }
}
