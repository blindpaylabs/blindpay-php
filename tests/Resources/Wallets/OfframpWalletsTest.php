<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Wallets\CreateOfframpWalletInput;
use BlindPay\SDK\Resources\Wallets\GetOfframpWalletInput;
use BlindPay\SDK\Resources\Wallets\ListOfframpWalletsInput;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OfframpWalletsTest extends TestCase
{
    private BlindPay $blindpay;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $this->blindpay = new BlindPay(
            apiKey: 'test-key',
            instanceId: 'in_000000000000'
        );

        $this->injectHttpClient($httpClient);
    }

    private function injectHttpClient(Client $client): void
    {
        $reflection = new ReflectionClass($this->blindpay);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($this->blindpay, $client);
    }

    private function mockResponse(array $body, int $status = 200): void
    {
        $this->mockHandler->append(
            new Response(
                $status,
                ['Content-Type' => 'application/json'],
                json_encode($body)
            )
        );
    }

    #[Test]
    public function it_lists_offramp_wallets(): void
    {
        $mockedOfframpWallets = [
            [
                'id' => 'ow_000000000000',
                'external_id' => 'your_external_id',
                'instance_id' => 'in_000000000000',
                'receiver_id' => 're_000000000000',
                'bank_account_id' => 'ba_000000000000',
                'network' => 'tron',
                'address' => 'TALJN9zTTEL9TVBb4WuTt6wLvPqJZr3hvb',
                'created_at' => '2021-01-01T00:00:00Z',
                'updated_at' => '2021-01-01T00:00:00Z',
            ],
        ];

        $this->mockResponse($mockedOfframpWallets);

        $input = new ListOfframpWalletsInput(
            receiverId: 're_000000000000',
            bankAccountId: 'ba_000000000000'
        );

        $response = $this->blindpay->wallets->offramp->list($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(1, $response->data);
        $this->assertEquals('ow_000000000000', $response->data[0]->id);
        $this->assertEquals('your_external_id', $response->data[0]->externalId);
        $this->assertEquals('in_000000000000', $response->data[0]->instanceId);
        $this->assertEquals('re_000000000000', $response->data[0]->receiverId);
        $this->assertEquals('ba_000000000000', $response->data[0]->bankAccountId);
        $this->assertEquals('tron', $response->data[0]->network);
        $this->assertEquals('TALJN9zTTEL9TVBb4WuTt6wLvPqJZr3hvb', $response->data[0]->address);
    }

    #[Test]
    public function it_creates_an_offramp_wallet(): void
    {
        $mockedOfframpWallet = [
            'id' => 'ow_000000000000',
            'external_id' => 'your_external_id',
            'network' => 'tron',
            'address' => 'TALJN9zTTEL9TVBb4WuTt6wLvPqJZr3hvb',
        ];

        $this->mockResponse($mockedOfframpWallet);

        $input = new CreateOfframpWalletInput(
            receiverId: 're_000000000000',
            bankAccountId: 'ba_000000000000',
            externalId: 'your_external_id',
            network: 'tron'
        );

        $response = $this->blindpay->wallets->offramp->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('ow_000000000000', $response->data->id);
        $this->assertEquals('your_external_id', $response->data->externalId);
        $this->assertEquals('tron', $response->data->network);
        $this->assertEquals('TALJN9zTTEL9TVBb4WuTt6wLvPqJZr3hvb', $response->data->address);
    }

    #[Test]
    public function it_gets_an_offramp_wallet(): void
    {
        $mockedOfframpWallet = [
            'id' => 'ow_000000000000',
            'external_id' => 'your_external_id',
            'instance_id' => 'in_000000000000',
            'receiver_id' => 're_000000000000',
            'bank_account_id' => 'ba_000000000000',
            'network' => 'tron',
            'address' => 'TALJN9zTTEL9TVBb4WuTt6wLvPqJZr3hvb',
            'created_at' => '2021-01-01T00:00:00Z',
            'updated_at' => '2021-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedOfframpWallet);

        $input = new GetOfframpWalletInput(
            id: 'ow_000000000000',
            bankAccountId: 'ba_000000000000',
            receiverId: 're_000000000000'
        );

        $response = $this->blindpay->wallets->offramp->get($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('ow_000000000000', $response->data->id);
        $this->assertEquals('your_external_id', $response->data->externalId);
        $this->assertEquals('in_000000000000', $response->data->instanceId);
        $this->assertEquals('re_000000000000', $response->data->receiverId);
        $this->assertEquals('ba_000000000000', $response->data->bankAccountId);
        $this->assertEquals('tron', $response->data->network);
        $this->assertEquals('TALJN9zTTEL9TVBb4WuTt6wLvPqJZr3hvb', $response->data->address);
    }
}
