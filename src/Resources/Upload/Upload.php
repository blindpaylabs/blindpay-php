<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Upload;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Internal\Decode;
use BlindPay\SDK\Internal\Query;
use BlindPay\SDK\Types\ApprovalRate;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\RequestOptions;
use JsonException;
use Psr\Http\Message\StreamInterface;

enum UploadBucket: string
{
    case AVATAR = 'avatar';
    case ONBOARDING = 'onboarding';
    case LIMIT_INCREASE = 'limit_increase';
    case DOCUMENTS = 'documents';
}

/** What a document sent to upload->analyze() is expected to be. */
enum UploadAnalyzeType: string
{
    case PROOF_OF_ADDRESS = 'proof_of_address';
    case IDENTITY_DOCUMENT = 'identity_document';
    case INCORPORATION_DOCUMENT = 'incorporation_document';
    case PROOF_OF_OWNERSHIP = 'proof_of_ownership';
    case SOURCE_OF_FUNDS = 'source_of_funds';
    case SELFIE = 'selfie';
    case BANK_STATEMENT = 'bank_statement';
    case TAX_RETURN = 'tax_return';
    case PROOF_OF_INCOME = 'proof_of_income';
    case FINANCIAL_STATEMENT = 'financial_statement';
    case INVOICE = 'invoice';
    case TRANSACTION_DOCUMENT = 'transaction_document';
    case PASSPORT = 'passport';
    case PAY_STUB = 'pay_stub';
    case EMPLOYMENT_LETTER = 'employment_letter';
    case INVESTMENT = 'investment';
    case CRYPTO_EXCHANGE = 'crypto_exchange';
    case BLOCKCHAIN_WALLET = 'blockchain_wallet';
    case CONTRACT = 'contract';
    case ACCOUNTS_RECEIVABLE = 'accounts_receivable';
    case MERCHANT_PROCESSOR = 'merchant_processor';
    case SHAREHOLDER_LOAN = 'shareholder_loan';
}

/**
 * A file to send as multipart/form-data: its bytes (a string, a stream resource or a PSR-7
 * stream), a filename and a content type.
 */
final readonly class UploadFile
{
    /**
     * @param  string|resource|StreamInterface  $contents
     */
    public function __construct(
        public mixed $contents,
        public string $filename,
        public string $contentType = 'application/octet-stream',
    ) {}

    /** Read the file at $path (opened lazily by Guzzle; the content type is guessed when omitted). */
    public static function fromPath(string $path, ?string $contentType = null, ?string $filename = null): self
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException("Cannot open {$path} for reading.");
        }

        return new self($handle, $filename ?? basename($path), $contentType ?? (mime_content_type($path) ?: 'application/octet-stream'));
    }

    /**
     * Guzzle multipart parts for form fields: an UploadFile becomes a file part, anything else a
     * plain field.
     *
     * @param  array<string, self|string>  $fields
     * @return list<array<string, mixed>>
     */
    public static function parts(array $fields): array
    {
        $parts = [];
        foreach ($fields as $name => $value) {
            $parts[] = $value instanceof self
                ? ['name' => $name, 'contents' => $value->contents, 'filename' => $value->filename, 'headers' => ['Content-Type' => $value->contentType]]
                : ['name' => $name, 'contents' => $value];
        }

        return $parts;
    }
}

readonly class UploadInput
{
    public function __construct(
        public UploadBucket $bucket,
        public UploadFile $file,
    ) {}

    /** @return list<array<string, mixed>> the multipart form fields, as Guzzle parts */
    public function toArray(): array
    {
        return UploadFile::parts([
            'file' => $this->file,
            'bucket' => $this->bucket->value,
        ]);
    }
}

readonly class UploadResponse
{
    public function __construct(
        public string $fileUrl
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            fileUrl: $data['file_url']
        );
    }
}

readonly class AnalyzeUploadInput
{
    /**
     * @param  array<string, mixed>|null  $metadata  expected values to check the document against
     *                                               (e.g. ['full_name' => 'Jane Doe']); a clear
     *                                               mismatch caps the rating at low
     */
    public function __construct(
        public UploadFile $file,
        public UploadAnalyzeType $type,
        public ?array $metadata = null,
    ) {}

    /**
     * @return list<array<string, mixed>> the multipart form fields
     *
     * @throws JsonException when $metadata cannot be encoded
     */
    public function toArray(): array
    {
        return UploadFile::parts(array_filter([
            'file' => $this->file,
            'type' => $this->type->value,
            'metadata' => $this->metadata === null ? null : json_encode($this->metadata, JSON_THROW_ON_ERROR),
        ], static fn ($value) => $value !== null));
    }
}

readonly class AnalyzeUploadResponse
{
    public function __construct(
        public ApprovalRate|string $approvalRate,
        public string $description,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            approvalRate: Decode::enum(ApprovalRate::class, $data['approval_rate']),
            description: $data['description'],
        );
    }
}

readonly class PresignResponse
{
    public function __construct(
        public string $fileUrl,
        public string $expiresAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(fileUrl: $data['file_url'], expiresAt: $data['expires_at']);
    }
}

/**
 * `$blindpay->upload`: file uploads for KYC/KYB documents. Uploads authenticate your API key
 * against your instance ID (sent as `?instance_id=`) and need a completed onboarding. Multipart
 * requests carry no idempotency key and are retried only when nothing was sent.
 */
class Upload
{
    public function __construct(
        private readonly string $instanceId,
        private readonly ApiClientInterface $client,
    ) {}

    /**
     * Upload a file (multipart) and get its URL, to pass in KYC/KYB fields.
     *
     *     $blindpay->upload->create(new UploadInput(UploadBucket::ONBOARDING, UploadFile::fromPath('id.pdf')));
     *
     * @return BlindPayApiResponse<UploadResponse>
     */
    public function create(UploadInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        return $this->client->postMultipart(
            'upload'.Query::build(['instance_id' => $this->instanceId]),
            $input->toArray(),
            $options,
        )->map(fn (array $data) => UploadResponse::fromArray($data));
    }

    /**
     * Rate how likely a document is to be approved as $input->type, before you submit it.
     *
     * @return BlindPayApiResponse<AnalyzeUploadResponse>
     */
    public function analyze(AnalyzeUploadInput $input, ?RequestOptions $options = null): BlindPayApiResponse
    {
        try {
            $parts = $input->toArray();
        } catch (JsonException $e) {
            return BlindPayApiResponse::error(ErrorResponse::validation('metadata cannot be encoded as JSON: '.$e->getMessage()));
        }

        return $this->client->postMultipart('upload/analyze'.Query::build(['instance_id' => $this->instanceId]), $parts, $options)
            ->map(fn (array $data) => AnalyzeUploadResponse::fromArray($data));
    }

    /**
     * A short-lived, signed URL to read a file you uploaded.
     *
     * @return BlindPayApiResponse<PresignResponse>
     */
    public function presign(string $fileUrl, ?RequestOptions $options = null): BlindPayApiResponse
    {
        if ($fileUrl === '') {
            return BlindPayApiResponse::error(ErrorResponse::validation('File URL cannot be empty'));
        }

        return $this->client->post('presign'.Query::build(['instance_id' => $this->instanceId]), ['file_url' => $fileUrl], $options)
            ->map(fn (array $data) => PresignResponse::fromArray($data));
    }
}
