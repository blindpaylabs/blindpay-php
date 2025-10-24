<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Webhooks\CreateWebhookEndpointInput;
use BlindPay\SDK\Resources\Webhooks\WebhookEvents;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class WebhooksTest extends TestCase
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
    public function it_creates_a_webhook_endpoint(): void
    {
        $mockedWebhookEndpoint = [
            'id' => 'we_000000000000',
        ];

        $this->mockResponse($mockedWebhookEndpoint);

        $input = new CreateWebhookEndpointInput(
            url: 'https://example.com/webhook',
            events: [WebhookEvents::RECEIVER_NEW]
        );

        $response = $this->blindpay->instances->webhookEndpoints->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('we_000000000000', $response->data->id);
    }

    #[Test]
    public function it_lists_webhook_endpoints(): void
    {
        $mockedWebhookEndpoints = [
            [
                'id' => 'we_000000000000',
                'url' => 'https://example.com/webhook',
                'events' => ['receiver.new'],
                'last_event_at' => '2024-01-01T00:00:00.000Z',
                'instance_id' => 'in_000000000000',
                'created_at' => '2021-01-01T00:00:00Z',
                'updated_at' => '2021-01-01T00:00:00Z',
            ],
        ];

        $this->mockResponse($mockedWebhookEndpoints);

        $response = $this->blindpay->instances->webhookEndpoints->list();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(1, $response->data);
        $this->assertEquals('we_000000000000', $response->data[0]->id);
        $this->assertEquals('https://example.com/webhook', $response->data[0]->url);
        $this->assertCount(1, $response->data[0]->events);
        $this->assertEquals(WebhookEvents::RECEIVER_NEW, $response->data[0]->events[0]);
        $this->assertEquals('2024-01-01T00:00:00.000Z', $response->data[0]->lastEventAt);
        $this->assertEquals('in_000000000000', $response->data[0]->instanceId);
    }

    #[Test]
    public function it_deletes_a_webhook_endpoint(): void
    {
        $this->mockResponse(['data' => null]);

        $response = $this->blindpay->instances->webhookEndpoints->delete('we_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertArrayHasKey('data', $response->data);
        $this->assertNull($response->data['data']);
    }

    #[Test]
    public function it_gets_webhook_endpoint_secret(): void
    {
        $mockedWebhookSecret = [
            'key' => 'whsec_000000000000',
        ];

        $this->mockResponse($mockedWebhookSecret);

        $response = $this->blindpay->instances->webhookEndpoints->getSecret('we_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('whsec_000000000000', $response->data->key);
    }

    #[Test]
    public function it_gets_webhook_portal_access_url(): void
    {
        $mockedWebhookUrl = [
            'url' => 'https://example.com/webhook',
        ];

        $this->mockResponse($mockedWebhookUrl);

        $response = $this->blindpay->instances->webhookEndpoints->getPortalAccessUrl();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('https://example.com/webhook', $response->data->url);
    }
}
