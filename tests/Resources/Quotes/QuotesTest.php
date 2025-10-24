<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Quotes\CreateQuoteInput;
use BlindPay\SDK\Resources\Quotes\GetFxRateInput;
use BlindPay\SDK\Types\Currency;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\Network;
use BlindPay\SDK\Types\StablecoinToken;
use BlindPay\SDK\Types\TransactionDocumentType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class QuotesTest extends TestCase
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
    public function it_creates_a_quote(): void
    {
        $mockedQuote = [
            'id' => 'qu_000000000000',
            'expires_at' => 1712958191,
            'commercial_quotation' => 495,
            'blindpay_quotation' => 485,
            'receiver_amount' => 5240,
            'sender_amount' => 1010,
            'partner_fee_amount' => 150,
            'flat_fee' => 50,
            'contract' => [
                'abi' => [[]],
                'address' => '0x1c7D4B196Cb0C7B01d743Fbc6116a902379C7238',
                'functionName' => 'approve',
                'blindpayContractAddress' => '0x1c7D4B196Cb0C7B01d743Fbc6116a902379C7238',
                'amount' => '1000000000000000000',
                'network' => [
                    'name' => 'Ethereum',
                    'chainId' => 1,
                ],
            ],
            'receiver_local_amount' => 1000,
            'description' => 'Memo code or description, only works with USD and BRL',
        ];

        $this->mockResponse($mockedQuote);

        $input = new CreateQuoteInput(
            bankAccountId: 'ba_000000000000',
            currencyType: CurrencyType::SENDER,
            requestAmount: 1000,
            coverFees: true,
            partnerFeeId: 'pf_000000000000',
            transactionDocumentFile: null,
            transactionDocumentId: null,
            transactionDocumentType: TransactionDocumentType::INVOICE,
            network: Network::SEPOLIA,
            token: StablecoinToken::USDC,
            description: 'Memo code or description, only works with USD and BRL'
        );

        $response = $this->blindpay->quotes->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('qu_000000000000', $response->data->id);
        $this->assertEquals(1712958191, $response->data->expiresAt);
        $this->assertEquals(495.0, $response->data->commercialQuotation);
        $this->assertEquals(485.0, $response->data->blindpayQuotation);
        $this->assertEquals(5240.0, $response->data->receiverAmount);
        $this->assertEquals(1010.0, $response->data->senderAmount);
        $this->assertEquals(150.0, $response->data->partnerFeeAmount);
        $this->assertEquals(50.0, $response->data->flatFee);
        $this->assertEquals(1000.0, $response->data->receiverLocalAmount);
        $this->assertEquals('Memo code or description, only works with USD and BRL', $response->data->description);

        // Assert contract details
        $this->assertIsArray($response->data->contract->abi);
        $this->assertCount(1, $response->data->contract->abi);
        $this->assertEquals('0x1c7D4B196Cb0C7B01d743Fbc6116a902379C7238', $response->data->contract->address);
        $this->assertEquals('approve', $response->data->contract->functionName);
        $this->assertEquals('0x1c7D4B196Cb0C7B01d743Fbc6116a902379C7238', $response->data->contract->blindpayContractAddress);
        $this->assertEquals('1000000000000000000', $response->data->contract->amount);
        $this->assertEquals('Ethereum', $response->data->contract->network->name);
        $this->assertEquals(1, $response->data->contract->network->chainId);
    }

    #[Test]
    public function it_gets_fx_rate(): void
    {
        $mockedFxRate = [
            'commercial_quotation' => 495,
            'blindpay_quotation' => 485,
            'result_amount' => 1,
            'instance_flat_fee' => 50,
            'instance_percentage_fee' => 0,
        ];

        $this->mockResponse($mockedFxRate);

        $input = new GetFxRateInput(
            currencyType: CurrencyType::SENDER,
            from: Currency::USD,
            to: Currency::BRL,
            requestAmount: 1000
        );

        $response = $this->blindpay->quotes->getFxRate($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals(495.0, $response->data->commercialQuotation);
        $this->assertEquals(485.0, $response->data->blindpayQuotation);
        $this->assertEquals(1.0, $response->data->resultAmount);
        $this->assertEquals(50.0, $response->data->instanceFlatFee);
        $this->assertEquals(0.0, $response->data->instancePercentageFee);
    }
}
