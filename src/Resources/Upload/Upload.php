<?php

declare(strict_types=1);

namespace BlindPay\SDK\Resources\Upload;

use BlindPay\SDK\Internal\ApiClientInterface;
use BlindPay\SDK\Types\BlindPayApiResponse;

enum UploadBucket: string
{
    case AVATAR = 'avatar';
    case ONBOARDING = 'onboarding';
    case LIMIT_INCREASE = 'limit_increase';
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
        private readonly string $instanceId,
        private readonly ApiClientInterface $client
    ) {}

    /*
     * Upload a file to a bucket
     *
     * @param UploadBucket $bucket The target bucket
     * @param mixed $file The file contents (string, resource, or stream)
     * @param string $filename The filename to use for the upload
     * @return BlindPayApiResponse<UploadResponse>
     */
    public function upload(UploadBucket $bucket, mixed $file, string $filename): BlindPayApiResponse
    {
        $multipart = [
            ['name' => 'bucket', 'contents' => $bucket->value],
            ['name' => 'file', 'contents' => $file, 'filename' => $filename],
        ];

        $queryParams = '?instance_id='.$this->instanceId;
        $response = $this->client->multipart("upload{$queryParams}", $multipart);

        if ($response->isSuccess() && is_array($response->data)) {
            return BlindPayApiResponse::success(
                UploadResponse::fromArray($response->data)
            );
        }

        return $response;
    }
}
