<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\ApiSync;

use BlindPay\SDK\Types\ErrorCode;
use BlindPay\SDK\Types\ErrorCodeInfo;
use BlindPay\SDK\Types\ErrorResponse;
use BlindPay\SDK\Types\RetryClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

if (! defined('API_SYNC_LIB_ONLY')) {
    define('API_SYNC_LIB_ONLY', true);
}
require_once __DIR__.'/../../scripts/api-sync.php';

/**
 * src/Types/ErrorCode.php is generated from the `Errors` table of the committed spec snapshot, and
 * ErrorResponse::retryClass() reads it.
 */
final class ErrorCodesTest extends TestCase
{
    private const SNAPSHOT = __DIR__.'/../../.api-sync/spec-snapshot.json';

    /** @return array<string, mixed> */
    private static function snapshot(): array
    {
        return json_decode((string) file_get_contents(self::SNAPSHOT), true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function it_keeps_the_committed_class_equal_to_the_generated_one(): void
    {
        $rows = parseErrorCodeTable(self::snapshot());

        $this->assertNotEmpty($rows);
        $this->assertSame(renderErrorCodeClass($rows), file_get_contents(__DIR__.'/../../src/Types/ErrorCode.php'));
    }

    #[Test]
    public function it_has_one_constant_and_one_info_entry_per_documented_code(): void
    {
        $rows = parseErrorCodeTable(self::snapshot());
        $constants = (new ReflectionClass(ErrorCode::class))->getConstants();
        unset($constants['INFO']);

        $this->assertSame(array_keys($rows), array_keys($constants));
        $this->assertSame(array_keys($rows), array_values($constants));
        foreach ($rows as $code => $row) {
            $info = ErrorCode::info($code);
            $this->assertInstanceOf(ErrorCodeInfo::class, $info, $code);
            $this->assertSame($row['status'], $info->status, $code);
            $this->assertSame($row['retry'], $info->retry->value, $code);
        }
    }

    #[Test]
    public function it_documents_the_swap_minimum_code(): void
    {
        $this->assertSame('TRANSFERS_SWAP_AMOUNT_BELOW_MINIMUM', ErrorCode::TRANSFERS_SWAP_AMOUNT_BELOW_MINIMUM);
        $this->assertEquals(new ErrorCodeInfo(400, RetryClass::ACTIONABLE), ErrorCode::info(ErrorCode::TRANSFERS_SWAP_AMOUNT_BELOW_MINIMUM));
        $this->assertEquals(new ErrorCodeInfo(429, RetryClass::RETRYABLE), ErrorCode::info(ErrorCode::AUTH_RATE_LIMITED));
    }

    #[Test]
    public function it_stays_open_for_codes_this_version_does_not_know(): void
    {
        $this->assertNull(ErrorCode::info('SOMETHING_NEW_FROM_THE_API'));

        $error = ErrorResponse::fromArray(['success' => false, 'code' => 'SOMETHING_NEW_FROM_THE_API', 'message' => 'something_new']);
        $this->assertSame('SOMETHING_NEW_FROM_THE_API', $error->code);
        $this->assertSame(RetryClass::UNKNOWN, $error->retryClass());
    }

    #[Test]
    public function it_derives_the_retry_class_from_the_code(): void
    {
        $this->assertSame(RetryClass::ACTIONABLE, ErrorResponse::fromArray(['code' => 'TRANSFERS_SWAP_AMOUNT_BELOW_MINIMUM', 'message' => 'fx_amount_below_minimum'])->retryClass());
        $this->assertSame(RetryClass::RETRYABLE, ErrorResponse::fromArray(['code' => 'AUTH_RATE_LIMITED', 'message' => 'fx_quote_rate_limit_exceeded'])->retryClass());
        $this->assertSame(RetryClass::TERMINAL, ErrorResponse::fromArray(['code' => 'AUTH_FORBIDDEN', 'message' => 'forbidden'])->retryClass());
        // Bodies without a code (idempotency conflicts, some legacy paths) and SDK-side errors.
        $this->assertSame(RetryClass::UNKNOWN, ErrorResponse::fromArray(['message' => 'idempotency_key_in_flight'])->retryClass());
        $this->assertSame(RetryClass::UNKNOWN, ErrorResponse::validation('Customer ID cannot be empty')->retryClass());
    }

    #[Test]
    public function it_needs_a_human_for_an_empty_table_or_a_removed_code_and_regenerates_for_a_new_one(): void
    {
        $root = sys_get_temp_dir().'/blindpay-error-codes-'.bin2hex(random_bytes(6));
        mkdir("{$root}/src/Types", 0777, true);
        $spec = self::snapshot();

        try {
            // No file and no Errors tag (the api-sync test fixtures): nothing to do.
            $this->assertSame([[], []], reconcileErrorCodes($root, ['tags' => []]));

            file_put_contents("{$root}/src/Types/ErrorCode.php", renderErrorCodeClass(parseErrorCodeTable($spec)));
            $this->assertSame([[], []], reconcileErrorCodes($root, $spec));

            // The table vanished: fail instead of generating an empty class.
            [, $needsHuman] = reconcileErrorCodes($root, ['tags' => [['name' => 'Errors', 'description' => 'no table']]]);
            $this->assertCount(1, $needsHuman);
            $this->assertStringContainsString('no error code table rows', $needsHuman[0]);

            $index = array_search('Errors', array_column($spec['tags'], 'name'), true);
            $withNew = $spec;
            $withNew['tags'][$index]['description'] .= "\n| `ZZZ_NEW_CODE` | 400 | actionable | A new code. |";
            [$applicable, $needsHuman] = reconcileErrorCodes($root, $withNew);
            $this->assertSame([], $needsHuman);
            $this->assertSame('error-codes-added', $applicable[0]['kind']);
            $this->assertSame(['ZZZ_NEW_CODE'], $applicable[0]['added']);

            $withoutOne = $spec;
            $withoutOne['tags'][$index]['description'] = str_replace('| `AUTH_FORBIDDEN` |', '| `AUTH_FORBIDDEN_RENAMED` |', $spec['tags'][$index]['description']);
            [, $needsHuman] = reconcileErrorCodes($root, $withoutOne);
            $this->assertCount(1, $needsHuman);
            $this->assertStringContainsString('AUTH_FORBIDDEN is no longer', $needsHuman[0]);
        } finally {
            @unlink("{$root}/src/Types/ErrorCode.php");
            @rmdir("{$root}/src/Types");
            @rmdir("{$root}/src");
            @rmdir($root);
        }
    }
}
