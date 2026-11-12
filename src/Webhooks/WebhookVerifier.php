<?php

declare(strict_types=1);

namespace BlindPay\SDK\Webhooks;

/**
 * Verifies BlindPay webhook signatures (Svix scheme). Needs only the endpoint's signing secret,
 * not an API client.
 *
 * Pass the raw request body exactly as received (e.g. file_get_contents('php://input')), never
 * re-encoded JSON, and the request headers. Deduplicate deliveries on the `svix-id` header, and
 * never log the `whsec_` secret.
 *
 *     if (! WebhookVerifier::verify($rawBody, getallheaders(), $secret)) {
 *         http_response_code(400);
 *         exit;
 *     }
 */
final class WebhookVerifier
{
    /** Default maximum age (and clock skew), in seconds, of the `svix-timestamp` header. */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Whether the payload carries a valid signature from $secret, signed within $tolerance
     * seconds of $now. Never throws: a missing header, a malformed secret, a non-numeric or stale
     * timestamp, or a signature mismatch all return false.
     *
     * Header names are matched case-insensitively; values may be strings or lists of strings (as
     * PSR-7 and Symfony header bags return them). The `webhook-id` / `webhook-timestamp` /
     * `webhook-signature` aliases are accepted when the `svix-*` headers are absent.
     *
     * @param  string  $payload  the raw request body
     * @param  array<array-key, mixed>  $headers  header name => value (a string or a list of strings)
     * @param  string  $secret  the endpoint's signing secret (`whsec_...`)
     * @param  int  $tolerance  maximum allowed |now - timestamp|, in seconds
     * @param  int|null  $now  current Unix time; defaults to time() (override in tests)
     */
    public static function verify(string $payload, array $headers, #[\SensitiveParameter] string $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): bool
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            if (! is_string($name)) {
                continue;
            }
            $values = is_array($value) ? array_values(array_filter($value, 'is_string')) : (is_string($value) ? [$value] : []);
            if ($values !== []) {
                $normalized[strtolower($name)] = $values;
            }
        }

        $id = self::first($normalized, 'svix-id') ?? self::first($normalized, 'webhook-id');
        $timestamp = self::first($normalized, 'svix-timestamp') ?? self::first($normalized, 'webhook-timestamp');
        $signatures = $normalized['svix-signature'] ?? $normalized['webhook-signature'] ?? null;
        if ($id === null || $timestamp === null || $signatures === null) {
            return false;
        }

        return self::verifySignature($payload, $id, $timestamp, implode(' ', $signatures), $secret, $tolerance, $now ?? time());
    }

    /**
     * Verify a delivery and decode it into a typed WebhookEvent. Unknown event types do not fail:
     * they come back with the raw name as `type` and a null `data`.
     *
     *     try {
     *         $event = WebhookVerifier::constructEvent(file_get_contents('php://input'), getallheaders(), $secret);
     *     } catch (WebhookException) {
     *         http_response_code(400);
     *         exit;
     *     }
     *
     * @param  array<string, string|string[]>  $headers
     *
     * @throws WebhookDecodeException when the delivery verified but its body is not a JSON object
     *                                with a `webhook_event`, or its payload does not decode: the
     *                                delivery is authentic, so log it and answer 2xx
     * @throws WebhookException when the signature or timestamp does not verify (answer 400)
     */
    public static function constructEvent(string $payload, array $headers, #[\SensitiveParameter] string $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): WebhookEvent
    {
        if (! self::verify($payload, $headers, $secret, $tolerance, $now)) {
            throw new WebhookException('The webhook signature or timestamp could not be verified.');
        }

        $body = json_decode($payload, true);
        if (! is_array($body) || array_is_list($body)) {
            throw new WebhookDecodeException('The webhook body is not a JSON object.');
        }

        return WebhookEvent::fromArray($body);
    }

    /**
     * @internal Shared by verify() and the deprecated BlindPay::verifyWebhookSignature().
     */
    public static function verifySignature(string $payload, string $id, string $timestamp, string $signatureHeader, #[\SensitiveParameter] string $secret, int $tolerance, int $now): bool
    {
        if (preg_match('/^\d{1,18}$/', $timestamp) !== 1 || abs($now - (int) $timestamp) > $tolerance) {
            return false;
        }

        $key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);
        if ($key === false || $key === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$payload}", $key, true));

        foreach (explode(' ', $signatureHeader) as $entry) {
            [$version, $signature] = array_pad(explode(',', $entry, 2), 2, '');
            if ($version === 'v1' && strlen($signature) === strlen($expected) && hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, string[]> $headers */
    private static function first(array $headers, string $name): ?string
    {
        return $headers[$name][0] ?? null;
    }
}
