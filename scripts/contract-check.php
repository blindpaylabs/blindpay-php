<?php

/**
 * Contract check: verifies the SDK's declared wire keys against the committed
 * OpenAPI spec snapshot at .api-sync/spec-snapshot.json.
 *
 * Direction A (hard fail): every string key the SDK reads via `$var['key']` inside a
 * fromArray() method, or writes via `'key' => ...` inside a toArray() method, must
 * exist as a property name somewhere in the snapshot -- unless allow-listed in
 * .api-sync/contract-allowlist.json.
 *
 * Direction B (mixed):
 *   - Hard fail: every webhook topic in the snapshot's `webhooks` map must have a
 *     matching case value in BlindPay\SDK\Resources\Webhooks\WebhookEvents.
 *   - Warning only: spec properties that no fromArray()/toArray() in the SDK reads
 *     or writes at all (informational, does not fail CI).
 *
 * Usage: php scripts/contract-check.php
 * No Composer dependencies -- uses only the Tokenizer/JSON extensions PHP ships with.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$specPath = $root.'/.api-sync/spec-snapshot.json';
$allowlistPath = $root.'/.api-sync/contract-allowlist.json';

function fail(string $message): never
{
    fwrite(STDERR, "\n[contract-check] FAIL: {$message}\n");
    exit(1);
}

if (! is_file($specPath)) {
    fail("spec snapshot not found at {$specPath}");
}

if (! is_file($allowlistPath)) {
    fail("allow-list not found at {$allowlistPath}");
}

$spec = json_decode(file_get_contents($specPath), true, 512, JSON_THROW_ON_ERROR);
$allowlistRaw = json_decode(file_get_contents($allowlistPath), true, 512, JSON_THROW_ON_ERROR);

$allowlist = [];
foreach ($allowlistRaw['entries'] ?? [] as $entry) {
    foreach (['schema', 'field', 'reason', 'owner'] as $required) {
        if (empty($entry[$required])) {
            fail("allow-list entry missing required key '{$required}': ".json_encode($entry));
        }
    }
    $allowlist[$entry['schema'].'.'.$entry['field']] = $entry;
}

/**
 * Recursively collect every key that appears as an OpenAPI "properties" map key,
 * anywhere in the document.
 */
function collectSpecProperties(mixed $node, array &$out): void
{
    if (is_array($node)) {
        if (isset($node['properties']) && is_array($node['properties'])) {
            foreach ($node['properties'] as $key => $_) {
                $out[$key] = true;
            }
        }
        foreach ($node as $value) {
            collectSpecProperties($value, $out);
        }
    }
}

$specProperties = [];
collectSpecProperties($spec, $specProperties);

$webhookTopics = array_keys($spec['webhooks'] ?? []);
if (empty($webhookTopics)) {
    fail('spec snapshot has no `webhooks` map -- refusing to run a check that could silently pass');
}

/**
 * Tokenize a PHP file and extract:
 *   - declaredKeys: [[class, field, kind]] where kind is 'read' (fromArray) or 'write' (toArray)
 *   - webhookEnumValues: string[] of case values, only populated when scanning Webhooks.php
 */
function scanFile(string $path): array
{
    $source = file_get_contents($path);
    $tokens = token_get_all($source);

    // Drop whitespace/comments so adjacency checks ('x' immediately followed by =>) are simple.
    $tokens = array_values(array_filter($tokens, function ($t) {
        if (! is_array($t)) {
            return true;
        }

        return ! in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }));

    $braceDepth = 0;
    $classStack = [];    // [[depth, name]]
    $functionStack = []; // [[depth, name]]
    $pendingClassName = null;
    $pendingFunctionName = null;
    $inEnumCaseList = false;

    $declaredKeys = [];
    $webhookEnumValues = [];

    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        $id = is_array($t) ? $t[0] : $t;
        $text = is_array($t) ? $t[1] : $t;

        if (is_array($t) && in_array($id, [T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT], true)) {
            // Next T_STRING token is the declared name.
            for ($j = $i + 1; $j < $count; $j++) {
                $nt = $tokens[$j];
                if (is_array($nt) && $nt[0] === T_STRING) {
                    $pendingClassName = $nt[1];

                    break;
                }
                if (! is_array($nt) && $nt === '{') {
                    break;
                }
            }
        }

        if (is_array($t) && $id === T_FUNCTION) {
            for ($j = $i + 1; $j < $count; $j++) {
                $nt = $tokens[$j];
                if (is_array($nt) && $nt[0] === T_STRING) {
                    $pendingFunctionName = $nt[1];

                    break;
                }
                if (! is_array($nt) && $nt === '{') {
                    break;
                }
            }
        }

        $currentClass = end($classStack)[1] ?? null;
        $currentFunction = end($functionStack)[1] ?? null;

        if (! is_array($t) && $text === '{') {
            $braceDepth++;
            if ($pendingClassName !== null) {
                $classStack[] = [$braceDepth, $pendingClassName];
                $pendingClassName = null;
            } elseif ($pendingFunctionName !== null) {
                $functionStack[] = [$braceDepth, $pendingFunctionName];
                $pendingFunctionName = null;
            }

            continue;
        }

        if (! is_array($t) && $text === '}') {
            if (! empty($functionStack) && end($functionStack)[0] === $braceDepth) {
                array_pop($functionStack);
            } elseif (! empty($classStack) && end($classStack)[0] === $braceDepth) {
                array_pop($classStack);
            }
            $braceDepth--;

            continue;
        }

        // WebhookEvents enum cases: `case NAME = 'value';` at the top of the enum body.
        if ($currentClass === 'WebhookEvents' && is_array($t) && $id === T_CASE) {
            for ($j = $i + 1; $j < $count && $tokens[$j] !== ';'; $j++) {
                $nt = $tokens[$j];
                if (is_array($nt) && $nt[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $webhookEnumValues[] = trim($nt[1], "'\"");

                    break;
                }
            }

            continue;
        }

        if ($currentFunction === 'fromArray' && $currentClass !== null) {
            if (is_array($t) && $id === T_VARIABLE
                && isset($tokens[$i + 1], $tokens[$i + 2], $tokens[$i + 3])
                && $tokens[$i + 1] === '['
                && is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING
                && $tokens[$i + 3] === ']'
            ) {
                $key = trim($tokens[$i + 2][1], "'\"");
                $declaredKeys[] = [$currentClass, $key, 'read'];
            }
        }

        if ($currentFunction === 'toArray' && $currentClass !== null) {
            if (is_array($t) && $id === T_CONSTANT_ENCAPSED_STRING
                && isset($tokens[$i + 1])
                && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] === T_DOUBLE_ARROW
            ) {
                $key = trim($text, "'\"");
                $declaredKeys[] = [$currentClass, $key, 'write'];
            }
        }
    }

    return [$declaredKeys, $webhookEnumValues];
}

function phpFilesUnder(string $dir): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
}

$scanDirs = [$root.'/src/Resources', $root.'/src/Types'];
$allDeclaredKeys = [];
$webhookEnumValues = [];

foreach ($scanDirs as $dir) {
    if (! is_dir($dir)) {
        continue;
    }
    foreach (phpFilesUnder($dir) as $file) {
        [$keys, $enumValues] = scanFile($file);
        foreach ($keys as $k) {
            $allDeclaredKeys[] = $k;
        }
        foreach ($enumValues as $v) {
            $webhookEnumValues[] = $v;
        }
    }
}

// ---- Direction A: every declared wire key must exist in the spec, or be allow-listed ----
$hardFailures = [];
$seen = [];
foreach ($allDeclaredKeys as [$class, $field, $kind]) {
    $dedupeKey = "{$class}.{$field}";
    if (isset($seen[$dedupeKey])) {
        continue;
    }
    $seen[$dedupeKey] = true;

    if (isset($specProperties[$field])) {
        continue;
    }

    if (isset($allowlist[$dedupeKey])) {
        continue;
    }

    $hardFailures[] = "{$class}.{$field} ({$kind}) -- not found anywhere in the spec snapshot and not allow-listed";
}

// ---- Direction B (hard): every spec webhook topic must be modeled by WebhookEvents ----
$webhookEnumValueSet = array_flip($webhookEnumValues);
$missingWebhookEvents = [];
foreach ($webhookTopics as $topic) {
    if (! isset($webhookEnumValueSet[$topic])) {
        $missingWebhookEvents[] = $topic;
    }
}

// ---- Direction B (soft): spec properties the SDK never reads or writes at all ----
$declaredFieldSet = [];
foreach ($allDeclaredKeys as [, $field]) {
    $declaredFieldSet[$field] = true;
}
$unmodeledSpecFields = array_values(array_diff(array_keys($specProperties), array_keys($declaredFieldSet)));
sort($unmodeledSpecFields);

// ---- Report ----
$ok = true;

if (! empty($hardFailures)) {
    $ok = false;
    fwrite(STDERR, "\n[contract-check] Direction A FAILED -- SDK declares wire keys the spec snapshot does not have:\n");
    foreach ($hardFailures as $line) {
        fwrite(STDERR, "  - {$line}\n");
    }
    fwrite(STDERR, "\nFix the SDK to match the spec, or add a reasoned, owned entry to .api-sync/contract-allowlist.json\nif this is a genuine pre-existing divergence unrelated to the change you're making.\n");
}

if (! empty($missingWebhookEvents)) {
    $ok = false;
    fwrite(STDERR, "\n[contract-check] Direction B FAILED -- webhook topics in the spec with no matching WebhookEvents case:\n");
    foreach ($missingWebhookEvents as $topic) {
        fwrite(STDERR, "  - {$topic}\n");
    }
}

if (! empty($unmodeledSpecFields)) {
    fwrite(STDOUT, "\n[contract-check] warning: ".count($unmodeledSpecFields)." spec properties are not read or written anywhere in the SDK (informational, not a failure).\n");
}

if ($ok) {
    fwrite(STDOUT, "\n[contract-check] OK -- ".count($seen).' declared wire keys checked, '.count($webhookTopics)." webhook topics checked.\n");
    exit(0);
}

exit(1);
