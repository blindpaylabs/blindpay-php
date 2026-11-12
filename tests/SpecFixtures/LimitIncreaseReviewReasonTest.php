<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\SpecFixtures;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Resources\Customers\LimitIncreaseRequest;
use BlindPay\SDK\Webhooks\WebhookEvent;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `review_reason` (added to GetCustomerLimitIncreaseOut and both limitIncrease.* webhooks) decodes
 * from the shared fixtures: populated on the full variant, null when the API sends null.
 */
final class LimitIncreaseReviewReasonTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__.'/../Fixtures/spec';

    /** @return array<string, mixed> variant name => body */
    private static function responseVariants(): array
    {
        $file = self::FIXTURE_DIR.'/responses/get__instances_instance_id_customers_customer_id_limit_increase.json';

        return json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR)['responses']['200']['variants'];
    }

    /** @return array<string, mixed> variant name => body */
    private static function webhookVariants(string $event): array
    {
        return json_decode((string) file_get_contents(self::FIXTURE_DIR."/webhooks/{$event}.json"), true, flags: JSON_THROW_ON_ERROR)['variants'];
    }

    /** @return list<LimitIncreaseRequest> */
    private static function get(mixed $body): array
    {
        $client = new BlindPay(
            apiKey: 'test_key',
            instanceId: 'in_000000000000',
            maxRetries: 0,
            handler: new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR))]),
        );

        return $client->customers->getLimitIncreaseRequests('cu_000000000000')->getDataOrFail();
    }

    #[Test]
    public function it_decodes_review_reason_on_the_get_response(): void
    {
        $variants = self::responseVariants();
        $expected = $variants['full'][0]['review_reason'];
        $this->assertIsString($expected);

        $this->assertSame($expected, self::get($variants['full'])[0]->reviewReason);
        $this->assertNull(self::get($variants['nullables_null'])[0]->reviewReason);
        $this->assertNull(self::get($variants['required_only'])[0]->reviewReason);
    }

    /** @return array<string, array{string}> */
    public static function webhookEvents(): array
    {
        return ['limitIncrease.new' => ['limitIncrease.new'], 'limitIncrease.update' => ['limitIncrease.update']];
    }

    #[Test]
    #[DataProvider('webhookEvents')]
    public function it_decodes_review_reason_on_the_limit_increase_webhooks(string $event): void
    {
        $variants = self::webhookVariants($event);
        $expected = $variants['full']['review_reason'];
        $this->assertIsString($expected);

        $full = WebhookEvent::fromArray($variants['full'])->data;
        $this->assertInstanceOf(LimitIncreaseRequest::class, $full);
        $this->assertSame($expected, $full->reviewReason);

        $null = WebhookEvent::fromArray($variants['nullables_null'])->data;
        $this->assertInstanceOf(LimitIncreaseRequest::class, $null);
        $this->assertNull($null->reviewReason);
    }
}
