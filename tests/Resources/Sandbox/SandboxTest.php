<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Sandbox\CreateSandboxInput;
use BlindPay\SDK\Resources\Sandbox\SandboxItem;
use BlindPay\SDK\Resources\Sandbox\SandboxStatus;
use BlindPay\SDK\Resources\Sandbox\UpdateSandboxInput;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SandboxTest extends TestCase
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
    public function it_lists_sandbox_items(): void
    {
        $this->mockResponse([
            [
                'id' => 'sb_000000000001',
                'name' => 'My Sandbox Item',
                'status' => 'active',
                'metadata' => ['key' => 'value'],
                'created_at' => '2021-01-01T00:00:00Z',
                'updated_at' => '2021-01-01T00:00:00Z',
            ],
            [
                'id' => 'sb_000000000002',
                'name' => 'Another Item',
                'status' => 'inactive',
            ],
        ]);

        $response = $this->blindpay->sandbox->list();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(2, $response->data);

        /** @var SandboxItem $first */
        $first = $response->data[0];
        $this->assertInstanceOf(SandboxItem::class, $first);
        $this->assertSame('sb_000000000001', $first->id);
        $this->assertSame('My Sandbox Item', $first->name);
        $this->assertSame(SandboxStatus::ACTIVE, $first->status);
        $this->assertSame(['key' => 'value'], $first->metadata);
        $this->assertNotNull($first->createdAt);
        $this->assertNotNull($first->updatedAt);

        /** @var SandboxItem $second */
        $second = $response->data[1];
        $this->assertSame(SandboxStatus::INACTIVE, $second->status);
        $this->assertNull($second->metadata);
        $this->assertNull($second->createdAt);
        $this->assertNull($second->updatedAt);
    }

    #[Test]
    public function it_gets_a_sandbox_item(): void
    {
        $this->mockResponse([
            'id' => 'sb_000000000001',
            'name' => 'My Sandbox Item',
            'status' => 'active',
            'metadata' => ['key' => 'value'],
            'created_at' => '2021-01-01T00:00:00Z',
            'updated_at' => '2021-01-01T00:00:00Z',
        ]);

        $response = $this->blindpay->sandbox->get('sb_000000000001');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertInstanceOf(SandboxItem::class, $response->data);
        $this->assertSame('sb_000000000001', $response->data->id);
        $this->assertSame('My Sandbox Item', $response->data->name);
        $this->assertSame(SandboxStatus::ACTIVE, $response->data->status);
    }

    #[Test]
    public function it_returns_error_when_get_id_is_empty(): void
    {
        $response = $this->blindpay->sandbox->get('');

        $this->assertFalse($response->isSuccess());
        $this->assertNotNull($response->error);
        $this->assertSame('Sandbox ID cannot be empty', $response->error->message);
    }

    #[Test]
    public function it_creates_a_sandbox_item(): void
    {
        $this->mockResponse([
            'id' => 'sb_000000000001',
            'name' => 'My Sandbox Item',
            'status' => 'active',
            'metadata' => ['key' => 'value'],
        ]);

        $input = new CreateSandboxInput(
            name: 'My Sandbox Item',
            metadata: ['key' => 'value']
        );

        $response = $this->blindpay->sandbox->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertInstanceOf(SandboxItem::class, $response->data);
        $this->assertSame('sb_000000000001', $response->data->id);
        $this->assertSame('My Sandbox Item', $response->data->name);
        $this->assertSame(SandboxStatus::ACTIVE, $response->data->status);
        $this->assertSame(['key' => 'value'], $response->data->metadata);
    }

    #[Test]
    public function it_creates_a_sandbox_item_without_metadata(): void
    {
        $this->mockResponse([
            'id' => 'sb_000000000001',
            'name' => 'My Sandbox Item',
            'status' => 'active',
        ]);

        $input = new CreateSandboxInput(name: 'My Sandbox Item');

        $response = $this->blindpay->sandbox->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertInstanceOf(SandboxItem::class, $response->data);
        $this->assertNull($response->data->metadata);
    }

    #[Test]
    public function it_updates_a_sandbox_item(): void
    {
        $this->mockResponse([
            'id' => 'sb_000000000001',
            'name' => 'Updated Name',
            'status' => 'inactive',
            'created_at' => '2021-01-01T00:00:00Z',
            'updated_at' => '2021-06-01T00:00:00Z',
        ]);

        $input = new UpdateSandboxInput(name: 'Updated Name');

        $response = $this->blindpay->sandbox->update('sb_000000000001', $input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertInstanceOf(SandboxItem::class, $response->data);
        $this->assertSame('Updated Name', $response->data->name);
        $this->assertSame(SandboxStatus::INACTIVE, $response->data->status);
    }

    #[Test]
    public function it_returns_error_when_update_id_is_empty(): void
    {
        $input = new UpdateSandboxInput(name: 'Updated Name');
        $response = $this->blindpay->sandbox->update('', $input);

        $this->assertFalse($response->isSuccess());
        $this->assertNotNull($response->error);
        $this->assertSame('Sandbox ID cannot be empty', $response->error->message);
    }

    #[Test]
    public function it_deletes_a_sandbox_item(): void
    {
        $this->mockResponse(['success' => true]);

        $response = $this->blindpay->sandbox->delete('sb_000000000001');

        $this->assertTrue($response->isSuccess());
    }

    #[Test]
    public function it_returns_error_when_delete_id_is_empty(): void
    {
        $response = $this->blindpay->sandbox->delete('');

        $this->assertFalse($response->isSuccess());
        $this->assertNotNull($response->error);
        $this->assertSame('Sandbox ID cannot be empty', $response->error->message);
    }
}
