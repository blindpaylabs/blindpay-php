<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Upload\UploadBucket;
use BlindPay\SDK\Resources\Upload\UploadInput;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class UploadTest extends TestCase
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
    public function it_uploads_a_file(): void
    {
        $this->mockResponse([
            'file_url' => 'https://storage.blindpay.com/uploads/avatar/abc123.png',
        ]);

        $input = new UploadInput(
            bucket: UploadBucket::AVATAR,
            file: 'base64encodedfile'
        );

        $response = $this->blindpay->upload->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('https://storage.blindpay.com/uploads/avatar/abc123.png', $response->data->fileUrl);
    }

    #[Test]
    public function it_uploads_with_onboarding_bucket(): void
    {
        $this->mockResponse([
            'file_url' => 'https://storage.blindpay.com/uploads/onboarding/doc123.pdf',
        ]);

        $input = new UploadInput(
            bucket: UploadBucket::ONBOARDING,
            file: 'base64encodedfile'
        );

        $response = $this->blindpay->upload->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('https://storage.blindpay.com/uploads/onboarding/doc123.pdf', $response->data->fileUrl);
    }

    #[Test]
    public function it_handles_upload_error(): void
    {
        $this->mockResponse(
            ['message' => 'File too large'],
            413
        );

        $input = new UploadInput(
            bucket: UploadBucket::LIMIT_INCREASE,
            file: 'base64encodedfile'
        );

        $response = $this->blindpay->upload->create($input);

        $this->assertFalse($response->isSuccess());
        $this->assertNotNull($response->error);
    }
}
