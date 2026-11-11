<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Upload;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\RequestOptions;

enum UploadBucket: string
{
    case AVATAR = 'avatar';
    case ONBOARDING = 'onboarding';
    case LIMIT_INCREASE = 'limit_increase';
}

readonly class UploadInput
{
    public function __construct(
        public UploadBucket $bucket,
        public string $file
    ) {}

    public function toArray(): array
    {
        return [
            'bucket' => $this->bucket->value,
            'file' => $this->file,
        ];
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

class Upload
{
    public function __construct(
        private readonly ApiClientInterface $client
    ) {}

    /*
     * Upload a file
     *
     * @param UploadInput $input
     * @return BlindPayApiResponse<UploadResponse>
     */
    public function create(
        UploadInput $input,
        ?RequestOptions $options = null
    ): BlindPayApiResponse {
        $response = $this->client->post(
            'upload',
            $input->toArray(),
            $options
        );

        return $response->map(fn (array $data) => UploadResponse::fromArray($data));
    }
}
