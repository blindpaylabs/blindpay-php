<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Instances\InstanceMemberRole;
use BlindPay\SDK\Resources\Instances\UpdateInstanceInput;
use BlindPay\SDK\Resources\Instances\UpdateMemberRoleInput;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class InstancesTest extends TestCase
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
    public function it_gets_instance_members(): void
    {
        $mockedMembers = [
            [
                'id' => 'us_000000000000',
                'email' => 'email@example.com',
                'first_name' => 'Harry',
                'middle_name' => 'James',
                'last_name' => 'Potter',
                'image_url' => 'https://example.com/image.png',
                'created_at' => '2021-01-01T00:00:00Z',
                'role' => 'admin',
            ],
        ];

        $this->mockResponse($mockedMembers);

        $response = $this->blindpay->instances->getMembers();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(1, $response->data);
        $this->assertEquals('us_000000000000', $response->data[0]->id);
        $this->assertEquals('email@example.com', $response->data[0]->email);
        $this->assertEquals('Harry', $response->data[0]->firstName);
        $this->assertEquals('James', $response->data[0]->middleName);
        $this->assertEquals('Potter', $response->data[0]->lastName);
        $this->assertEquals('https://example.com/image.png', $response->data[0]->imageUrl);
        $this->assertEquals(InstanceMemberRole::ADMIN, $response->data[0]->role);
    }

    #[Test]
    public function it_updates_an_instance(): void
    {
        $this->mockResponse(['data' => null]);

        $input = new UpdateInstanceInput(
            name: 'New Instance Name'
        );

        $response = $this->blindpay->instances->update($input);

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertArrayHasKey('data', $response->data);
        $this->assertNull($response->data['data']);
    }

    #[Test]
    public function it_deletes_an_instance(): void
    {
        $this->mockResponse(['data' => null]);

        $response = $this->blindpay->instances->delete();

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertArrayHasKey('data', $response->data);
        $this->assertNull($response->data['data']);
    }

    #[Test]
    public function it_deletes_an_instance_member(): void
    {
        $this->mockResponse(['data' => null]);

        $response = $this->blindpay->instances->deleteMember('us_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertArrayHasKey('data', $response->data);
        $this->assertNull($response->data['data']);
    }

    #[Test]
    public function it_updates_instance_member_role(): void
    {
        $this->mockResponse(['data' => null]);

        $input = new UpdateMemberRoleInput(
            memberId: 'us_000000000000',
            role: InstanceMemberRole::CHECKER
        );

        $response = $this->blindpay->instances->updateMemberRole($input);

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertArrayHasKey('data', $response->data);
        $this->assertNull($response->data['data']);
    }
}
