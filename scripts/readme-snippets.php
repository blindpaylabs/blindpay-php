<?php

declare(strict_types=1);

/**
 * Extracts every ```php block of README.md into one PHP file of functions, so the README's code can
 * be type-checked (CI runs PHPStan level 8 on the output) and executed by a test harness.
 *
 * Every block must be preceded by a header comment that names the function to wrap it in:
 *
 *     <!-- snippet: readme_retries(\BlindPay\SDK\BlindPay $blindpay, \BlindPay\SDK\Resources\Payouts\CreateEvmPayoutInput $input): void
 *     $response = $blindpay->payouts->createEvm($input);
 *     -->
 *
 * The first line is the signature. Further lines are setup code placed before the block, except
 * lines that start with `return `, which go after it. A block that starts with `<?php` loses its
 * opening tag, `declare` and `require` lines. The `use` imports of all blocks are merged at the
 * top of the file, as a reader who copies the README in order would have them.
 *
 * Usage: php scripts/readme-snippets.php [--analyse] [output.php]
 *   output.php defaults to <tmp>/blindpay-readme-snippets.php (outside the repository);
 *   --analyse then runs PHPStan level 8 on it and exits with PHPStan's status (composer run readme:check).
 */
$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$analyse = in_array('--analyse', $args, true);
$paths = array_values(array_filter($args, static fn (string $a): bool => ! str_starts_with($a, '--')));
$out = $paths[0] ?? sys_get_temp_dir().'/blindpay-readme-snippets.php';
$readme = (string) file_get_contents($root.'/README.md');

preg_match_all('/(?:<!-- snippet: (.*?)-->\s*\n)?```php\n(.*?)```/s', $readme, $blocks, PREG_SET_ORDER);
if ($blocks === []) {
    fwrite(STDERR, "[readme-snippets] FAIL: README.md has no php blocks\n");
    exit(1);
}

$uses = [];
$functions = [];
foreach ($blocks as $i => [, $header, $code]) {
    if (trim($header) === '') {
        $first = strtok(ltrim($code), "\n");
        fwrite(STDERR, "[readme-snippets] FAIL: php block #{$i} (\"{$first}\") has no <!-- snippet: ... --> header\n");
        exit(1);
    }
    $headerLines = explode("\n", trim($header));
    $signature = trim((string) array_shift($headerLines));
    $before = [];
    $after = [];
    foreach ($headerLines as $line) {
        if (trim($line) === '') {
            continue;
        }
        if (str_starts_with(ltrim($line), 'return ')) {
            $after[] = '    '.trim($line);
        } else {
            $before[] = '    '.trim($line);
        }
    }

    $body = [];
    foreach (explode("\n", rtrim($code)) as $line) {
        if (preg_match('/^use [A-Za-z0-9_\\\\]+;$/', $line) === 1) {
            $uses[$line] = true;

            continue;
        }
        if (preg_match('/^(<\?php|declare\(strict_types=1\);|require )/', $line) === 1) {
            continue;
        }
        $body[] = $line === '' ? '' : '    '.$line;
    }
    while ($body !== [] && $body[0] === '') {
        array_shift($body);
    }

    $functions[] = "function {$signature}\n{\n".implode("\n", [...$before, ...$body, ...$after])."\n}\n";
}

ksort($uses);
$stubs = <<<'PHP'
    // The application functions the README calls.
    function markPaid(string $id): void
    {
        echo "paid:{$id}\n";
    }

    function markFailed(string $id): void
    {
        echo "failed:{$id}\n";
    }

    function markRefunded(string $id): void
    {
        echo "refunded:{$id}\n";
    }

    PHP;

if (! is_dir(dirname($out))) {
    mkdir(dirname($out), 0777, true);
}
file_put_contents($out, "<?php\n\ndeclare(strict_types=1);\n\n// Generated from README.md by scripts/readme-snippets.php. Do not edit.\n\n"
    .implode("\n", array_keys($uses))."\n\n".implode("\n", $functions)."\n".$stubs);
fwrite(STDOUT, '[readme-snippets] '.count($functions)." snippets -> {$out}\n");

if ($analyse) {
    passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/vendor/bin/phpstan').' analyse --no-progress --memory-limit=1G --level=8 '.escapeshellarg($out), $status);
    exit($status);
}
