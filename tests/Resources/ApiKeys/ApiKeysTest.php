<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\ApiKeys\CreateApiKeyInput;
use BlindPay\SDK\Types\Permission;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ApiKeysTest extends TestCase
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
    public function it_creates_an_api_key(): void
    {
        $this->mockResponse([
            'id' => 'ap_000000000000',
            'token' => 'token',
        ]);

        $input = new CreateApiKeyInput(
            name: 'test',
            permission: Permission::FULL_ACCESS
        );

        $response = $this->blindpay->instances->apiKeys->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('ap_000000000000', $response->data->id);
        $this->assertEquals('token', $response->data->token);
    }

    #[Test]
    public function it_gets_an_api_key(): void
    {
        $this->mockResponse([
            'id' => 'ap_000000000000',
            'token' => 'token',
            'name' => 'test',
            'permission' => 'full_access',
            'ip_whitelist' => ['127.0.0.1'],
            'unkey_id' => 'key_123456789',
            'last_used_at' => '2024-01-01T00:00:00.000Z',
            'instance_id' => 'in_000000000000',
            'created_at' => '2021-01-01',
            'updated_at' => '2021-01-01',
        ]);

        $response = $this->blindpay->instances->apiKeys->get('ap_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertEquals('ap_000000000000', $response->data->id);
        $this->assertEquals('test', $response->data->name);
        $this->assertEquals(Permission::FULL_ACCESS, $response->data->permission);
        $this->assertCount(1, $response->data->ipWhitelist);
        $this->assertEquals('127.0.0.1', $response->data->ipWhitelist[0]);
    }

    #[Test]
    public function it_lists_api_keys(): void
    {
        $this->mockResponse([
            [
                'id' => 'ap_000000000000',
                'token' => 'token',
                'name' => 'test',
                'permission' => 'full_access',
                'ip_whitelist' => ['127.0.0.1'],
                'unkey_id' => 'key_123456789',
                'last_used_at' => '2024-01-01T00:00:00.000Z',
                'instance_id' => 'in_000000000000',
                'created_at' => '2021-01-01',
                'updated_at' => '2021-01-01',
            ],
        ]);

        $response = $this->blindpay->instances->apiKeys->list();

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertCount(1, $response->data);
        $this->assertEquals('ap_000000000000', $response->data[0]->id);
    }

    #[Test]
    public function it_deletes_an_api_key(): void
    {
        $this->mockResponse(['data' => null]);

        $response = $this->blindpay->instances->apiKeys->delete('ap_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertArrayHasKey('data', $response->data);
        $this->assertNull($response->data['data']);
    }
}
