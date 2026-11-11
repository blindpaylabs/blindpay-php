<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Rfi;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\AccountClass;
use BlindPay\SDK\Types\AipriseDocumentType;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\RequestOptions;
use DateTimeImmutable;

enum RfiStatus: string
{
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}

/** One field BlindPay asks for in an RFI. Submit its answer under `key`. */
readonly class RfiField
{
    /**
     * @param  list<array<string, mixed>>|null  $items  choices, for select-like fields
     */
    public function __construct(
        public string $key,
        public string $label,
        public bool $required,
        public ?string $regex = null,
        public ?array $items = null,
        public ?bool $multiple = null,
        public AipriseDocumentType|string|null $aipriseDocumentType = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            label: $data['label'],
            required: (bool) $data['required'],
            regex: $data['regex'] ?? null,
            items: $data['items'] ?? null,
            multiple: $data['multiple'] ?? null,
            aipriseDocumentType: Decode::enum(AipriseDocumentType::class, $data['aiprise_document_type'] ?? null),
        );
    }
}

readonly class RfiSection
{
    /**
     * @param  RfiField[]  $fields
     */
    public function __construct(
        public string $title,
        public string $description,
        public array $fields,
        public ?string $supportingDocument = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'],
            fields: array_map(static fn (array $field) => RfiField::fromArray($field), $data['fields']),
            supportingDocument: $data['supporting_document'] ?? null,
        );
    }
}

/** A request for information about your instance (KYB). */
readonly class InstanceRfi
{
    /**
     * @param  RfiSection[]  $request  what BlindPay asks for
     * @param  array<string, mixed>  $response  the answers submitted so far, keyed by field key
     */
    public function __construct(
        public string $id,
        public string $instanceId,
        public RfiStatus|string $status,
        public array $request,
        public array $response,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $submittedAt,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            instanceId: $data['instance_id'],
            status: Decode::enum(RfiStatus::class, $data['status']),
            request: array_map(static fn (array $section) => RfiSection::fromArray($section), $data['request']),
            response: $data['response'] ?? [],
            expiresAt: new DateTimeImmutable($data['expires_at']),
            submittedAt: isset($data['submitted_at']) ? new DateTimeImmutable($data['submitted_at']) : null,
            createdAt: new DateTimeImmutable($data['created_at']),
        );
    }
}

/** A request for information about one of your customers. */
readonly class Rfi
{
    /**
     * @param  RfiSection[]  $request  what BlindPay asks for
     * @param  array<string, mixed>  $response  the answers submitted so far, keyed by field key
     */
    public function __construct(
        public string $id,
        public string $customerId,
        public string $instanceId,
        public RfiStatus|string $status,
        public array $request,
        public array $response,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $submittedAt,
        public DateTimeImmutable $createdAt,
        public AccountClass|string|null $customerType = null,
        public ?string $customerKycStatus = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            customerId: $data['customer_id'],
            instanceId: $data['instance_id'],
            status: Decode::enum(RfiStatus::class, $data['status']),
            request: array_map(static fn (array $section) => RfiSection::fromArray($section), $data['request']),
            response: $data['response'] ?? [],
            expiresAt: new DateTimeImmutable($data['expires_at']),
            submittedAt: isset($data['submitted_at']) ? new DateTimeImmutable($data['submitted_at']) : null,
            createdAt: new DateTimeImmutable($data['created_at']),
            customerType: Decode::enum(AccountClass::class, $data['customer_type'] ?? null),
            customerKycStatus: $data['customer_kyc_status'] ?? null,
        );
    }
}

/** The result of an RFI submission. `reason` is set when `success` is false. */
readonly class SubmitRfiResponse
{
    /**
     * @param  string|null  $reason  `no_pending_rfi` or `already_resolved` (or a newer reason)
     */
    public function __construct(
        public bool $success,
        public ?string $reason = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(success: (bool) $data['success'], reason: $data['reason'] ?? null);
    }
}

/** `$blindpay->instances->rfi`: the open RFI about your instance. */
class InstanceRfis
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client,
    ) {}

    /**
     * The open RFI, or null data when there is none.
     *
     * @return BlindPayApiResponse<InstanceRfi|null>
     */
    public function get(?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->client->get(Path::of('instances/%s/rfi', $this->instanceId), $options)
            ->map(fn (?array $data) => $data === null ? null : InstanceRfi::fromArray($data));
    }

    /**
     * Answer the open RFI. $response is sent as the whole JSON body: a flat map from each
     * RfiField `key` to its answer (file fields take an uploaded file URL).
     *
     * @param  array<string, mixed>  $response
     * @return BlindPayApiResponse<SubmitRfiResponse>
     */
    public function submit(array $response, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->client->post(Path::of('instances/%s/rfi', $this->instanceId), $response, $options)
            ->map(fn (array $data) => SubmitRfiResponse::fromArray($data));
    }
}

/** `$blindpay->customers->rfi`: open RFIs about your customers. */
class CustomerRfis
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client,
    ) {}

    /**
     * The customer's open RFI, or null data when there is none.
     *
     * @return BlindPayApiResponse<Rfi|null>
     */
    public function get(string $customerId, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($customerId === '') {
            return BlindPayApiResponse::error(ErrorResponse::validation('Customer ID cannot be empty'));
        }

        return $this->client->get(Path::of('instances/%s/customers/%s/rfi', $this->instanceId, $customerId), $options)
            ->map(fn (?array $data) => $data === null ? null : Rfi::fromArray($data));
    }

    /**
     * Answer the customer's open RFI. $response is sent as the whole JSON body: a flat map from
     * each RfiField `key` to its answer (file fields take an uploaded file URL).
     *
     * @param  array<string, mixed>  $response
     * @return BlindPayApiResponse<SubmitRfiResponse>
     */
    public function submit(string $customerId, array $response, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($customerId === '') {
            return BlindPayApiResponse::error(ErrorResponse::validation('Customer ID cannot be empty'));
        }

        return $this->client->post(Path::of('instances/%s/customers/%s/rfi', $this->instanceId, $customerId), $response, $options)
            ->map(fn (array $data) => SubmitRfiResponse::fromArray($data));
    }
}
