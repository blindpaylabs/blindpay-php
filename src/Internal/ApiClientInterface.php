<?php

declare(strict_types=1);

namespace BlindPay\SDK\Internal;

use BlindPay\SDK\Types\BlindPayApiResponse;

interface ApiClientInterface
{
    public function get(string $path): BlindPayApiResponse;

    public function post(string $path, array $body): BlindPayApiResponse;

    public function put(string $path, array $body): BlindPayApiResponse;

    public function patch(string $path, array $body): BlindPayApiResponse;

    public function delete(string $path, ?array $body = null): BlindPayApiResponse;
}
