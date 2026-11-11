<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Internal;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\BlindPayApiException;
use BlindPay\SDK\Internal\BlindPayException;
use BlindPay\SDK\Internal\Query;
use BlindPay\SDK\Resources\CustodialWallets\GetWalletDepositsInput;
use BlindPay\SDK\Resources\Customers\ListCustomersInput;
use BlindPay\SDK\Resources\Payins\ListPayinsInput;
use BlindPay\SDK\Resources\Payouts\ListPayoutsInput;
use BlindPay\SDK\Resources\Payouts\Payout;
use BlindPay\SDK\Resources\Transfers\ListTransfersInput;
use BlindPay\SDK\Types\ListResponse;
use BlindPay\SDK\Types\RequestOptions;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** List envelopes (D3) and the listAutoPaging() generators (C5). */
final class AutoPagerTest extends TestCase
{
    private MockHandler $mock;

    private array $history = [];

    private function client(): BlindPay
    {
        $this->mock = new MockHandler;
        $this->history = [];
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        return new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000', maxRetries: 0, handler: $stack);
    }

    /** A spec-shaped payout row from the shared fixtures, with the given id. */
    private function payout(string $id): array
    {
        $fixture = json_decode((string) file_get_contents(__DIR__.'/../Fixtures/spec/responses/get__instances_instance_id_payouts_id.json'), true);

        return ['id' => $id] + $fixture['responses']['200']['variants']['full'];
    }

    private function page(array $ids, bool $hasMore): Response
    {
        return new Response(200, [], json_encode([
            'data' => array_map(fn (string $id) => $this->payout($id), $ids),
            'pagination' => ['has_more' => $hasMore, 'next_page' => $hasMore ? end($ids) : null, 'prev_page' => null],
        ]));
    }

    /** @return list<string> the query string of every request */
    private function queries(): array
    {
        return array_map(fn (array $t) => $t['request']->getUri()->getQuery(), $this->history);
    }

    #[Test]
    public function it_follows_next_page_until_has_more_is_false(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->page(['po_1', 'po_2'], true), $this->page(['po_3'], true), $this->page(['po_4'], false));

        $ids = [];
        foreach ($blindpay->payouts->listAutoPaging(new ListPayoutsInput(customerId: 'cu_1'), new RequestOptions(headers: ['X-Trace' => 't'])) as $payout) {
            $this->assertInstanceOf(Payout::class, $payout);
            $ids[] = $payout->id;
        }

        $this->assertSame(['po_1', 'po_2', 'po_3', 'po_4'], $ids);
        $this->assertSame([
            'customer_id=cu_1&limit=100',
            'customer_id=cu_1&limit=100&starting_after=po_2',
            'customer_id=cu_1&limit=100&starting_after=po_3',
        ], $this->queries());
        $this->assertSame('t', $this->history[2]['request']->getHeaderLine('X-Trace'));
    }

    #[Test]
    public function it_keeps_the_callers_limit_and_starting_point(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->page(['po_9'], false));

        iterator_to_array($blindpay->payouts->listAutoPaging(new ListPayoutsInput(limit: 10, startingAfter: 'po_8')));

        $this->assertSame(['limit=10&starting_after=po_8'], $this->queries());
    }

    #[Test]
    public function it_is_lazy_and_stops_when_the_caller_stops(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->page(['po_1', 'po_2'], true), $this->page(['po_3'], false));

        $pager = $blindpay->payouts->listAutoPaging();
        $this->assertSame([], $this->history);
        foreach ($pager as $payout) {
            break;
        }

        $this->assertCount(1, $this->history);
    }

    #[Test]
    public function it_failed_page_throws_with_the_error_response(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->page(['po_1'], true), new Response(400, [], json_encode(['success' => false, 'code' => 'INVALID_CURSOR', 'message' => 'invalid_cursor'])));

        $seen = [];

        try {
            foreach ($blindpay->payouts->listAutoPaging() as $payout) {
                $seen[] = $payout->id;
            }
            $this->fail('expected BlindPayApiException');
        } catch (BlindPayApiException $e) {
            $this->assertSame(400, $e->error->status);
            $this->assertSame('INVALID_CURSOR', $e->error->code);
        }
        $this->assertSame(['po_1'], $seen);
    }

    #[Test]
    public function it_rejects_ending_before(): void
    {
        $this->expectException(BlindPayException::class);

        iterator_to_array($this->client()->payouts->listAutoPaging(new ListPayoutsInput(endingBefore: 'po_1')));
    }

    #[Test]
    public function it_every_cursor_paginated_list_has_an_auto_pager(): void
    {
        $blindpay = $this->client();
        $empty = new Response(200, [], json_encode(['data' => [], 'pagination' => ['has_more' => false, 'next_page' => null, 'prev_page' => null]]));
        $this->mock->append($empty, clone $empty, clone $empty, clone $empty, clone $empty, clone $empty);

        iterator_to_array($blindpay->payins->listAutoPaging(new ListPayinsInput));
        iterator_to_array($blindpay->customers->listAutoPaging(new ListCustomersInput));
        iterator_to_array($blindpay->transfers->listAutoPaging(new ListTransfersInput));
        iterator_to_array($blindpay->payouts->listAutoPaging());
        iterator_to_array($blindpay->payables->listAutoPaging());
        iterator_to_array($blindpay->wallets->custodial->getDepositsAutoPaging(new GetWalletDepositsInput('cu_1', 'wa_1')));

        $this->assertSame(
            [
                '/v1/instances/in_000000000000/payins', '/v1/instances/in_000000000000/customers', '/v1/instances/in_000000000000/transfers',
                '/v1/instances/in_000000000000/payouts', '/v1/instances/in_000000000000/payables', '/v1/instances/in_000000000000/customers/cu_1/wallets/wa_1/deposits',
            ],
            array_map(fn (array $t) => $t['request']->getUri()->getPath(), $this->history),
        );
        $this->assertSame(array_fill(0, 6, 'limit=100'), $this->queries());
    }

    #[Test]
    public function it_list_decodes_both_wire_shapes_into_one_type(): void
    {
        $blindpay = $this->client();
        $this->mock->append(new Response(200, [], json_encode([$this->payout('po_1')])), $this->page(['po_2'], true));

        $bare = $blindpay->payouts->list()->data;
        $envelope = $blindpay->payouts->list(new ListPayoutsInput(limit: 10))->data;

        $this->assertInstanceOf(ListResponse::class, $bare);
        $this->assertNull($bare->pagination);
        $this->assertFalse($bare->hasMore());
        $this->assertCount(1, $bare);
        $this->assertSame('po_2', $envelope->data[0]->id);
        $this->assertTrue($envelope->hasMore());
        $this->assertSame('po_2', $envelope->pagination->nextPage);
        $this->assertSame(['', 'limit=10'], $this->queries());
    }

    #[Test]
    public function it_query_helper_skips_unset_values(): void
    {
        $this->assertSame('', Query::build(['a' => null, 'b' => '']));
        $this->assertSame('?limit=10&x=a%20b%26c&flag=false', Query::build(['limit' => '10', 'skip' => null, 'x' => 'a b&c', 'flag' => false]));
    }
}
