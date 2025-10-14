<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use BlindPay\SDK\BlindPayClient;
use BlindPay\SDK\Enums\Rail;

// Initialize the client
$client = new BlindPayClient(
    apiKey: getenv('BLINDPAY_API_KEY') ?: 'your-api-key',
    instanceId: getenv('BLINDPAY_INSTANCE_ID') ?: 'your-instance-id'
);

echo "=== BlindPay PHP SDK - Available Resource Example ===\n\n";

// Example 1: Get all available rails
echo "1. Getting all available rails...\n";
$railsResponse = $client->available->getRails();

if ($railsResponse->isSuccess()) {
    echo "Available rails:\n";
    foreach ($railsResponse->data as $rail) {
        echo sprintf(
            "  - %s (%s) - Country: %s\n",
            $rail->label,
            $rail->value,
            $rail->country
        );
    }
    echo "\n";
} else {
    echo "Error: {$railsResponse->error->message}\n\n";
}

// Example 2: Get bank details for PIX rail
echo "2. Getting bank details for PIX rail...\n";
$bankDetailsResponse = $client->available->getBankDetails(Rail::PIX);

if ($bankDetailsResponse->isSuccess()) {
    echo "PIX Bank Details:\n";
    foreach ($bankDetailsResponse->data as $detail) {
        echo sprintf(
            "  - %s (%s)%s\n",
            $detail->label,
            $detail->key,
            $detail->required ? ' [REQUIRED]' : ' [OPTIONAL]'
        );
        
        if ($detail->regex) {
            echo "    Validation: {$detail->regex}\n";
        }
        
        if (!empty($detail->items)) {
            echo "    Options:\n";
            foreach ($detail->items as $item) {
                $status = $item->isActive === false ? ' (inactive)' : '';
                echo "      • {$item->label}: {$item->value}{$status}\n";
            }
        }
        echo "\n";
    }
} else {
    echo "Error: {$bankDetailsResponse->error->message}\n\n";
}

// Example 3: Get bank details for ACH rail
echo "3. Getting bank details for ACH rail...\n";
$achDetailsResponse = $client->available->getBankDetails(Rail::ACH);

if ($achDetailsResponse->isSuccess()) {
    echo "ACH Bank Details:\n";
    foreach ($achDetailsResponse->data as $detail) {
        echo sprintf(
            "  - %s (%s)%s\n",
            $detail->label,
            $detail->key,
            $detail->required ? ' [REQUIRED]' : ' [OPTIONAL]'
        );
    }
    echo "\n";
} else {
    echo "Error: {$achDetailsResponse->error->message}\n\n";
}

echo "=== Example completed ===\n";

