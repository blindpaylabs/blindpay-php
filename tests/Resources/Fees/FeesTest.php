<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class FeesTest extends TestCase
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
    public function it_gets_fees(): void
    {
        $mockedFees = [
            'ach' => [
                'payin_flat' => 0.0,
                'payin_percentage' => 0.0,
                'payout_flat' => 5.0,
                'payout_percentage' => 0.0,
            ],
            'domestic_wire' => [
                'payin_flat' => 0.0,
                'payin_percentage' => 0.0,
                'payout_flat' => 25.0,
                'payout_percentage' => 0.0,
            ],
            'pix' => [
                'payin_flat' => 0.0,
                'payin_percentage' => 0.0,
                'payout_flat' => 3.0,
                'payout_percentage' => 0.5,
            ],
            'solana' => null,
        ];

        $this->mockResponse($mockedFees);

        $response = $this->blindpay->fees->get();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertNotNull($response->data->ach);
        $this->assertEquals(5.0, $response->data->ach->payoutFlat);
        $this->assertEquals(0.0, $response->data->ach->payoutPercentage);
        $this->assertNotNull($response->data->domesticWire);
        $this->assertEquals(25.0, $response->data->domesticWire->payoutFlat);
        $this->assertNotNull($response->data->pix);
        $this->assertEquals(3.0, $response->data->pix->payoutFlat);
        $this->assertEquals(0.5, $response->data->pix->payoutPercentage);
        $this->assertNull($response->data->solana);
    }
}
