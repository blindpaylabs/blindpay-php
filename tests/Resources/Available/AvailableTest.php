<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Types\Rail;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AvailableTest extends TestCase
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
    public function it_gets_bank_details_for_a_rail(): void
    {
        $mockedBankDetails = [
            [
                'label' => 'Account Type',
                'regex' => '',
                'key' => 'account_type',
                'items' => [
                    [
                        'label' => 'Checking',
                        'value' => 'checking',
                    ],
                    [
                        'label' => 'Savings',
                        'value' => 'saving',
                    ],
                ],
                'required' => true,
            ],
        ];

        $this->mockResponse($mockedBankDetails);

        $response = $this->blindpay->available->getBankDetails(Rail::PIX);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(1, $response->data);
        $this->assertEquals('Account Type', $response->data[0]->label);
        $this->assertEquals('', $response->data[0]->regex);
        $this->assertEquals('account_type', $response->data[0]->key);
        $this->assertTrue($response->data[0]->required);
        $this->assertIsArray($response->data[0]->items);
        $this->assertCount(2, $response->data[0]->items);
        $this->assertEquals('Checking', $response->data[0]->items[0]->label);
        $this->assertEquals('checking', $response->data[0]->items[0]->value);
        $this->assertEquals('Savings', $response->data[0]->items[1]->label);
        $this->assertEquals('saving', $response->data[0]->items[1]->value);
    }

    #[Test]
    public function it_gets_available_rails(): void
    {
        $mockedRails = [
            [
                'label' => 'Domestic Wire',
                'value' => 'wire',
                'country' => 'US',
            ],
            [
                'label' => 'ACH',
                'value' => 'ach',
                'country' => 'US',
            ],
            [
                'label' => 'PIX',
                'value' => 'pix',
                'country' => 'BR',
            ],
            [
                'label' => 'SPEI',
                'value' => 'spei_bitso',
                'country' => 'MX',
            ],
            [
                'label' => 'Transfers 3.0',
                'value' => 'transfers_bitso',
                'country' => 'AR',
            ],
            [
                'label' => 'ACH Colombia',
                'value' => 'ach_cop_bitso',
                'country' => 'CO',
            ],
            [
                'label' => 'International Swift',
                'value' => 'international_swift',
                'country' => 'US',
            ],
            [
                'label' => 'RTP',
                'value' => 'rtp',
                'country' => 'US',
            ],
        ];

        $this->mockResponse($mockedRails);

        $response = $this->blindpay->available->getRails();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(8, $response->data);
        $this->assertEquals('Domestic Wire', $response->data[0]->label);
        $this->assertEquals('wire', $response->data[0]->value);
        $this->assertEquals('US', $response->data[0]->country);
        $this->assertEquals('ACH', $response->data[1]->label);
        $this->assertEquals('ach', $response->data[1]->value);
        $this->assertEquals('US', $response->data[1]->country);
        $this->assertEquals('PIX', $response->data[2]->label);
        $this->assertEquals('pix', $response->data[2]->value);
        $this->assertEquals('BR', $response->data[2]->country);
        $this->assertEquals('SPEI', $response->data[3]->label);
        $this->assertEquals('spei_bitso', $response->data[3]->value);
        $this->assertEquals('MX', $response->data[3]->country);
        $this->assertEquals('Transfers 3.0', $response->data[4]->label);
        $this->assertEquals('transfers_bitso', $response->data[4]->value);
        $this->assertEquals('AR', $response->data[4]->country);
        $this->assertEquals('ACH Colombia', $response->data[5]->label);
        $this->assertEquals('ach_cop_bitso', $response->data[5]->value);
        $this->assertEquals('CO', $response->data[5]->country);
        $this->assertEquals('International Swift', $response->data[6]->label);
        $this->assertEquals('international_swift', $response->data[6]->value);
        $this->assertEquals('US', $response->data[6]->country);
        $this->assertEquals('RTP', $response->data[7]->label);
        $this->assertEquals('rtp', $response->data[7]->value);
        $this->assertEquals('US', $response->data[7]->country);
    }
}
