<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\VirtualAccounts\CreateVirtualAccountInput;
use BlindPay\SDK\Resources\VirtualAccounts\UpdateVirtualAccountInput;
use BlindPay\SDK\Types\StablecoinToken;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class VirtualAccountsTest extends TestCase
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
    public function it_updates_a_virtual_account(): void
    {
        $this->mockResponse(['data' => null]);

        $input = new UpdateVirtualAccountInput(
            receiverId: 're_000000000000',
            blockchainWalletId: 'bw_000000000000',
            token: StablecoinToken::USDC
        );

        $response = $this->blindpay->virtualAccounts->update($input);

        $this->assertTrue($response->isSuccess());
        $this->assertIsArray($response->data);
        $this->assertArrayHasKey('data', $response->data);
        $this->assertNull($response->data['data']);
    }

    #[Test]
    public function it_creates_a_virtual_account(): void
    {
        $mockedVirtualAccount = [
            'id' => 'va_000000000000',
            'us' => [
                'ach' => [
                    'routing_number' => '123456789',
                    'account_number' => '123456789',
                ],
                'wire' => [
                    'routing_number' => '123456789',
                    'account_number' => '123456789',
                ],
                'rtp' => [
                    'routing_number' => '123456789',
                    'account_number' => '123456789',
                ],
                'swift_bic_code' => 'CHASUS33',
                'account_type' => 'Business checking',
                'beneficiary' => [
                    'name' => 'Receiver Name',
                    'address_line_1' => '8 The Green, #19364',
                    'address_line_2' => 'Dover, DE 19901',
                ],
                'receiving_bank' => [
                    'name' => 'JPMorgan Chase',
                    'address_line_1' => '270 Park Ave',
                    'address_line_2' => 'New York, NY, 10017-2070',
                ],
            ],
            'token' => 'USDC',
            'blockchain_wallet_id' => 'bw_000000000000',
        ];

        $this->mockResponse($mockedVirtualAccount);

        $input = new CreateVirtualAccountInput(
            receiverId: 're_000000000000',
            blockchainWalletId: 'bw_000000000000',
            token: StablecoinToken::USDC
        );

        $response = $this->blindpay->virtualAccounts->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('va_000000000000', $response->data->id);
        $this->assertEquals(StablecoinToken::USDC, $response->data->token);
        $this->assertEquals('bw_000000000000', $response->data->blockchainWalletId);
        $this->assertEquals('123456789', $response->data->us->ach->routingNumber);
        $this->assertEquals('123456789', $response->data->us->ach->accountNumber);
        $this->assertEquals('123456789', $response->data->us->wire->routingNumber);
        $this->assertEquals('123456789', $response->data->us->wire->accountNumber);
        $this->assertEquals('123456789', $response->data->us->rtp->routingNumber);
        $this->assertEquals('123456789', $response->data->us->rtp->accountNumber);
        $this->assertEquals('CHASUS33', $response->data->us->swiftBicCode);
        $this->assertEquals('Business checking', $response->data->us->accountType);
        $this->assertEquals('Receiver Name', $response->data->us->beneficiary->name);
        $this->assertEquals('8 The Green, #19364', $response->data->us->beneficiary->addressLine1);
        $this->assertEquals('Dover, DE 19901', $response->data->us->beneficiary->addressLine2);
        $this->assertEquals('JPMorgan Chase', $response->data->us->receivingBank->name);
        $this->assertEquals('270 Park Ave', $response->data->us->receivingBank->addressLine1);
        $this->assertEquals('New York, NY, 10017-2070', $response->data->us->receivingBank->addressLine2);
    }

    #[Test]
    public function it_gets_a_virtual_account(): void
    {
        $mockedVirtualAccount = [
            'id' => 'va_000000000000',
            'us' => [
                'ach' => [
                    'routing_number' => '123456789',
                    'account_number' => '123456789',
                ],
                'wire' => [
                    'routing_number' => '123456789',
                    'account_number' => '123456789',
                ],
                'rtp' => [
                    'routing_number' => '123456789',
                    'account_number' => '123456789',
                ],
                'swift_bic_code' => 'CHASUS33',
                'account_type' => 'Business checking',
                'beneficiary' => [
                    'name' => 'Receiver Name',
                    'address_line_1' => '8 The Green, #19364',
                    'address_line_2' => 'Dover, DE 19901',
                ],
                'receiving_bank' => [
                    'name' => 'JPMorgan Chase',
                    'address_line_1' => '270 Park Ave',
                    'address_line_2' => 'New York, NY, 10017-2070',
                ],
            ],
            'token' => 'USDC',
            'blockchain_wallet_id' => 'bw_000000000000',
        ];

        $this->mockResponse($mockedVirtualAccount);

        $response = $this->blindpay->virtualAccounts->get('re_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('va_000000000000', $response->data->id);
        $this->assertEquals(StablecoinToken::USDC, $response->data->token);
        $this->assertEquals('bw_000000000000', $response->data->blockchainWalletId);
        $this->assertEquals('123456789', $response->data->us->ach->routingNumber);
        $this->assertEquals('123456789', $response->data->us->ach->accountNumber);
        $this->assertEquals('123456789', $response->data->us->wire->routingNumber);
        $this->assertEquals('123456789', $response->data->us->wire->accountNumber);
        $this->assertEquals('123456789', $response->data->us->rtp->routingNumber);
        $this->assertEquals('123456789', $response->data->us->rtp->accountNumber);
        $this->assertEquals('CHASUS33', $response->data->us->swiftBicCode);
        $this->assertEquals('Business checking', $response->data->us->accountType);
        $this->assertEquals('Receiver Name', $response->data->us->beneficiary->name);
        $this->assertEquals('8 The Green, #19364', $response->data->us->beneficiary->addressLine1);
        $this->assertEquals('Dover, DE 19901', $response->data->us->beneficiary->addressLine2);
        $this->assertEquals('JPMorgan Chase', $response->data->us->receivingBank->name);
        $this->assertEquals('270 Park Ave', $response->data->us->receivingBank->addressLine1);
        $this->assertEquals('New York, NY, 10017-2070', $response->data->us->receivingBank->addressLine2);
    }
}
