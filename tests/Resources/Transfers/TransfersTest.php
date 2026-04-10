<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Transfers\CreateTransferInput;
use BlindPay\SDK\Resources\Transfers\CreateTransferQuoteInput;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TransfersTest extends TestCase
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
    public function it_creates_a_transfer_quote(): void
    {
        $mockedQuote = [
            'id' => 'tq_000000000000',
            'amount' => 100.00,
            'currency' => 'USD',
            'fee_amount' => 1.50,
            'source_wallet_id' => 'cw_source',
            'destination_wallet_id' => 'cw_dest',
        ];

        $this->mockResponse($mockedQuote);

        $input = new CreateTransferQuoteInput(
            sourceWalletId: 'cw_source',
            destinationWalletId: 'cw_dest',
            amount: 100.00
        );

        $response = $this->blindpay->transfers->createQuote($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('tq_000000000000', $response->data->id);
        $this->assertEquals(100.00, $response->data->amount);
        $this->assertEquals(1.50, $response->data->feeAmount);
        $this->assertEquals('cw_source', $response->data->sourceWalletId);
        $this->assertEquals('cw_dest', $response->data->destinationWalletId);
    }

    #[Test]
    public function it_creates_a_transfer(): void
    {
        $mockedTransfer = [
            'id' => 'tr_000000000000',
            'instance_id' => 'in_000000000000',
            'status' => 'processing',
            'quote_id' => 'tq_000000000000',
            'source_wallet_id' => 'cw_source',
            'destination_wallet_id' => 'cw_dest',
            'amount' => 100.00,
            'currency' => 'USD',
            'tracking_transaction' => ['status' => 'processing', 'date' => '2025-01-01T00:00:00Z'],
            'tracking_transaction_monitoring' => ['status' => 'processing', 'date' => null],
            'tracking_complete' => ['status' => 'processing', 'date' => null],
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedTransfer);

        $input = new CreateTransferInput(quoteId: 'tq_000000000000');

        $response = $this->blindpay->transfers->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('tr_000000000000', $response->data->id);
        $this->assertEquals('processing', $response->data->status->value);
        $this->assertEquals('tq_000000000000', $response->data->quoteId);
        $this->assertEquals(100.00, $response->data->amount);
    }

    #[Test]
    public function it_gets_a_transfer(): void
    {
        $mockedTransfer = [
            'id' => 'tr_000000000000',
            'instance_id' => 'in_000000000000',
            'status' => 'completed',
            'quote_id' => 'tq_000000000000',
            'source_wallet_id' => 'cw_source',
            'destination_wallet_id' => 'cw_dest',
            'amount' => 100.00,
            'currency' => 'USD',
            'tracking_transaction' => ['status' => 'completed', 'date' => '2025-01-01T00:00:00Z'],
            'tracking_transaction_monitoring' => ['status' => 'completed', 'date' => '2025-01-01T00:00:00Z'],
            'tracking_complete' => ['status' => 'completed', 'date' => '2025-01-01T00:00:00Z'],
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
        ];

        $this->mockResponse($mockedTransfer);

        $response = $this->blindpay->transfers->get('tr_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('tr_000000000000', $response->data->id);
        $this->assertEquals('completed', $response->data->status->value);
    }

    #[Test]
    public function it_lists_transfers(): void
    {
        $mockedTransfers = [
            'data' => [
                [
                    'id' => 'tr_000000000000',
                    'instance_id' => 'in_000000000000',
                    'status' => 'completed',
                    'quote_id' => 'tq_000000000000',
                    'source_wallet_id' => 'cw_source',
                    'destination_wallet_id' => 'cw_dest',
                    'amount' => 100.00,
                    'currency' => 'USD',
                    'tracking_transaction' => ['status' => 'completed', 'date' => '2025-01-01T00:00:00Z'],
                    'tracking_transaction_monitoring' => ['status' => 'completed', 'date' => '2025-01-01T00:00:00Z'],
                    'tracking_complete' => ['status' => 'completed', 'date' => '2025-01-01T00:00:00Z'],
                    'created_at' => '2025-01-01T00:00:00Z',
                    'updated_at' => '2025-01-01T00:00:00Z',
                ],
            ],
            'pagination' => [
                'has_more' => false,
                'next_page' => null,
                'prev_page' => null,
            ],
        ];

        $this->mockResponse($mockedTransfers);

        $response = $this->blindpay->transfers->list();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data->data);
        $this->assertCount(1, $response->data->data);
        $this->assertEquals('tr_000000000000', $response->data->data[0]->id);
    }
}
