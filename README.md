# Blindpay PHP SDK

The official PHP SDK for [Blindpay](https://blindpay.com) - Global payments infrastructure made simple.

## Requirements

- PHP 8.2 or higher
- Composer

## Installation

```bash
composer require blindpay/php
```

## Authentication

To get started, you will need both your API key and your instance id, you can obtain your API key and instance id from the Blindpay dashboard [https://app.blindpay.com/instances/{instanceId}/api-keys](https://app.blindpay.com/instances/{instanceId}/api-keys)

```php
use BlindPay\SDK\BlindPay;

$blindpay = new BlindPay(
    apiKey: 'your-api-key-here',
    instanceId: 'your-instance-id-here'
);
```

> [!NOTE]  
> All api calls are going to use the provided api key and instance id

## Quick Start

### Check for available rails

```php
use BlindPay\SDK\BlindPay;

function getAvailableRails(): void
{
    $blindpay = new BlindPay(
        apiKey: 'your-api-key-here',
        instanceId: 'your-instance-id-here'
    );

    $response = $blindpay->available->getRails();

    if ($response->isError()) {
        throw new RuntimeException($response->error->message);
    }

    foreach ($response->data) {
        echo "Rail: {$rail->label} ({$rail->value}) - {$rail->country}\n";
    }
}

getAvailableRails();
```

For detailed API documentation, visit:

- [Blindpay API documentation](https://blindpay.com/docs/getting-started/overview)
- [API Reference](https://api.blindpay.com/reference)

## Support

- Email: [gabriel@blindpay.com](mailto:gabriel@blindpay.com)
- Issues: [GitHub Issues](https://github.com/blindpaylabs/blindpay-php/issues)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Made with ❤️ by the [Blindpay](https://blindpay.com) team
