<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Webhooks;

use BlindPay\SDK\Resources\Customers\BusinessWithStandardKYB;
use BlindPay\SDK\Resources\Customers\IndividualWithEnhancedKYC;
use BlindPay\SDK\Resources\Customers\IndividualWithStandardKYC;
use BlindPay\SDK\Resources\Payouts\CreateEvmPayoutResponse;
use BlindPay\SDK\Resources\Webhooks\WebhookEvents;
use BlindPay\SDK\Webhooks\WebhookEvent;
use BlindPay\SDK\Webhooks\WebhookException;
use BlindPay\SDK\Webhooks\WebhookVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * constructEvent() over every variant of the shared webhook fixtures (all 28 events in the spec,
 * plus an event name this SDK version does not know).
 */
final class WebhookEventTest extends TestCase
{
    private const SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

    private const NOW = 1_700_000_000;

    /** @return array<string, string> the svix headers for $payload, signed with SECRET at NOW */
    private static function sign(string $payload, string $id = 'msg_000000000000'): array
    {
        $key = base64_decode(substr(self::SECRET, 6));
        $signature = base64_encode(hash_hmac('sha256', $id.'.'.self::NOW.'.'.$payload, $key, true));

        return ['svix-id' => $id, 'svix-timestamp' => (string) self::NOW, 'svix-signature' => "v1,{$signature}"];
    }

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function fixtures(): array
    {
        $cases = [];
        foreach (glob(__DIR__.'/../Fixtures/spec/webhooks/*.json') as $file) {
            $fixture = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            foreach ($fixture['variants'] as $variant => $body) {
                $cases[basename($file, '.json')." [{$variant}]"] = [basename($file, '.json'), $variant, $body];
            }
        }

        return $cases;
    }

    #[Test]
    public function the_fixtures_cover_every_event_type_in_the_sdk(): void
    {
        $events = array_unique(array_map(fn (array $case) => $case[0], self::fixtures()));
        $known = array_map(fn (WebhookEvents $e) => $e->value, WebhookEvents::cases());

        $this->assertEqualsCanonicalizing([...$known, '_unknown_event'], array_values($events));
        $this->assertCount(28, $known);
    }

    #[Test]
    #[DataProvider('fixtures')]
    public function it_constructs_a_typed_event_from_every_fixture_variant(string $event, string $variant, array $body): void
    {
        $payload = json_encode($body, JSON_THROW_ON_ERROR);

        $constructed = WebhookVerifier::constructEvent($payload, self::sign($payload), self::SECRET, now: self::NOW);

        $this->assertSame($body, $constructed->raw);
        if ($event === '_unknown_event') {
            $this->assertFalse($constructed->isKnown());
            $this->assertSame($body['webhook_event'], $constructed->type);
            $this->assertNull($constructed->data);

            return;
        }
        $this->assertTrue($constructed->isKnown());
        $this->assertSame(WebhookEvents::from($event), $constructed->type);
        $this->assertIsObject($constructed->data);
    }

    #[Test]
    public function payloads_get_the_documented_types(): void
    {
        $decode = fn (string $event) => WebhookEvent::fromArray(json_decode((string) file_get_contents(__DIR__."/../Fixtures/spec/webhooks/{$event}.json"), true)['variants']['full']);

        $this->assertInstanceOf(CreateEvmPayoutResponse::class, $decode('payout.complete')->data);
        $this->assertContains($decode('customer.new')->data::class, [IndividualWithStandardKYC::class, IndividualWithEnhancedKYC::class, BusinessWithStandardKYB::class]);
    }

    #[Test]
    public function it_throws_on_a_bad_signature_a_stale_timestamp_or_a_non_object_body(): void
    {
        $payload = '{"webhook_event":"payout.new"}';
        $cases = [
            'tampered' => [$payload.' ', self::sign($payload), self::NOW],
            'stale' => [$payload, self::sign($payload), self::NOW + 301],
            'missing headers' => [$payload, [], self::NOW],
            'not json' => ['nope', self::sign('nope'), self::NOW],
            'json list' => ['[1]', self::sign('[1]'), self::NOW],
            'no webhook_event' => ['{"id":"x"}', self::sign('{"id":"x"}'), self::NOW],
        ];

        foreach ($cases as $name => [$body, $headers, $now]) {
            try {
                WebhookVerifier::constructEvent($body, $headers, self::SECRET, now: $now);
                $this->fail("{$name}: expected WebhookException");
            } catch (WebhookException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function a_known_event_with_an_undecodable_payload_throws(): void
    {
        $payload = '{"webhook_event":"payout.complete","id":"po_1"}';

        $this->expectException(WebhookException::class);

        WebhookVerifier::constructEvent($payload, self::sign($payload), self::SECRET, now: self::NOW);
    }
}
