<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Internal;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\BlindPayException;
use BlindPay\SDK\Internal\Transport;
use BlindPay\SDK\Resources\Customers\ListCustomersInput;
use BlindPay\SDK\Webhooks\WebhookException;
use BlindPay\SDK\Webhooks\WebhookVerifier;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ReflectionMethod;
use SensitiveParameter;
use Throwable;

/**
 * C13: the API key and the webhook secret never show up in dumps of the client or in exception
 * stack traces, and transport errors never carry the request's query string.
 */
final class SecretHygieneTest extends TestCase
{
    private const API_KEY = 'sk_test_hygiene_0123456789abcdef';

    private const WEBHOOK_SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

    private string|false $ignoreArgs = false;

    protected function setUp(): void
    {
        // php.ini-production turns this on; the stock Docker images and php.ini-development leave
        // it off, so stack traces record every argument. Test the leaky setting.
        $this->ignoreArgs = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
    }

    protected function tearDown(): void
    {
        if ($this->ignoreArgs !== false) {
            ini_set('zend.exception_ignore_args', $this->ignoreArgs);
        }
    }

    /** Everything a stack trace exposes, as an error tracker or a log line would render it. */
    private static function traceText(Throwable $e): string
    {
        return $e->getTraceAsString()."\n".print_r($e->getTrace(), true)."\n".json_encode($e->getTrace());
    }

    #[Test]
    public function it_keeps_the_api_key_out_of_constructor_stack_traces(): void
    {
        try {
            new BlindPay(apiKey: self::API_KEY, instanceId: 'in_000000000000', timeout: -1);
            $this->fail('expected a BlindPayException');
        } catch (BlindPayException $e) {
            $trace = self::traceText($e);
            $this->assertStringContainsString('in_000000000000', $trace, 'arguments are recorded in this test');
            $this->assertStringNotContainsString(self::API_KEY, $trace);
            $this->assertStringNotContainsString(substr(self::API_KEY, 0, 12), $trace);
        }
    }

    #[Test]
    public function it_keeps_the_webhook_secret_out_of_verifier_stack_traces(): void
    {
        try {
            WebhookVerifier::constructEvent('{}', ['svix-id' => 'msg_1', 'svix-timestamp' => '1', 'svix-signature' => 'v1,x'], self::WEBHOOK_SECRET, now: 1);
            $this->fail('expected a WebhookException');
        } catch (WebhookException $e) {
            $trace = self::traceText($e);
            $this->assertStringContainsString('msg_1', $trace, 'arguments are recorded in this test');
            $this->assertStringNotContainsString(self::WEBHOOK_SECRET, $trace);
            $this->assertStringNotContainsString(substr(self::WEBHOOK_SECRET, 0, 12), $trace);
        }
    }

    #[Test]
    public function it_marks_every_key_and_secret_parameter_as_sensitive(): void
    {
        $parameters = [
            [BlindPay::class, '__construct', 'apiKey'],
            [BlindPay::class, 'verifyWebhookSignature', 'secret'],
            [Transport::class, '__construct', 'apiKey'],
            [WebhookVerifier::class, 'verify', 'secret'],
            [WebhookVerifier::class, 'constructEvent', 'secret'],
            [WebhookVerifier::class, 'verifySignature', 'secret'],
        ];
        foreach ($parameters as [$class, $method, $name]) {
            $matches = array_values(array_filter((new ReflectionMethod($class, $method))->getParameters(), fn ($p) => $p->getName() === $name));
            $this->assertCount(1, $matches, "{$class}::{$method}(\${$name})");
            $this->assertNotEmpty($matches[0]->getAttributes(SensitiveParameter::class), "{$class}::{$method}(\${$name}) needs #[\\SensitiveParameter]");
        }
    }

    #[Test]
    public function it_never_shows_the_api_key_in_a_dump_export_or_cast_of_the_client(): void
    {
        $client = new BlindPay(apiKey: self::API_KEY, instanceId: 'in_000000000000');

        ob_start();
        var_dump($client);
        $varDump = (string) ob_get_clean();
        // The resources point back at the client: var_export() warns about the cycle but still prints.
        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            $varExport = var_export($client, true);
        } finally {
            restore_error_handler();
        }
        $dumps = [
            'var_dump' => $varDump,
            'print_r' => print_r($client, true),
            'var_export' => (string) $varExport,
            '(array)' => print_r((array) $client, true),
            'json_encode' => (string) json_encode($client),
        ];

        try {
            $dumps['serialize'] = serialize($client);
        } catch (Throwable) {
            // The Guzzle client inside is not serializable; nothing was produced.
        }

        foreach ($dumps as $how => $text) {
            $this->assertStringNotContainsString(self::API_KEY, $text, $how);
        }
    }

    #[Test]
    public function it_strips_query_strings_from_transport_error_messages(): void
    {
        $handler = new MockHandler([
            static function (RequestInterface $request): never {
                // Guzzle's curl handler appends the full request URL to its error messages.
                throw new ConnectException("cURL error 6: Could not resolve host: api.blindpay.com (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for {$request->getUri()}", $request);
            },
        ]);
        $client = new BlindPay(apiKey: self::API_KEY, instanceId: 'in_000000000000', maxRetries: 0, handler: $handler);

        $error = $client->customers->list(new ListCustomersInput(fullName: 'Jane Private', customerId: 'cu_000000000000'))->error;

        $this->assertNotNull($error);
        $this->assertTrue($error->isNetworkError());
        $this->assertStringContainsString('for https://api.blindpay.com/v1/instances/in_000000000000/customers', $error->message);
        $this->assertStringNotContainsString('?', $error->message);
        $this->assertStringNotContainsString('Jane', $error->message);
        $this->assertStringNotContainsString('cu_000000000000', $error->message);
    }
}
