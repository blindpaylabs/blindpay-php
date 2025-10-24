<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Payins\ExportPayinsInput;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TransactionStatus;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PayinsTest extends TestCase
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

    private function getPayinMockData(): array
    {
        return [
            'receiver_id' => 're_000000000000',
            'id' => 're_000000000000',
            'pix_code' => '00020101021226790014br.gov.bcb.pix2557brcode.starkinfra.com/v2/bcf07f6c4110454e9fd6f120bab13e835204000053039865802BR5915Blind Pay, Inc.6010Vila Velha62070503***6304BCAB',
            'memo_code' => '8K45GHBNT6BQ6462',
            'clabe' => '014027000000000008',
            'status' => 'processing',
            'payin_quote_id' => 'pq_000000000000',
            'instance_id' => 'in_000000000000',
            'tracking_transaction' => [
                'step' => 'processing',
                'status' => 'failed',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_payment' => [
                'step' => 'on_hold',
                'provider_name' => 'blockchain',
                'provider_transaction_id' => 'tx_123456789',
                'provider_status' => 'confirmed',
                'estimated_time_of_arrival' => '2011-10-05T15:00:00.000Z',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_complete' => [
                'step' => 'on_hold',
                'status' => 'completed',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_partner_fee' => [
                'step' => 'on_hold',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'created_at' => '2021-01-01T00:00:00Z',
            'updated_at' => '2021-01-01T00:00:00Z',
            'image_url' => 'https://example.com/image.png',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'legal_name' => 'Company Name Inc.',
            'type' => 'individual',
            'payment_method' => 'pix',
            'sender_amount' => 5240,
            'receiver_amount' => 1010,
            'token' => 'USDC',
            'partner_fee_amount' => 150,
            'total_fee_amount' => 1.53,
            'commercial_quotation' => 495,
            'blindpay_quotation' => 505,
            'currency' => 'BRL',
            'billing_fee' => 100,
            'name' => 'Wallet Display Name',
            'address' => '0xDD6a3aD0949396e57C7738ba8FC1A46A5a1C372C',
            'network' => 'polygon',
            'blindpay_bank_details' => [
                'routing_number' => '121145349',
                'account_number' => '621327727210181',
                'account_type' => 'Business checking',
                'swift_bic_code' => 'CHASUS33',
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
                'beneficiary' => [
                    'name' => 'BlindPay, Inc.',
                    'address_line_1' => '8 The Green, #19364',
                    'address_line_2' => 'Dover, DE 19901',
                ],
                'receiving_bank' => [
                    'name' => 'Column NA - Brex',
                    'address_line_1' => '1 Letterman Drive, Building A, Suite A4-700',
                    'address_line_2' => 'San Francisco, CA 94129',
                ],
            ],
        ];
    }

    #[Test]
    public function it_lists_payins(): void
    {
        $mockedPayins = [
            'data' => [$this->getPayinMockData()],
            'pagination' => [
                'has_more' => true,
                'next_page' => 3,
                'prev_page' => 1,
            ],
        ];

        $this->mockResponse($mockedPayins);

        $response = $this->blindpay->payins->list();

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data->data);
        $this->assertCount(1, $response->data->data);
        $this->assertEquals('re_000000000000', $response->data->data[0]->id);
        $this->assertEquals('re_000000000000', $response->data->data[0]->receiverId);
        $this->assertTrue($response->data->pagination->hasMore);
        $this->assertEquals(3, $response->data->pagination->nextPage);
        $this->assertEquals(1, $response->data->pagination->prevPage);
    }

    #[Test]
    public function it_gets_a_payin(): void
    {
        $mockedPayin = $this->getPayinMockData();

        $this->mockResponse($mockedPayin);

        $response = $this->blindpay->payins->get('pi_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('re_000000000000', $response->data->id);
        $this->assertEquals('re_000000000000', $response->data->receiverId);
        $this->assertEquals('pq_000000000000', $response->data->payinQuoteId);
        $this->assertEquals(TransactionStatus::PROCESSING, $response->data->status);
        $this->assertEquals('processing', $response->data->trackingTransaction->step);
        $this->assertEquals(StablecoinToken::USDC, $response->data->token);
        $this->assertEquals(Network::POLYGON, $response->data->network);
    }

    #[Test]
    public function it_gets_payin_tracking_information(): void
    {
        $mockedPayinTrack = $this->getPayinMockData();
        $mockedPayinTrack['tracking_transaction'] = [
            'step' => 'processing',
            'status' => 'failed',
            'transaction_hash' => '0x123...890',
            'external_id' => '12345678',
            'completed_at' => '2011-10-05T14:48:00.000Z',
            'sender_name' => 'John Doe Smith',
            'sender_tax_id' => '123.456.789-10',
            'sender_bank_code' => '00416968',
            'sender_account_number' => '1234567890',
            'trace_number' => '1234567890',
            'transaction_reference' => '1234567890',
            'description' => 'Payment from John Doe Smith',
        ];
        $mockedPayinTrack['tracking_payment'] = [
            'step' => 'on_hold',
            'provider_name' => 'blockchain',
            'provider_transaction_id' => 'tx_123456789',
            'provider_status' => 'confirmed',
            'estimated_time_of_arrival' => '2011-10-05T15:00:00.000Z',
            'completed_at' => '2011-10-05T14:48:00.000Z',
        ];

        $this->mockResponse($mockedPayinTrack);

        $response = $this->blindpay->payins->getTrack('pi_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('re_000000000000', $response->data->id);
        $this->assertEquals('processing', $response->data->trackingTransaction->step);
        $this->assertEquals('0x123...890', $response->data->trackingTransaction->transactionHash);
        $this->assertEquals('on_hold', $response->data->trackingPayment->step);
        $this->assertEquals('blockchain', $response->data->trackingPayment->providerName);
    }

    #[Test]
    public function it_exports_payins(): void
    {
        $mockedExportPayins = [$this->getPayinMockData()];

        $this->mockResponse($mockedExportPayins);

        $input = new ExportPayinsInput(
            status: TransactionStatus::PROCESSING
        );

        $response = $this->blindpay->payins->export($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertIsArray($response->data);
        $this->assertCount(1, $response->data);
        $this->assertEquals('re_000000000000', $response->data[0]->id);
        $this->assertEquals(TransactionStatus::PROCESSING, $response->data[0]->status);
    }

    #[Test]
    public function it_creates_an_evm_payin(): void
    {
        $mockedEvmPayin = [
            'id' => 'pi_000000000000',
            'status' => 'processing',
            'pix_code' => '00020101021226790014br.gov.bcb.pix2557brcode.starkinfra.com/v2/bcf07f6c4110454e9fd6f120bab13e835204000053039865802BR5915Blind Pay, Inc.6010Vila Velha62070503***6304BCAB',
            'memo_code' => '8K45GHBNT6BQ6462',
            'clabe' => '014027000000000008',
            'tracking_complete' => [
                'step' => 'on_hold',
                'status' => 'completed',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_payment' => [
                'step' => 'on_hold',
                'provider_name' => 'blockchain',
                'provider_transaction_id' => 'tx_123456789',
                'provider_status' => 'confirmed',
                'estimated_time_of_arrival' => '2011-10-05T15:00:00.000Z',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_transaction' => [
                'step' => 'processing',
                'status' => 'failed',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'tracking_partner_fee' => [
                'step' => 'on_hold',
                'transaction_hash' => '0x123...890',
                'completed_at' => '2011-10-05T14:48:00.000Z',
            ],
            'blindpay_bank_details' => [
                'routing_number' => '121145349',
                'account_number' => '621327727210181',
                'account_type' => 'Business checking',
                'swift_bic_code' => 'CHASUS33',
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
                'beneficiary' => [
                    'name' => 'BlindPay, Inc.',
                    'address_line_1' => '8 The Green, #19364',
                    'address_line_2' => 'Dover, DE 19901',
                ],
                'receiving_bank' => [
                    'name' => 'Column NA - Brex',
                    'address_line_1' => '1 Letterman Drive, Building A, Suite A4-700',
                    'address_line_2' => 'San Francisco, CA 94129',
                ],
            ],
            'receiver_id' => 're_000000000000',
            'receiver_amount' => 1010,
        ];

        $this->mockResponse($mockedEvmPayin);

        $response = $this->blindpay->payins->createEvm('pq_000000000000');

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('pi_000000000000', $response->data->id);
        $this->assertEquals(TransactionStatus::PROCESSING, $response->data->status);
        $this->assertEquals('re_000000000000', $response->data->receiverId);
        $this->assertEquals(1010.0, $response->data->receiverAmount);
    }
}
