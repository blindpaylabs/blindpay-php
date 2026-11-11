<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Internal;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\BlindPayApiException;
use BlindPay\SDK\Internal\BlindPayException;
use BlindPay\SDK\Internal\Path;
use BlindPay\SDK\Types\BlindPayApiResponse;
use BlindPay\SDK\Types\ErrorType;
use BlindPay\SDK\Types\RequestOptions;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * The transport contract shared by every BlindPay SDK: headers, idempotency keys, the retry
 * decision table (sdk-parity Appendix A2, one test per row) and the error model. No test sleeps:
 * retries go through a recording fake sleeper.
 */
final class TransportTest extends TestCase
{
    private const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    private MockHandler $mock;

    /** @var list<array{request: RequestInterface, options: array<string, mixed>}> */
    private array $history = [];

    /** @var list<float> */
    private array $sleeps = [];

    private function client(int $maxRetries = 2, string $baseUrl = BlindPay::DEFAULT_BASE_URL): BlindPay
    {
        $this->mock = new MockHandler;
        $this->history = [];
        $this->sleeps = [];
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        return new BlindPay(
            apiKey: 'test_key',
            instanceId: 'in_000000000000',
            baseUrl: $baseUrl,
            maxRetries: $maxRetries,
            handler: $stack,
            sleep: function (float $seconds): void {
                $this->sleeps[] = $seconds;
            },
        );
    }

    private function json(int $status, array $body, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json', ...$headers], json_encode($body));
    }

    private function timeout(): ConnectException
    {
        return new ConnectException('cURL error 28: Operation timed out', new Request('GET', '/'), null, ['errno' => 28]);
    }

    /** @return list<string|null> the Idempotency-Key header of every attempt */
    private function keys(): array
    {
        return array_map(fn (array $t) => $t['request']->hasHeader('Idempotency-Key') ? $t['request']->getHeaderLine('Idempotency-Key') : null, $this->history);
    }

    // -- C1: configuration and headers ----------------------------------------------------------

    #[Test]
    public function it_sends_the_sdk_headers_and_no_content_type_without_a_body(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->json(200, []));

        $blindpay->get('available/rails');

        $request = $this->history[0]['request'];
        $this->assertSame('https://api.blindpay.com/v1/available/rails', (string) $request->getUri());
        $this->assertSame('Bearer test_key', $request->getHeaderLine('Authorization'));
        $this->assertMatchesRegularExpression('#^blindpay-php/\d+\.\d+\.\d+$#', $request->getHeaderLine('User-Agent'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertFalse($request->hasHeader('Content-Type'));
        $this->assertFalse($request->hasHeader('Idempotency-Key'));
        $this->assertSame(60.0, $this->history[0]['options']['timeout']);
    }

    #[Test]
    public function it_takes_the_user_agent_version_from_the_version_constant(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->json(200, []));
        $blindpay->get('available/rails');

        $version = (new \ReflectionClassConstant(BlindPay::class, 'VERSION'))->getValue();
        $this->assertSame("blindpay-php/{$version}", $this->history[0]['request']->getHeaderLine('User-Agent'));
    }

    #[Test]
    public function it_sends_json_bodies_with_a_content_type_and_an_object_for_an_empty_body(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->json(200, []), $this->json(200, []));

        $blindpay->post('instances/in_000000000000/payouts/evm', ['quote_id' => 'qu_1']);
        $blindpay->post('instances/in_000000000000/things', []);

        $this->assertSame('application/json', $this->history[0]['request']->getHeaderLine('Content-Type'));
        $this->assertSame('{"quote_id":"qu_1"}', (string) $this->history[0]['request']->getBody());
        $this->assertSame('{}', (string) $this->history[1]['request']->getBody());
    }

    #[Test]
    public function it_honours_a_custom_base_url_and_per_request_timeout_and_headers(): void
    {
        $blindpay = $this->client(baseUrl: 'http://localhost:8080/');
        $this->mock->append($this->json(200, []));

        $blindpay->get('available/rails', new RequestOptions(timeout: 5.0, headers: ['X-Trace' => 'abc', 'authorization' => 'Bearer stolen']));

        $request = $this->history[0]['request'];
        $this->assertSame('http://localhost:8080/v1/available/rails', (string) $request->getUri());
        $this->assertSame(5.0, $this->history[0]['options']['timeout']);
        $this->assertSame('abc', $request->getHeaderLine('X-Trace'));
        $this->assertSame('Bearer test_key', $request->getHeaderLine('Authorization'));
    }

    #[Test]
    public function it_rejects_invalid_configuration(): void
    {
        foreach ([['timeout' => 0.0], ['maxRetries' => -1], ['baseUrl' => 'api.blindpay.com'], ['apiKey' => ''], ['instanceId' => '']] as $override) {
            try {
                new BlindPay(...[...['apiKey' => 'test_key', 'instanceId' => 'in_000000000000'], ...$override]);
                $this->fail('expected a BlindPayException for '.json_encode($override));
            } catch (BlindPayException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function it_never_exposes_the_api_key_when_dumped(): void
    {
        $blindpay = new BlindPay(apiKey: 'sk_secret_value', instanceId: 'in_000000000000');

        $this->assertStringNotContainsString('sk_secret_value', print_r($blindpay, true));
        ob_start();
        var_dump($blindpay);
        $this->assertStringNotContainsString('sk_secret_value', (string) ob_get_clean());
    }

    #[Test]
    public function it_percent_encodes_path_segments(): void
    {
        $this->assertSame('instances/in_1/payouts/a%2Fb%3Fc%20d', Path::of('instances/%s/payouts/%s', 'in_1', 'a/b?c d'));
    }

    // -- C2: idempotency keys -------------------------------------------------------------------

    #[Test]
    public function it_generates_one_uuid_v4_key_per_mutation_before_the_first_attempt(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->json(200, []), $this->json(200, []));

        $blindpay->post('instances/in_000000000000/payouts/evm', ['quote_id' => 'qu_1']);
        $blindpay->delete('instances/in_000000000000/customers/cu_1');

        [$first, $second] = $this->keys();
        $this->assertMatchesRegularExpression(self::UUID_V4, (string) $first);
        $this->assertMatchesRegularExpression(self::UUID_V4, (string) $second);
        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function it_sends_the_callers_idempotency_key(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->json(200, []));

        $blindpay->put('instances/in_000000000000', ['name' => 'x'], new RequestOptions(idempotencyKey: 'order-42'));

        $this->assertSame(['order-42'], $this->keys());
    }

    #[Test]
    public function it_rejects_an_empty_or_over_long_idempotency_key_without_sending(): void
    {
        $blindpay = $this->client();

        foreach (['', str_repeat('k', 256)] as $key) {
            $response = $blindpay->post('instances/in_000000000000/payouts/evm', [], new RequestOptions(idempotencyKey: $key));
            $this->assertTrue($response->isError());
            $this->assertSame(ErrorType::VALIDATION, $response->error->type);
        }
        $this->assertSame([], $this->history);
    }

    #[Test]
    public function it_sends_no_key_on_gets_or_when_retries_are_off_and_none_was_given(): void
    {
        $blindpay = $this->client(maxRetries: 0);
        $this->mock->append($this->json(200, []), $this->json(200, []));

        $blindpay->get('available/rails');
        $blindpay->post('instances/in_000000000000/payouts/evm', []);

        $this->assertSame([null, null], $this->keys());
    }

    // -- C3: the A2 retry table -----------------------------------------------------------------

    #[Test]
    public function row_2_multipart_is_retried_only_when_nothing_was_sent(): void
    {
        $blindpay = $this->client();
        $refused = new ConnectException('cURL error 7: Failed to connect', new Request('POST', '/'), null, ['errno' => 7]);
        $this->mock->append($refused, $this->json(200, ['file_url' => 'https://x']), $this->json(503, []), $this->timeout());
        $parts = [['name' => 'bucket', 'contents' => 'avatar']];

        $this->assertTrue($blindpay->postMultipart('upload', $parts)->isSuccess());
        $this->assertSame(503, $blindpay->postMultipart('upload', $parts)->error->status);
        $this->assertTrue($blindpay->postMultipart('upload', $parts)->error->isNetworkError());

        $this->assertCount(4, $this->history);
        $this->assertSame([null, null, null, null], $this->keys());
    }

    #[Test]
    public function row_3_a_mutation_without_a_key_is_never_retried(): void
    {
        // Only reachable with maxRetries = 0, which also disables the auto key.
        $blindpay = $this->client(maxRetries: 0);
        $this->mock->append($this->json(429, ['message' => 'rate_limited']));

        $this->assertSame(429, $blindpay->post('instances/in_000000000000/payouts/evm', [])->error->status);
        $this->assertCount(1, $this->history);
    }

    #[Test]
    public function row_4_a_mutation_is_retried_after_a_timeout_with_the_same_key_and_bytes(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->timeout(), $this->json(200, ['id' => 'po_1']));

        $response = $blindpay->post('instances/in_000000000000/payouts/evm', ['quote_id' => 'qu_1', 'amount' => 1.5]);

        $this->assertTrue($response->isSuccess());
        $keys = $this->keys();
        $this->assertCount(2, $keys);
        $this->assertSame($keys[0], $keys[1]);
        $this->assertSame((string) $this->history[0]['request']->getBody(), (string) $this->history[1]['request']->getBody());
        $this->assertCount(1, $this->sleeps);
    }

    #[Test]
    public function row_4_a_network_error_is_reported_with_the_key_once_retries_run_out(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->timeout(), $this->timeout(), $this->timeout());

        $response = $blindpay->post('instances/in_000000000000/payouts/evm', []);

        $this->assertCount(3, $this->history);
        $this->assertSame(ErrorType::NETWORK, $response->error->type);
        $this->assertTrue($response->error->isNetworkError());
        $this->assertNull($response->error->status);
        $this->assertTrue($response->error->retryable);
        $this->assertSame($this->keys()[0], $response->error->idempotencyKey);
    }

    #[Test]
    public function row_5_in_flight_409_waits_retry_after_up_to_60_seconds(): void
    {
        $blindpay = $this->client();
        $inFlight = ['success' => false, 'message' => 'idempotency_key_in_flight', 'errors' => []];
        $this->mock->append($this->json(409, $inFlight, ['Retry-After' => '1']), $this->json(200, []));

        $this->assertTrue($blindpay->post('instances/in_000000000000/payouts/evm', [])->isSuccess());
        $this->assertSame([1.0], $this->sleeps);
        $this->assertSame($this->keys()[0], $this->keys()[1]);
    }

    #[Test]
    public function row_5_in_flight_409_with_retry_after_above_60_falls_back_to_backoff(): void
    {
        $blindpay = $this->client();
        $inFlight = ['success' => false, 'message' => 'idempotency_key_in_flight'];
        $this->mock->append(
            $this->json(409, $inFlight, ['Retry-After' => '120']),
            $this->json(409, $inFlight, ['Retry-After' => '120']),
            $this->json(409, $inFlight, ['Retry-After' => '120']),
        );

        $response = $blindpay->post('instances/in_000000000000/payouts/evm', []);

        $this->assertCount(3, $this->history);
        $this->assertCount(2, $this->sleeps);
        $this->assertGreaterThanOrEqual(0.25, $this->sleeps[0]);
        $this->assertLessThanOrEqual(0.5, $this->sleeps[0]);
        $this->assertGreaterThanOrEqual(0.5, $this->sleeps[1]);
        $this->assertLessThanOrEqual(1.0, $this->sleeps[1]);
        $this->assertTrue($response->error->retryable);
        $this->assertSame($this->keys()[0], $response->error->idempotencyKey);
    }

    #[Test]
    public function row_6_429_is_retried_for_mutations_and_gets_honouring_retry_after(): void
    {
        $blindpay = $this->client();
        $this->mock->append(
            $this->json(429, ['message' => 'rate_limited'], ['Retry-After' => '2']),
            $this->json(200, []),
            $this->json(429, ['message' => 'rate_limited'], ['Retry-After' => 'Wed, 21 Oct 2015 07:28:00 GMT']),
            $this->json(200, []),
        );

        $this->assertTrue($blindpay->post('instances/in_000000000000/payouts/evm', [])->isSuccess());
        $this->assertTrue($blindpay->get('available/rails')->isSuccess());

        $this->assertSame(2.0, $this->sleeps[0]);
        $this->assertLessThanOrEqual(0.5, $this->sleeps[1]);
        $this->assertSame($this->keys()[0], $this->keys()[1]);
        $this->assertSame([null, null], array_slice($this->keys(), 2));
    }

    /** @return array<string, array{int}> */
    public static function serverErrors(): array
    {
        return ['500' => [500], '502' => [502], '503' => [503], '504' => [504]];
    }

    #[Test]
    #[DataProvider('serverErrors')]
    public function row_7_a_mutation_is_never_auto_retried_on_5xx(int $status): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->json($status, ['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'internal_error']));

        $response = $blindpay->post('instances/in_000000000000/payouts/evm', ['quote_id' => 'qu_1']);

        $this->assertCount(1, $this->history);
        $this->assertSame($status, $response->error->status);
        $this->assertTrue($response->error->retryable);
        $this->assertMatchesRegularExpression(self::UUID_V4, (string) $response->error->idempotencyKey);
        $this->assertSame($this->keys()[0], $response->error->idempotencyKey);
    }

    #[Test]
    public function row_8_a_get_5xx_with_a_terminal_code_is_not_retried(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->json(502, ['success' => false, 'code' => 'PAYINS_CARD_PAYMENT_PENDING', 'message' => 'card_payment_pending']));

        $response = $blindpay->get('instances/in_000000000000/payins/pi_1');

        $this->assertCount(1, $this->history);
        $this->assertFalse($response->error->retryable);
    }

    #[Test]
    #[DataProvider('serverErrors')]
    public function row_9_a_get_is_retried_on_5xx_without_a_key(int $status): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->json($status, ['message' => 'unavailable']), $this->json(200, ['ok' => true]));

        $response = $blindpay->get('available/rails');

        $this->assertTrue($response->isSuccess());
        $this->assertSame([null, null], $this->keys());
        $this->assertCount(1, $this->sleeps);
    }

    #[Test]
    public function row_9_get_408_is_retried_until_the_budget_runs_out(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->json(408, []), $this->json(408, []), $this->json(408, []));

        $response = $blindpay->get('available/rails');

        $this->assertCount(3, $this->history);
        $this->assertSame(408, $response->error->status);
        $this->assertTrue($response->error->retryable);
    }

    /** @return array<string, array{string, int}> */
    public static function terminal(): array
    {
        return [
            'GET 400' => ['GET', 400], 'GET 404' => ['GET', 404], 'GET 422' => ['GET', 422], 'GET 409' => ['GET', 409],
            'POST 400' => ['POST', 400], 'POST 409 other' => ['POST', 409], 'POST 422' => ['POST', 422], 'POST 408' => ['POST', 408],
        ];
    }

    #[Test]
    #[DataProvider('terminal')]
    public function row_10_other_statuses_are_not_retried(string $method, int $status): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->json($status, ['success' => false, 'message' => 'idempotency_key_payload_mismatch']));

        $response = $method === 'GET' ? $blindpay->get('available/rails') : $blindpay->post('instances/in_000000000000/payouts/evm', []);

        $this->assertCount(1, $this->history);
        $this->assertSame($status, $response->error->status);
    }

    #[Test]
    public function max_retries_zero_disables_retries(): void
    {
        $blindpay = $this->client(maxRetries: 0);
        $this->mock->append($this->json(503, []));

        $this->assertSame(503, $blindpay->get('available/rails')->error->status);
        $this->assertCount(1, $this->history);
        $this->assertSame([], $this->sleeps);
    }

    #[Test]
    public function the_backoff_grows_and_is_capped_at_8_seconds(): void
    {
        $blindpay = $this->client(maxRetries: 6);
        $this->mock->append(...array_fill(0, 7, $this->json(503, [])));

        $blindpay->get('available/rails');

        $this->assertCount(6, $this->sleeps);
        foreach ($this->sleeps as $attempt => $seconds) {
            $base = min(8.0, 0.5 * (2 ** $attempt));
            $this->assertGreaterThanOrEqual($base * 0.5, $seconds, "attempt {$attempt}");
            $this->assertLessThanOrEqual($base, $seconds, "attempt {$attempt}");
        }
    }

    // -- C4: the error model, from the shared error fixtures ------------------------------------

    /** @return array<string, array{string}> */
    public static function errorFixtures(): array
    {
        $out = [];
        foreach (glob(__DIR__.'/../Fixtures/spec/errors/*.json') as $file) {
            $out[basename($file, '.json')] = [$file];
        }

        return $out;
    }

    #[Test]
    #[DataProvider('errorFixtures')]
    public function it_decodes_every_shared_error_fixture(string $file): void
    {
        $fixture = json_decode((string) file_get_contents($file), true);
        $blindpay = $this->client(maxRetries: 0);
        $body = array_key_exists('raw_body', $fixture) ? $fixture['raw_body'] : json_encode($fixture['body']);
        $this->mock->append(new Response($fixture['status'], [...($fixture['headers'] ?? []), 'X-Request-Id' => 'req_1'], $body));

        $response = $blindpay->get('available/rails');

        if ($fixture['status'] === 204) {
            $this->assertTrue($response->isSuccess());
            $this->assertNull($response->data);
            $this->assertSame('req_1', $response->requestId);

            return;
        }

        $error = $response->error;
        $this->assertSame($fixture['status'], $error->status);
        $this->assertSame(ErrorType::API, $error->type);
        $this->assertSame('req_1', $error->requestId);
        if (isset($fixture['body'])) {
            $this->assertSame($fixture['body']['message'], $error->message);
            $this->assertSame($fixture['body']['code'] ?? null, $error->code);
            $this->assertSame($fixture['body']['description'] ?? null, $error->description);
            $this->assertSame($fixture['body']['errors'] ?? [], $error->errors);
            $this->assertSame($fixture['body']['trace_id'] ?? null, $error->traceId);
        } else {
            $this->assertSame('HTTP 502 Bad Gateway', $error->message);
            $this->assertNull($error->code);
            $this->assertSame([], $error->errors);
        }
    }

    #[Test]
    public function it_exposes_success_metadata(): void
    {
        $blindpay = $this->client();
        $this->mock->append($this->json(200, ['id' => 'po_1'], ['X-Request-Id' => 'req_9', 'Idempotency-Replayed' => 'true']));

        $response = $blindpay->post('instances/in_000000000000/payouts/evm', []);

        $this->assertSame('req_9', $response->requestId);
        $this->assertTrue($response->idempotencyReplayed);
    }

    #[Test]
    public function a_2xx_body_that_is_not_json_is_a_decode_error(): void
    {
        $blindpay = $this->client();
        $this->mock->append(new Response(200, [], '<html>'));

        $response = $blindpay->get('available/rails');

        $this->assertSame(ErrorType::DECODE, $response->error->type);
        $this->assertSame(200, $response->error->status);
    }

    #[Test]
    public function map_turns_decode_failures_and_warnings_into_error_responses(): void
    {
        $ok = BlindPayApiResponse::success(['a' => 1], 'req_1');

        $typeError = $ok->map(fn (array $data) => new \DateTimeImmutable($data['a']));
        $warning = $ok->map(fn (array $data) => $data['missing']);
        $mapped = $ok->map(fn (array $data) => $data['a'] + 1);

        $this->assertSame(ErrorType::DECODE, $typeError->error->type);
        $this->assertSame(ErrorType::DECODE, $warning->error->type);
        $this->assertStringContainsString('missing', $warning->error->message);
        $this->assertSame('req_1', $warning->error->requestId);
        $this->assertSame(2, $mapped->data);
        $this->assertSame('req_1', $mapped->requestId);
    }

    #[Test]
    public function get_data_or_fail_throws_the_error_response(): void
    {
        $blindpay = $this->client(maxRetries: 0);
        $this->mock->append($this->json(404, ['success' => false, 'code' => 'CUSTOMERS_NOT_FOUND', 'message' => 'customer_not_found']));

        try {
            $blindpay->get('instances/in_000000000000/customers/cu_1')->getDataOrFail();
            $this->fail('expected BlindPayApiException');
        } catch (BlindPayApiException $e) {
            $this->assertSame('CUSTOMERS_NOT_FOUND', $e->error->code);
            $this->assertSame(404, $e->getCode());
        }
    }
}
