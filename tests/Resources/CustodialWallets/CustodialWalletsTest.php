<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\CustodialWallets\CreateCustodialWalletInput;
use BlindPay\SDK\Resources\CustodialWallets\DeleteCustodialWalletInput;
use BlindPay\SDK\Resources\CustodialWallets\GetCustodialWalletInput;
use BlindPay\SDK\Types\Network;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CustodialWalletsTest extends TestCase
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
    public function it_creates_a_custodial_wallet(): void
    {
        $mockedWallet = [
            'id' => 'cw_000000000000',
            'receiver_id' => 're_000000000000',
            'instance_id' => 'in_000000000000',
            'network' => 'solana',
            'address' => 'So1ana1234567890',
            'created_at' => '2025-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedWallet);

        $input = new CreateCustodialWalletInput(
            receiverId: 're_000000000000',
            network: Network::SOLANA
        );

        $response = $this->blindpay->wallets->custodial->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('cw_000000000000', $response->data->id);
        $this->assertEquals('re_000000000000', $response->data->receiverId);
        $this->assertEquals(Network::SOLANA, $response->data->network);
        $this->assertEquals('So1ana1234567890', $response->data->address);
    }

    #[Test]
    public function it_lists_custodial_wallets(): void
    {
        $mockedWallets = [
            [
                'id' => 'cw_000000000000',
                'receiver_id' => 're_000000000000',
                'instance_id' => 'in_000000000000',
                'network' => 'solana',
                'address' => 'So1ana1234567890',
                'created_at' => '2025-01-01T00:00:00Z',
            ],
        ];

        $this->mockResponse($mockedWallets);

        $response = $this->blindpay->wallets->custodial->list('re_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(1, $response->data);
        $this->assertEquals('cw_000000000000', $response->data[0]->id);
    }

    #[Test]
    public function it_gets_a_custodial_wallet(): void
    {
        $mockedWallet = [
            'id' => 'cw_000000000000',
            'receiver_id' => 're_000000000000',
            'instance_id' => 'in_000000000000',
            'network' => 'solana',
            'address' => 'So1ana1234567890',
            'created_at' => '2025-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedWallet);

        $input = new GetCustodialWalletInput(
            receiverId: 're_000000000000',
            id: 'cw_000000000000'
        );

        $response = $this->blindpay->wallets->custodial->get($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('cw_000000000000', $response->data->id);
    }

    #[Test]
    public function it_gets_custodial_wallet_balance(): void
    {
        $mockedBalance = [
            'USDC' => [
                'amount' => 150.50,
                'token' => 'USDC',
                'address' => 'So1ana1234567890',
            ],
            'USDT' => [
                'amount' => 0.0,
                'token' => 'USDT',
                'address' => 'So1ana1234567890',
            ],
            'USDB' => null,
        ];

        $this->mockResponse($mockedBalance);

        $input = new GetCustodialWalletInput(
            receiverId: 're_000000000000',
            id: 'cw_000000000000'
        );

        $response = $this->blindpay->wallets->custodial->getBalance($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertNotNull($response->data->usdc);
        $this->assertEquals(150.50, $response->data->usdc->amount);
        $this->assertEquals('USDC', $response->data->usdc->token);
        $this->assertNotNull($response->data->usdt);
        $this->assertEquals(0.0, $response->data->usdt->amount);
        $this->assertNull($response->data->usdb);
    }

    #[Test]
    public function it_deletes_a_custodial_wallet(): void
    {
        $this->mockResponse(['data' => null]);

        $input = new DeleteCustodialWalletInput(
            receiverId: 're_000000000000',
            id: 'cw_000000000000'
        );

        $response = $this->blindpay->wallets->custodial->delete($input);

        $this->assertTrue($response->isSuccess());
    }
}
