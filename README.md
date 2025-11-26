# BlindPay PHP SDK

The official PHP SDK for [BlindPay](https://blindpay.com) -  Global payments infrastructure made simple.

## Table of Contents
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Authentication](#authentication)
- [Resources](#resources)
- [Error Handling](#error-handling)
- [Webhooks](#webhooks)
- [Support](#support)
- [License](#license)

## Prerequisites

- PHP 8.2 or higher
- Composer
- BlindPay account with API credentials (get yours at [dashboard.blindpay.com](https://dashboard.blindpay.com))
- Extensions: `ext-json`, `ext-openssl`

## Installation

Install via Composer:

```bash
composer require blindpay/php
```

## Authentication

To get started, you will need both your API key and your instance id, you can obtain your API key and instance id from the Blindpay dashboard [https://app.blindpay.com/instances/{instanceId}/api-keys](https://app.blindpay.com/instances/{instanceId}/api-keys)

```typescript
import { BlindPay } from '@blindpay/node';

const blindpay = new BlindPay({
    apiKey: 'your-api-key-here',
    instanceId: 'your-instance-id-here'
  })
```

> [!NOTE]  
> All api calls are going to use the provided api key and instance id

## Resources

### Receivers

```php
$response = $blindpay->receivers->list();
$response = $blindpay->receivers->get($receiverId);
$response = $blindpay->receivers->createIndividualWithStandardKYC($data);
$response = $blindpay->receivers->update($data);
$response = $blindpay->receivers->delete($receiverId);
$response = $blindpay->receivers->getLimits($receiverId);
$response = $blindpay->receivers->requestLimitIncrease($data);
```

#### Bank Accounts

```php
$response = $blindpay->receivers->bankAccounts->list($receiverId);
$response = $blindpay->receivers->bankAccounts->get($data);
$response = $blindpay->receivers->bankAccounts->createPix($data);
$response = $blindpay->receivers->bankAccounts->createArgentinaTransfers($data);
$response = $blindpay->receivers->bankAccounts->createSpei($data);
$response = $blindpay->receivers->bankAccounts->createColombiaAch($data);
$response = $blindpay->receivers->bankAccounts->createInternationalSwift($data);
$response = $blindpay->receivers->bankAccounts->createRtp($data);
$response = $blindpay->receivers->bankAccounts->createWire($data);
$response = $blindpay->receivers->bankAccounts->createAch($data);
$response = $blindpay->receivers->bankAccounts->delete($data);
```

### Quotes

```php
$response = $blindpay->quotes->create($data);
$response = $blindpay->quotes->getFxRate($data);
```

### Payouts

```php
$response = $blindpay->payouts->list($data); // optional filters or/and pagination
$response = $blindpay->payouts->get($payoutId);
$response = $blindpay->payouts->getTrack($payoutId);
$response = $blindpay->payouts->createStellar($data);
$response = $blindpay->payouts->createEvm($data);
$response = $blindpay->payouts->export($data); // optional pagination
```

### Payins

```php
$response = $blindpay->payins->list($data); // optional filters or/and pagination 
$response = $blindpay->payins->get($payinId);
$response = $blindpay->payins->quotes->create($data);
```

### Virtual Accounts

```php
$response = $blindpay->virtualAccounts->create($data);
$response = $blindpay->virtualAccounts->get($receiverId);
$response = $blindpay->virtualAccounts->update($data);
```

### Wallets

#### Blockchain Wallets

```php
$response = $blindpay->wallets->blockchain->list($receiverId);
$response = $blindpay->wallets->blockchain->get($walletId);
```

#### Offramp Wallets

```php
$response = $blindpay->wallets->offramp->list();
$response = $blindpay->wallets->offramp->get($walletId);
$response = $blindpay->wallets->offramp->create($data);
```

### Instances

```php
$response = $blindpay->instances->getMembers();
$response = $blindpay->instances->update($data);
$response = $blindpay->instances->deleteMember($memberId);
$response = $blindpay->instances->updateMemberRole($data);
$response = $blindpay->instances->delete();
```

#### Webhooks

```php
$response = $blindpay->instances->webhookEndpoints->list();
$response = $blindpay->instances->webhookEndpoints->create($data);
$response = $blindpay->instances->webhookEndpoints->getSecret($webhookId);
$response = $blindpay->instances->webhookEndpoints->getPortalAccessUrl();
$response = $blindpay->instances->webhookEndpoints->delete($webhookId);
```

#### Api Keys

```php
$response = $blindpay->instances->apiKeys->list();
$response = $blindpay->instances->apiKeys->create($data);
$response = $blindpay->instances->apiKeys->get($keyId);
$response = $blindpay->instances->apiKeys->delete($keyId);
```

### Partner Fees

```php
$response = $blindpay->partnerFees->list();
$response = $blindpay->partnerFees->get($feeId);
$response = $blindpay->partnerFees->create($data);
$response = $blindpay->partnerFees->delete($feeId);
```


### Available
```php
$response = $blindpay->available->getBankDetails($rail);
$response = $blindpay->available->getRails();
```


## Webhooks

BlindPay uses webhooks to notify your application about events (e.g., payment completed, new receivers, new wallets), all webhooks are signed using Svix

### Webhook Verification

Always verify webhook signatures before processing events:

> **Warning**
>
> The `$payload` must be set to the **raw** request body, do not parse or modify it before verifying the signature. Passing a parsed or altered payload will result in invalid signature verification.


```php
<?php

// Get headers from the webhook request
$svixId = $_SERVER['HTTP_SVIX_ID'] ?? '';
$svixTimestamp = $_SERVER['HTTP_SVIX_TIMESTAMP'] ?? '';
$svixSignature = $_SERVER['HTTP_SVIX_SIGNATURE'] ?? '';

// Get the raw request body
$payload = file_get_contents('php://input');

// Your webhook secret from BlindPay dashboard
$webhookSecret = 'whsec_your_webhook_secret';

$blindpay = new BlindPay($apiKey, $instanceId);

// Verify the signature
$isValid = $blindpay->verifyWebhookSignature(
    secret: $webhookSecret,
    id: $svixId,
    timestamp: $svixTimestamp,
    payload: $payload,
    svixSignature: $svixSignature
);

if (!$isValid) {
    http_response_code(401);
    exit('Invalid signature');
}

// Process the webhook
$event = json_decode($payload, true);

switch ($event['type']) {
    case 'payout.completed':
        handlePayoutCompleted($event);
        break;
    case 'receiver.kyc.approved':
        handleKycApproved($event);
        break;
    default:
        error_log("Unknown webhook event: {$event['type']}");
}

http_response_code(200);
```

### Webhook Events

All [webhook events](https://api.blindpay.com/reference#webhooks) are listed in our API Reference

## Support

- **API Reference:** [https://api.blindpay.com/reference](https://api.blindpay.com/reference)
- **Email:** gabriel@blindpay.com
- **Issues:** [GitHub Issues](https://github.com/blindpay/blindpay-php/issues)

## License

This SDK is licensed under the MIT License - see the [LICENSE](LICENSE) file for details
