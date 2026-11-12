<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Internal;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\BlindPayApiException;
use BlindPay\SDK\BlindPayException;
use BlindPay\SDK\Internal\BlindPayException as InternalBlindPayException;
use BlindPay\SDK\Resources\Payouts\ListPayoutsInput;
use BlindPay\SDK\Types\RequestOptions;
use BlindPay\SDK\Webhooks\WebhookException;
use BlindPay\SDK\Webhooks\WebhookVerifier;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Every exception the SDK throws is a public \BlindPay\SDK\BlindPayException and, for existing
 * catch blocks, still an Internal\BlindPayException.
 */
final class BlindPayExceptionTest extends TestCase
{
    /** @return array<string, callable(): mixed> */
    private static function throwers(): array
    {
        $failing = new BlindPay(
            apiKey: 'test_key',
            instanceId: 'in_000000000000',
            maxRetries: 0,
            handler: new MockHandler([new Response(404, [], '{"success":false,"code":"NOT_FOUND","message":"not_found"}')]),
        );

        return [
            'empty API key' => fn () => new BlindPay(apiKey: '', instanceId: 'in_000000000000'),
            'empty instance ID' => fn () => new BlindPay(apiKey: 'test_key', instanceId: ''),
            'bad client option' => fn () => new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000', maxRetries: -1),
            'bad request option' => fn () => new RequestOptions(timeout: 0),
            'getDataOrFail' => fn () => $failing->instances->get()->getDataOrFail(),
            'backward auto-paging' => fn () => iterator_to_array($failing->payouts->listAutoPaging(new ListPayoutsInput(endingBefore: 'po_000000000000'))),
            'webhook' => fn () => WebhookVerifier::constructEvent('{}', [], 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw'),
        ];
    }

    #[Test]
    public function it_throws_the_public_exception_everywhere(): void
    {
        foreach (self::throwers() as $case => $throw) {
            try {
                $throw();
                $this->fail("{$case}: nothing was thrown");
            } catch (Throwable $e) {
                $this->assertInstanceOf(BlindPayException::class, $e, $case);
                $this->assertInstanceOf(InternalBlindPayException::class, $e, $case);
            }
        }
    }

    #[Test]
    public function it_keeps_the_subclasses_and_factories_public(): void
    {
        $this->assertTrue(is_subclass_of(BlindPayApiException::class, BlindPayException::class));
        $this->assertTrue(is_subclass_of(WebhookException::class, BlindPayException::class));
        $this->assertInstanceOf(BlindPayException::class, BlindPayException::configuration('x'));
        $this->assertSame('CONFIGURATION_ERROR', BlindPayException::configuration('x')->getErrorCode());
        $this->assertSame('VALIDATION_ERROR', BlindPayException::validation('x')->getErrorCode());
    }
}
