<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Wallets\CreateBlockchainWalletWithAddressInput;
use BlindPay\SDK\Resources\Wallets\CreateBlockchainWalletWithHashInput;
use BlindPay\SDK\Resources\Wallets\DeleteBlockchainWalletInput;
use BlindPay\SDK\Resources\Wallets\GetBlockchainWalletInput;
use BlindPay\SDK\Types\Network;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BlockchainWalletsTest extends TestCase
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
    public function it_gets_blockchain_wallet_message(): void
    {
        $mockedMessage = [
            'message' => 'random',
        ];

        $this->mockResponse($mockedMessage);

        $response = $this->blindpay->wallets->blockchain->getMessage('re_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('random', $response->data->message);
    }

    #[Test]
    public function it_lists_blockchain_wallets(): void
    {
        $mockedWallets = [
            [
                'id' => 'bw_000000000000',
                'name' => 'Wallet Display Name',
                'network' => 'polygon',
                'address' => '0xDD6a3aD0949396e57C7738ba8FC1A46A5a1C372C',
                'signature_tx_hash' => '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359',
                'is_account_abstraction' => false,
                'receiver_id' => 're_000000000000',
            ],
        ];

        $this->mockResponse($mockedWallets);

        $response = $this->blindpay->wallets->blockchain->list('re_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(1, $response->data);
        $this->assertEquals('bw_000000000000', $response->data[0]->id);
        $this->assertEquals('Wallet Display Name', $response->data[0]->name);
        $this->assertEquals(Network::POLYGON, $response->data[0]->network);
        $this->assertEquals('0xDD6a3aD0949396e57C7738ba8FC1A46A5a1C372C', $response->data[0]->address);
        $this->assertFalse($response->data[0]->isAccountAbstraction);
    }

    #[Test]
    public function it_creates_a_blockchain_wallet_with_address(): void
    {
        $mockedWallet = [
            'id' => 'bw_000000000000',
            'name' => 'Wallet Display Name',
            'network' => 'polygon',
            'address' => '0xDD6a3aD0949396e57C7738ba8FC1A46A5a1C372C',
            'signature_tx_hash' => null,
            'is_account_abstraction' => true,
            'receiver_id' => 're_000000000000',
        ];

        $this->mockResponse($mockedWallet);

        $input = new CreateBlockchainWalletWithAddressInput(
            receiverId: 're_000000000000',
            name: 'Wallet Display Name',
            network: Network::POLYGON,
            address: '0xDD6a3aD0949396e57C7738ba8FC1A46A5a1C372C'
        );

        $response = $this->blindpay->wallets->blockchain->createWithAddress($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('bw_000000000000', $response->data->id);
        $this->assertEquals('Wallet Display Name', $response->data->name);
        $this->assertEquals(Network::POLYGON, $response->data->network);
        $this->assertEquals('0xDD6a3aD0949396e57C7738ba8FC1A46A5a1C372C', $response->data->address);
        $this->assertNull($response->data->signatureTxHash);
        $this->assertTrue($response->data->isAccountAbstraction);
    }

    #[Test]
    public function it_creates_a_blockchain_wallet_with_hash(): void
    {
        $mockedWallet = [
            'id' => 'bw_000000000000',
            'name' => 'Wallet Display Name',
            'network' => 'polygon',
            'address' => '0xDD6a3aD0949396e57C7738ba8FC1A46A5a1C372C',
            'signature_tx_hash' => '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359',
            'is_account_abstraction' => false,
            'receiver_id' => 're_000000000000',
        ];

        $this->mockResponse($mockedWallet);

        $input = new CreateBlockchainWalletWithHashInput(
            receiverId: 're_000000000000',
            name: 'Wallet Display Name',
            network: Network::POLYGON,
            signatureTxHash: '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359'
        );

        $response = $this->blindpay->wallets->blockchain->createWithHash($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('bw_000000000000', $response->data->id);
        $this->assertEquals('Wallet Display Name', $response->data->name);
        $this->assertEquals(Network::POLYGON, $response->data->network);
        $this->assertEquals('0xDD6a3aD0949396e57C7738ba8FC1A46A5a1C372C', $response->data->address);
        $this->assertEquals('0x3c499c542cef5e3811e1192ce70d8cc03d5c3359', $response->data->signatureTxHash);
        $this->assertFalse($response->data->isAccountAbstraction);
    }

    #[Test]
    public function it_gets_a_blockchain_wallet(): void
    {
        $mockedWallet = [
            'id' => 'bw_000000000000',
            'name' => 'Wallet Display Name',
            'network' => 'polygon',
            'address' => '0xDD6a3aD0949396e57C7738ba8FC1A46A5a1C372C',
            'signature_tx_hash' => '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359',
            'is_account_abstraction' => false,
            'receiver_id' => 're_000000000000',
        ];

        $this->mockResponse($mockedWallet);

        $input = new GetBlockchainWalletInput(
            receiverId: 're_000000000000',
            id: 'bw_000000000000'
        );

        $response = $this->blindpay->wallets->blockchain->get($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('bw_000000000000', $response->data->id);
        $this->assertEquals('Wallet Display Name', $response->data->name);
        $this->assertEquals(Network::POLYGON, $response->data->network);
        $this->assertEquals('0xDD6a3aD0949396e57C7738ba8FC1A46A5a1C372C', $response->data->address);
    }

    #[Test]
    public function it_deletes_a_blockchain_wallet(): void
    {
        $this->mockResponse(['data' => null]);

        $input = new DeleteBlockchainWalletInput(
            receiverId: 're_000000000000',
            id: 'bw_000000000000'
        );

        $response = $this->blindpay->wallets->blockchain->delete($input);

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertArrayHasKey('data', $response->data);
        $this->assertNull($response->data['data']);
    }
}
