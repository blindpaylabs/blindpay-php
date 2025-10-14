<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use BlindPay\SDK\BlindPayClient;

function getAvailableRails(): void
{
    $blindpay = new BlindPayClient(
        apiKey: 'your-api-key-here',
        instanceId: 'your-instance-id-here'
    );

    $response = $blindpay->available->getRails();
    
    if ($response->isError()) {
        throw new Exception($response->error->message);
    }
    
    echo "Rails: \n";
    print_r($response->data);
}

getAvailableRails();

