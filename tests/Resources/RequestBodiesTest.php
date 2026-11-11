<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Resources;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Customers\CreateCustomerInput;
use BlindPay\SDK\Resources\Customers\KycType;
use BlindPay\SDK\Resources\Payables\CreatePayableInput;
use BlindPay\SDK\Resources\Payables\PayableLineItem;
use BlindPay\SDK\Resources\Payables\PayableParty;
use BlindPay\SDK\Resources\Wallets\SetSelfCustodyInput;
use BlindPay\SDK\Resources\Wallets\UpdateOfframpWalletInput;
use BlindPay\SDK\Types\AccountClass;
use BlindPay\SDK\Types\Country;
use BlindPay\SDK\Types\Currency;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Exact JSON bodies of the operations added in 4.0.0 (A8 item 4: absent optionals are absent). */
final class RequestBodiesTest extends TestCase
{
    private array $history = [];

    private function send(callable $call, array $response = ['success' => true]): array
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode($response))]));
        $stack->push(Middleware::history($this->history));
        $call(new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000', handler: $stack));

        return json_decode((string) $this->history[0]['request']->getBody(), true);
    }

    #[Test]
    public function offramp_update_sends_an_explicit_null_to_clear_the_partner_fee(): void
    {
        $this->assertSame(['partner_fee_id' => null], $this->send(fn (BlindPay $b) => $b->wallets->offramp->update(new UpdateOfframpWalletInput('cu_1', 'ba_1', 'ow_1', null))));
        $this->assertSame(['partner_fee_id' => 'fe_1'], $this->send(fn (BlindPay $b) => $b->wallets->offramp->update(new UpdateOfframpWalletInput('cu_1', 'ba_1', 'ow_1', 'fe_1'))));
    }

    #[Test]
    public function set_self_custody_sends_the_flag(): void
    {
        $this->assertSame(['is_self_custody' => false], $this->send(fn (BlindPay $b) => $b->wallets->blockchain->setSelfCustody(new SetSelfCustodyInput('cu_1', 'bw_1', false)), ['id' => 'bw_1', 'name' => 'x', 'network' => 'base', 'customer_id' => 'cu_1']));
    }

    #[Test]
    public function rfi_submit_sends_the_answers_as_the_whole_body(): void
    {
        $answers = ['occupation' => 'engineer', 'proof' => 'https://files.blindpay.com/p.pdf'];

        $this->assertSame($answers, $this->send(fn (BlindPay $b) => $b->customers->rfi->submit('cu_1', $answers)));
        $this->assertSame($answers, $this->send(fn (BlindPay $b) => $b->instances->rfi->submit($answers)));
    }

    #[Test]
    public function rfi_get_returns_null_data_when_no_rfi_is_open(): void
    {
        foreach ([fn (BlindPay $b) => $b->customers->rfi->get('cu_1'), fn (BlindPay $b) => $b->instances->rfi->get()] as $call) {
            $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], 'null')]));
            $response = $call(new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000', handler: $stack));

            $this->assertTrue($response->isSuccess());
            $this->assertNull($response->data);
        }
    }

    #[Test]
    public function plaid_link_sends_the_redirect_url_only_when_given(): void
    {
        $this->assertSame(['redirect_url' => 'https://x'], $this->send(fn (BlindPay $b) => $b->customers->bankAccounts->createPlaidLink('cu_1', 'https://x'), ['url' => 'https://plaid']));
        $this->assertSame([], $this->send(fn (BlindPay $b) => $b->customers->bankAccounts->createPlaidLink('cu_1'), ['url' => 'https://plaid']));
    }

    #[Test]
    public function a_light_kyc_customer_can_be_created_with_only_the_required_fields(): void
    {
        $body = $this->send(fn (BlindPay $b) => $b->customers->create(new CreateCustomerInput(
            AccountClass::INDIVIDUAL,
            KycType::LIGHT,
            'jane@example.com',
            Country::BR,
            ['first_name' => 'Jane', 'tax_id' => '00000000000'],
        )), ['id' => 'cu_000000000000']);

        $this->assertSame(['type' => 'individual', 'kyc_type' => 'light', 'email' => 'jane@example.com', 'country' => 'BR', 'first_name' => 'Jane', 'tax_id' => '00000000000'], $body);
    }

    #[Test]
    public function payable_create_sends_only_what_was_set(): void
    {
        $payable = ['id' => 'pa_1', 'instance_id' => 'in_000000000000', 'customer_id' => 'cu_1', 'currency' => 'USD', 'status' => 'draft', 'amount' => 300000];

        $body = $this->send(fn (BlindPay $b) => $b->payables->create(new CreatePayableInput(
            customerId: 'cu_1',
            currency: Currency::USD,
            to: new PayableParty(legalName: 'Northwind'),
            lineItems: [new PayableLineItem('Consulting', 2, 150000)],
            bankAccount: ['type' => 'ach', 'beneficiary_name' => 'Northwind'],
        )), $payable);

        $this->assertSame([
            'customer_id' => 'cu_1',
            'currency' => 'USD',
            'to' => ['legal_name' => 'Northwind'],
            'line_items' => [['name' => 'Consulting', 'quantity' => 2, 'price' => 150000]],
            'bank_account' => ['type' => 'ach', 'beneficiary_name' => 'Northwind'],
        ], $body);
    }
}
