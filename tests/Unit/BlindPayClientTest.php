<?php

declare(strict_types=1);

use BlindPay\SDK\BlindPayClient;
use BlindPay\SDK\Exceptions\BlindPayException;

it('throws exception when api key is not provided', function () {
    new BlindPayClient(
        apiKey: '',
        instanceId: 'test-instance-id'
    );
})->throws(BlindPayException::class, 'API key not provided');

it('throws exception when instance id is not provided', function () {
    new BlindPayClient(
        apiKey: 'test-api-key',
        instanceId: ''
    );
})->throws(BlindPayException::class, 'Instance ID not provided');

it('initializes successfully with valid credentials', function () {
    $client = new BlindPayClient(
        apiKey: 'test-api-key',
        instanceId: 'test-instance-id'
    );

    expect($client)->toBeInstanceOf(BlindPayClient::class);
    expect($client->getInstanceId())->toBe('test-instance-id');
});

it('has available resource', function () {
    $client = new BlindPayClient(
        apiKey: 'test-api-key',
        instanceId: 'test-instance-id'
    );

    expect($client->available)->toBeInstanceOf(\BlindPay\SDK\Resources\AvailableResource::class);
});

