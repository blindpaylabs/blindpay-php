<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\Webhooks;

use BlindPay\SDK\BlindPay;
use BlindPay\SDK\Webhooks\WebhookVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Runs the shared Svix vectors (tests/Fixtures/webhook-vectors.json, generated and self-verified
 * with the official svix npm package) plus PHP-specific header handling.
 */
final class WebhookVerifierTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function fixture(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../Fixtures/webhook-vectors.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function vectors(): array
    {
        $cases = [];
        foreach (self::fixture()['vectors'] as $vector) {
            $cases[$vector['name']] = [$vector];
        }

        return $cases;
    }

    #[Test]
    public function the_shared_vector_file_has_all_fourteen_cases(): void
    {
        $this->assertCount(14, self::fixture()['vectors']);
        $this->assertSame(WebhookVerifier::DEFAULT_TOLERANCE, self::fixture()['tolerance_seconds']);
    }

    #[Test]
    #[DataProvider('vectors')]
    public function it_matches_the_shared_svix_vector(array $vector): void
    {
        $valid = WebhookVerifier::verify($vector['payload'], $vector['headers'], $vector['secret'], now: $vector['now']);

        $this->assertSame($vector['expect'] === 'valid', $valid);
    }

    private static function valid(): array
    {
        return self::fixture()['vectors'][0];
    }

    #[Test]
    public function header_names_are_case_insensitive_and_values_may_be_lists(): void
    {
        $v = self::valid();
        $headers = [
            'Svix-Id' => [$v['headers']['svix-id']],
            'SVIX-TIMESTAMP' => [$v['headers']['svix-timestamp']],
            'Svix-Signature' => ['v1,AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', $v['headers']['svix-signature']],
        ];

        $this->assertTrue(WebhookVerifier::verify($v['payload'], $headers, $v['secret'], now: $v['now']));
    }

    #[Test]
    public function it_accepts_the_webhook_header_aliases(): void
    {
        $v = self::valid();
        $headers = [
            'webhook-id' => $v['headers']['svix-id'],
            'webhook-timestamp' => $v['headers']['svix-timestamp'],
            'webhook-signature' => $v['headers']['svix-signature'],
        ];

        $this->assertTrue(WebhookVerifier::verify($v['payload'], $headers, $v['secret'], now: $v['now']));
    }

    #[Test]
    public function malformed_secrets_return_false_instead_of_throwing(): void
    {
        $v = self::valid();

        foreach (['', 'whsec_', 'whsec_not base64!', 'no-underscore'] as $secret) {
            $this->assertFalse(WebhookVerifier::verify($v['payload'], $v['headers'], $secret, now: $v['now']), $secret);
        }
    }

    #[Test]
    public function a_custom_tolerance_is_honoured(): void
    {
        $v = self::valid();

        $this->assertFalse(WebhookVerifier::verify($v['payload'], $v['headers'], $v['secret'], tolerance: 10, now: $v['now'] + 11));
        $this->assertTrue(WebhookVerifier::verify($v['payload'], $v['headers'], $v['secret'], tolerance: 10, now: $v['now'] + 10));
    }

    #[Test]
    public function the_deprecated_client_method_verifies_real_headers_and_rejects_replays(): void
    {
        $blindpay = new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000');
        $secret = self::valid()['secret'];
        $payload = '{"webhook_event":"payout.complete","id":"pa_000000000000"}';
        $id = 'msg_000000000000';
        $sign = static fn (int $ts): string => base64_encode(hash_hmac('sha256', "{$id}.{$ts}.{$payload}", base64_decode(substr($secret, 6)), true));
        $now = time();

        // the header exactly as Svix sends it (rejected by every earlier version)
        $this->assertTrue($blindpay->verifyWebhookSignature($secret, $id, (string) $now, $payload, 'v1,'.$sign($now)));
        // a pre-stripped bare signature, which callers had to pass before, keeps working
        $this->assertTrue($blindpay->verifyWebhookSignature($secret, $id, (string) $now, $payload, $sign($now)));
        // replay protection: a correctly signed but stale delivery is rejected
        $stale = $now - 3600;
        $this->assertFalse($blindpay->verifyWebhookSignature($secret, $id, (string) $stale, $payload, 'v1,'.$sign($stale)));
        // tampering, and a secret without the whsec_ prefix's underscore, return false
        $this->assertFalse($blindpay->verifyWebhookSignature($secret, $id, (string) $now, $payload.' ', 'v1,'.$sign($now)));
        $this->assertFalse($blindpay->verifyWebhookSignature('nounderscore', $id, (string) $now, $payload, 'v1,'.$sign($now)));
    }

    #[Test]
    public function it_honours_a_custom_tolerance_in_the_deprecated_client_method(): void
    {
        $blindpay = new BlindPay(apiKey: 'test_key', instanceId: 'in_000000000000');
        $secret = self::valid()['secret'];
        $payload = '{"webhook_event":"payout.complete","id":"pa_000000000000"}';
        $id = 'msg_000000000000';
        $tenMinutesAgo = time() - 600;
        $signature = 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$tenMinutesAgo}.{$payload}", base64_decode(substr($secret, 6)), true));

        // outside the default 300 s window
        $this->assertFalse($blindpay->verifyWebhookSignature($secret, $id, (string) $tenMinutesAgo, $payload, $signature));
        // inside a wider window the caller chose; a narrower one still rejects it
        $this->assertTrue($blindpay->verifyWebhookSignature($secret, $id, (string) $tenMinutesAgo, $payload, $signature, tolerance: 900));
        $this->assertFalse($blindpay->verifyWebhookSignature($secret, $id, (string) $tenMinutesAgo, $payload, $signature, tolerance: 60));
    }
}
