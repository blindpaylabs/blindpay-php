<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Payins\CreatePayinQuoteInput;
use BlindPay\SDK\Resources\Payins\GetPayinFxRateInput;
use BlindPay\SDK\Resources\Payins\PayerRules;
use BlindPay\SDK\Resources\Payins\PaymentMethod;
use BlindPay\SDK\Types\Currency;
use BlindPay\SDK\Types\CurrencyType;
use BlindPay\SDK\Types\StablecoinToken;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PayinQuotesTest extends TestCase
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
    public function it_creates_a_payin_quote(): void
    {
        $mockedPayinQuote = [
            'id' => 'qu_000000000000',
            'expires_at' => 1712958191,
            'commercial_quotation' => 495,
            'blindpay_quotation' => 505,
            'receiver_amount' => 1010,
            'sender_amount' => 5240,
            'partner_fee_amount' => 150,
            'flat_fee' => 50,
        ];

        $this->mockResponse($mockedPayinQuote);

        $payerRules = new PayerRules(
            pixAllowedTaxIds: ['149.476.037-68']
        );

        $input = new CreatePayinQuoteInput(
            blockchainWalletId: 'bw_000000000000',
            currencyType: CurrencyType::SENDER,
            paymentMethod: PaymentMethod::PIX,
            requestAmount: 1000,
            token: StablecoinToken::USDC,
            coverFees: true,
            payerRules: $payerRules,
            partnerFeeId: 'pf_000000000000'
        );

        $response = $this->blindpay->payins->quotes->create($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals('qu_000000000000', $response->data->id);
        $this->assertEquals(1712958191, $response->data->expiresAt);
        $this->assertEquals(495.0, $response->data->commercialQuotation);
        $this->assertEquals(505.0, $response->data->blindpayQuotation);
        $this->assertEquals(1010.0, $response->data->receiverAmount);
        $this->assertEquals(5240.0, $response->data->senderAmount);
        $this->assertEquals(150.0, $response->data->partnerFeeAmount);
        $this->assertEquals(50.0, $response->data->flatFee);
    }

    #[Test]
    public function it_gets_fx_rate(): void
    {
        $mockedFxRate = [
            'commercial_quotation' => 495,
            'blindpay_quotation' => 505,
            'result_amount' => 1,
            'instance_flat_fee' => 50,
            'instance_percentage_fee' => 0,
        ];

        $this->mockResponse($mockedFxRate);

        $input = new GetPayinFxRateInput(
            currencyType: CurrencyType::SENDER,
            from: Currency::USD,
            to: Currency::BRL,
            requestAmount: 1000
        );

        $response = $this->blindpay->payins->quotes->getFxRate($input);

        $this->assertTrue($response->isSuccess());
        $this->assertNull($response->error);
        $this->assertEquals(495.0, $response->data->commercialQuotation);
        $this->assertEquals(505.0, $response->data->blindpayQuotation);
        $this->assertEquals(1.0, $response->data->resultAmount);
        $this->assertEquals(50.0, $response->data->instanceFlatFee);
        $this->assertEquals(0.0, $response->data->instancePercentageFee);
    }
}
