<?php

declare(strict_types=1);

namespace BlindPay\SDK\Internal;

use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\RequestOptions;

interface ApiClientInterface
{
    /** @return BlindPayApiResponse<mixed> */
    public function get(string $path, ?RequestOptions $options = null): BlindPayApiResponse;

    /** @return BlindPayApiResponse<mixed> */
    public function post(string $path, array $body, ?RequestOptions $options = null): BlindPayApiResponse;

    /** @return BlindPayApiResponse<mixed> */
    public function put(string $path, array $body, ?RequestOptions $options = null): BlindPayApiResponse;

    /** @return BlindPayApiResponse<mixed> */
    public function patch(string $path, array $body, ?RequestOptions $options = null): BlindPayApiResponse;

    /** @return BlindPayApiResponse<mixed> */
    public function delete(string $path, ?array $body = null, ?RequestOptions $options = null): BlindPayApiResponse;

    /**
     * POST a multipart/form-data body (Guzzle `multipart` parts). Never carries an idempotency key
     * (the API does not apply idempotency to multipart requests).
     *
     * @param  list<array<string, mixed>>  $multipart
     * @return BlindPayApiResponse<mixed>
     */
    /** @return BlindPayApiResponse<mixed> */
    public function postMultipart(string $path, array $multipart, ?RequestOptions $options = null): BlindPayApiResponse;
}
