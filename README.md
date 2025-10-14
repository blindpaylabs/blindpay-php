# BlindPay PHP SDK

Official PHP SDK for [BlindPay](https://blindpay.com) API - Global payments infrastructure.

A PHP SDK for interacting with the BlindPay API. This SDK provides a simple and intuitive way to integrate with BlindPay's payment services.

## Requirements

- PHP 8.2 or higher
- Composer
- ext-openssl
- ext-json

## Installation

```bash
composer require blindpay/php-sdk
```

## Usage

### Initialize the Client

```php
<?php

require_once 'vendor/autoload.php';

use BlindPay\SDK\BlindPayClient;

$client = new BlindPayClient(
    apiKey: 'your-api-key',
    instanceId: 'your-instance-id'
);
```

### Available Resource

The `available` resource provides methods to query available payment rails and their required bank details.

#### Get Available Rails

```php
use BlindPay\SDK\BlindPayClient;

$client = new BlindPayClient(
    apiKey: 'your-api-key',
    instanceId: 'your-instance-id'
);

$response = $client->available->getRails();

if ($response->isSuccess()) {
    foreach ($response->data as $rail) {
        echo "Rail: {$rail->label}\n";
        echo "Value: {$rail->value}\n";
        echo "Country: {$rail->country}\n";
        echo "---\n";
    }
} else {
    echo "Error: {$response->error->message}\n";
}
```

#### Get Bank Details for a Specific Rail

```php
use BlindPay\SDK\BlindPayClient;
use BlindPay\SDK\Enums\Rail;

$client = new BlindPayClient(
    apiKey: 'your-api-key',
    instanceId: 'your-instance-id'
);

$response = $client->available->getBankDetails(Rail::PIX);

if ($response->isSuccess()) {
    foreach ($response->data as $detail) {
        echo "Field: {$detail->label}\n";
        echo "Key: {$detail->key}\n";
        echo "Required: " . ($detail->required ? 'Yes' : 'No') . "\n";
        echo "Regex: {$detail->regex}\n";
        
        if (!empty($detail->items)) {
            echo "Options:\n";
            foreach ($detail->items as $item) {
                echo "  - {$item->label}: {$item->value}\n";
            }
        }
        
        echo "---\n";
    }
} else {
    echo "Error: {$response->error->message}\n";
}
```


## Response Structure

All API methods return a `BlindPayApiResponse` object with the following structure:

```php
class BlindPayApiResponse
{
    public mixed $data;          // The response data (null on error)
    public ?ErrorResponse $error; // Error details (null on success)
    
    public function isSuccess(): bool;
    public function isError(): bool;
}
```

### Success Response

```php
$response = $client->available->getRails();

if ($response->isSuccess()) {
    // Access the data
    $rails = $response->data;
}
```

### Error Response

```php
$response = $client->available->getRails();

if ($response->isError()) {
    // Access the error message
    echo $response->error->message;
}
```

## Error Handling

The SDK uses typed exceptions for error handling:

```php
use BlindPay\SDK\BlindPayClient;
use BlindPay\SDK\Exceptions\BlindPayException;

try {
    $client = new BlindPayClient(
        apiKey: '', // Empty API key will throw exception
        instanceId: 'your-instance-id'
    );
} catch (BlindPayException $e) {
    echo "Error: {$e->getMessage()}";
}
```

## Examples

See the `examples/` directory for more complete examples:

- `examples/available.php` - Working with the available resource

