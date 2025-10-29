<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\PartnerFees\CreatePartnerFeeInput;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PartnerFeesTest extends TestCase
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
    public function it_lists_partner_fees(): void
    {
        $mockedList = [
            [
                'id' => 'fe_000000000000',
                'instance_id' => 'in_000000000000',
                'name' => 'Display Name',
                'payout_percentage_fee' => 0,
                'payout_flat_fee' => 0,
                'payin_percentage_fee' => 0,
                'payin_flat_fee' => 0,
                'evm_wallet_address' => '0x1234567890123456789012345678901234567890',
                'stellar_wallet_address' => 'GAB22222222222222222222222222222222222222222222222222222222222222',
            ],
        ];

        $this->mockResponse($mockedList);

        $response = $this->blindpay->partnerFees->list();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(1, $response->data);
        $this->assertEquals('fe_000000000000', $response->data[0]->id);
        $this->assertEquals('in_000000000000', $response->data[0]->instanceId);
        $this->assertEquals('Display Name', $response->data[0]->name);
        $this->assertEquals(0.0, $response->data[0]->payoutPercentageFee);
        $this->assertEquals(0.0, $response->data[0]->payoutFlatFee);
        $this->assertEquals(0.0, $response->data[0]->payinPercentageFee);
        $this->assertEquals(0.0, $response->data[0]->payinFlatFee);
        $this->assertEquals('0x1234567890123456789012345678901234567890', $response->data[0]->evmWalletAddress);
        $this->assertEquals('GAB22222222222222222222222222222222222222222222222222222222222222', $response->data[0]->stellarWalletAddress);
    }

    #[Test]
    public function it_creates_a_partner_fee(): void
    {
        $mockPartnerFee = [
            'id' => 'fe_000000000000',
            'instance_id' => 'in_000000000000',
            'name' => 'Display Name',
            'payout_percentage_fee' => 0,
            'payout_flat_fee' => 0,
            'payin_percentage_fee' => 0,
            'payin_flat_fee' => 0,
            'evm_wallet_address' => '0x1234567890123456789012345678901234567890',
            'stellar_wallet_address' => 'GAB22222222222222222222222222222222222222222222222222222222222222',
        ];

        $this->mockResponse($mockPartnerFee);

        $input = new CreatePartnerFeeInput(
            evmWalletAddress: '0x1234567890123456789012345678901234567890',
            name: 'Display Name',
            payinFlatFee: 0,
            payinPercentageFee: 0,
            payoutFlatFee: 0,
            payoutPercentageFee: 0,
            stellarWalletAddress: 'GAB22222222222222222222222222222222222222222222222222222222222222'
        );

        $response = $this->blindpay->partnerFees->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('fe_000000000000', $response->data->id);
        $this->assertEquals('in_000000000000', $response->data->instanceId);
        $this->assertEquals('Display Name', $response->data->name);
        $this->assertEquals(0.0, $response->data->payoutPercentageFee);
        $this->assertEquals(0.0, $response->data->payoutFlatFee);
        $this->assertEquals(0.0, $response->data->payinPercentageFee);
        $this->assertEquals(0.0, $response->data->payinFlatFee);
        $this->assertEquals('0x1234567890123456789012345678901234567890', $response->data->evmWalletAddress);
        $this->assertEquals('GAB22222222222222222222222222222222222222222222222222222222222222', $response->data->stellarWalletAddress);
    }

    #[Test]
    public function it_gets_a_partner_fee(): void
    {
        $mockedFee = [
            'id' => 'fe_000000000000',
            'instance_id' => 'in_000000000000',
            'name' => 'Display Name',
            'payout_percentage_fee' => 0,
            'payout_flat_fee' => 0,
            'payin_percentage_fee' => 0,
            'payin_flat_fee' => 0,
            'evm_wallet_address' => '0x1234567890123456789012345678901234567890',
            'stellar_wallet_address' => 'GAB22222222222222222222222222222222222222222222222222222222222222',
        ];

        $this->mockResponse($mockedFee);

        $response = $this->blindpay->partnerFees->get('fe_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('fe_000000000000', $response->data->id);
        $this->assertEquals('in_000000000000', $response->data->instanceId);
        $this->assertEquals('Display Name', $response->data->name);
        $this->assertEquals(0.0, $response->data->payoutPercentageFee);
        $this->assertEquals(0.0, $response->data->payoutFlatFee);
        $this->assertEquals(0.0, $response->data->payinPercentageFee);
        $this->assertEquals(0.0, $response->data->payinFlatFee);
        $this->assertEquals('0x1234567890123456789012345678901234567890', $response->data->evmWalletAddress);
        $this->assertEquals('GAB22222222222222222222222222222222222222222222222222222222222222', $response->data->stellarWalletAddress);
    }

    #[Test]
    public function it_deletes_a_partner_fee(): void
    {
        $this->mockResponse(['data' => null]);

        $response = $this->blindpay->partnerFees->delete('fe_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertArrayHasKey('data', $response->data);
        $this->assertNull($response->data['data']);
    }
}
