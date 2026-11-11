<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Transfers\CreateTransferInput;
use BlindPay\SDK\Resources\Transfers\CreateTransferQuoteInput;
use BlindPay\SDK\Resources\Transfers\CreateTransferResponse;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TransactionStatus;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransfersTest extends TestCase
{
    private BlindPay $blindpay;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler;

        $this->blindpay = new BlindPay(
            apiKey: 'test-key',
            instanceId: 'in_000000000000',
            handler: $this->mockHandler,
        );
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

    private function mockTransferData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'tr_000000000000',
            'instance_id' => 'in_000000000000',
            'status' => 'processing',
            'transfer_quote_id' => 'tq_000000000000',
            'wallet_id' => 'wl_000000000000',
            'sender_token' => 'USDC',
            'sender_amount' => 100.00,
            'receiver_amount' => 98.50,
            'customer_token' => 'USDT',
            'customer_network' => 'base',
            'customer_wallet_address' => '0xabc123',
            'customer_id' => 'rc_000000000000',
            'address' => '0xabc123',
            'network' => 'base',
            'tracking_transaction_monitoring' => ['step' => 'pending', 'completed_at' => null],
            'tracking_paymaster' => ['step' => 'pending', 'completed_at' => null],
            'tracking_bridge_swap' => ['step' => 'pending', 'completed_at' => null],
            'tracking_complete' => ['step' => 'pending', 'status' => 'pending', 'transaction_hash' => '', 'completed_at' => null],
            'tracking_partner_fee' => ['step' => 'pending', 'transaction_hash' => '', 'completed_at' => null],
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
        ], $overrides);
    }

    #[Test]
    public function it_creates_a_transfer_quote(): void
    {
        $mockedQuote = [
            'id' => 'tq_000000000000',
            'receiver_amount' => 98.50,
            'sender_amount' => 100.00,
            'flat_fee' => 1.50,
            'expires_at' => 1700000000,
            'commercial_quotation' => 1.0,
            'blindpay_quotation' => 1.0,
            'partner_fee_amount' => null,
        ];

        $this->mockResponse($mockedQuote);

        $input = new CreateTransferQuoteInput(
            walletId: 'wl_000000000000',
            senderToken: StablecoinToken::USDC,
            customerWalletAddress: '0xabc123',
            customerToken: StablecoinToken::USDT,
            customerNetwork: Network::BASE,
            requestAmount: 100,
            amountReference: CurrencyType::SENDER
        );

        $response = $this->blindpay->transfers->createQuote($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('tq_000000000000', $response->data->id);
        $this->assertEquals(98.50, $response->data->receiverAmount);
        $this->assertEquals(100.00, $response->data->senderAmount);
        $this->assertEquals(1.50, $response->data->flatFee);
        $this->assertEquals(1700000000, $response->data->expiresAt);
        $this->assertEquals(1.0, $response->data->commercialQuotation);
        $this->assertEquals(1.0, $response->data->blindpayQuotation);
        $this->assertNull($response->data->partnerFeeAmount);
    }

    #[Test]
    public function it_creates_a_transfer_quote_with_optional_fields(): void
    {
        $mockedQuote = [
            'id' => 'tq_000000000000',
            'receiver_amount' => 98.50,
            'sender_amount' => 100.00,
            'flat_fee' => 1.50,
            'expires_at' => null,
            'commercial_quotation' => null,
            'blindpay_quotation' => null,
            'partner_fee_amount' => 2.00,
        ];

        $this->mockResponse($mockedQuote);

        $input = new CreateTransferQuoteInput(
            walletId: 'wl_000000000000',
            senderToken: StablecoinToken::USDC,
            customerWalletAddress: '0xabc123',
            customerToken: StablecoinToken::USDT,
            customerNetwork: Network::BASE,
            requestAmount: 100,
            amountReference: CurrencyType::SENDER,
            coverFees: true,
            partnerFeeId: 'pf_000000000000'
        );

        $response = $this->blindpay->transfers->createQuote($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('tq_000000000000', $response->data->id);
        $this->assertEquals(2.00, $response->data->partnerFeeAmount);
    }

    #[Test]
    public function it_creates_a_transfer(): void
    {
        // POST /transfers returns CreateTransferOut (id, status, tracking_*), not the full TransferOut.
        $this->mockResponse([
            'id' => 'tr_000000000000',
            'status' => 'processing',
            'tracking_transaction_monitoring' => ['step' => 'processing', 'completed_at' => null],
            'tracking_paymaster' => ['step' => 'processing', 'transaction_hash' => null, 'completed_at' => null],
            'tracking_bridge_swap' => ['step' => 'processing', 'transaction_hash' => null, 'completed_at' => null],
            'tracking_complete' => ['step' => 'processing', 'transaction_hash' => null, 'completed_at' => null],
        ]);

        $input = new CreateTransferInput(transferQuoteId: 'tq_000000000000');

        $response = $this->blindpay->transfers->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertInstanceOf(CreateTransferResponse::class, $response->data);
        $this->assertEquals('tr_000000000000', $response->data->id);
        $this->assertSame(TransactionStatus::PROCESSING, $response->data->status);
        $this->assertSame('processing', $response->data->trackingComplete->step);
        $this->assertNull($response->data->trackingComplete->status);
        $this->assertNull($response->data->trackingPartnerFee);
    }

    #[Test]
    public function it_gets_a_transfer(): void
    {
        $mockedTransfer = $this->mockTransferData(['status' => 'completed']);

        $this->mockResponse($mockedTransfer);

        $response = $this->blindpay->transfers->get('tr_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('tr_000000000000', $response->data->id);
        $this->assertEquals('completed', $response->data->status->value);
        $this->assertEquals('rc_000000000000', $response->data->customerId);
        $this->assertEquals('base', $response->data->network->value);
    }

    #[Test]
    public function it_returns_error_for_empty_transfer_id(): void
    {
        $response = $this->blindpay->transfers->get('');

        $this->assertFalse($response->isSuccess());
        $this->assertNotNull($response->error);
    }

    #[Test]
    public function it_lists_transfers(): void
    {
        $mockedTransfers = [
            'data' => [
                $this->mockTransferData(['status' => 'completed']),
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
        $this->assertEquals('completed', $response->data->data[0]->status->value);
    }

    #[Test]
    public function it_gets_transfer_tracking(): void
    {
        $mockedTransfer = $this->mockTransferData(['status' => 'completed']);

        $this->mockResponse($mockedTransfer);

        $response = $this->blindpay->transfers->getTrack('tr_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('tr_000000000000', $response->data->id);
        $this->assertEquals('pending', $response->data->trackingTransactionMonitoring->step);
        $this->assertEquals('pending', $response->data->trackingPaymaster->step);
        $this->assertEquals('pending', $response->data->trackingBridgeSwap->step);
    }

    #[Test]
    public function it_handles_transfer_with_optional_fields(): void
    {
        $mockedTransfer = $this->mockTransferData([
            'image_url' => 'https://example.com/avatar.png',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'legal_name' => 'John Doe',
            'partner_fee_amount' => 2.50,
            'external_id' => 'ext_000000000000',
        ]);

        $this->mockResponse($mockedTransfer);

        $response = $this->blindpay->transfers->get('tr_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('https://example.com/avatar.png', $response->data->imageUrl);
        $this->assertEquals('John', $response->data->firstName);
        $this->assertEquals('Doe', $response->data->lastName);
        $this->assertEquals('John Doe', $response->data->legalName);
        $this->assertEquals(2.50, $response->data->partnerFeeAmount);
        $this->assertEquals('ext_000000000000', $response->data->externalId);
    }
}
