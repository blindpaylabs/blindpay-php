<?php

declare(strict_types=1);

use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorResponse;

it('creates success response correctly', function () {
    $data = ['key' => 'value'];
    $response = BlindPayApiResponse::success($data);

    expect($response->isSuccess())->toBeTrue();
    expect($response->isError())->toBeFalse();
    expect($response->data)->toBe($data);
    expect($response->error)->toBeNull();
});

it('creates error response correctly', function () {
    $error = new ErrorResponse('Test error');
    $response = BlindPayApiResponse::error($error);

    expect($response->isError())->toBeTrue();
    expect($response->isSuccess())->toBeFalse();
    expect($response->data)->toBeNull();
    expect($response->error)->toBe($error);
    expect($response->error->message)->toBe('Test error');
});

