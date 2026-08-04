<?php

/**
 * Deterministic OpenAPI -> SDK sync patcher.
 *
 * State-based reconciliation (primary): for every (spec schema[/path] -> SDK class) pair in
 * .api-sync/spec-map.json, every spec property must be present in the SDK class, or be listed
 * in .api-sync/unmodeled.json. For every mapped enum, every spec member value must be present as
 * an SDK case value, or be listed in .api-sync/known-divergences.json. Anything missing is
 * pending drift, regardless of when it appeared.
 *
 * Old-vs-new spec diff (secondary): only used for removal detection (always a hard fail) and
 * version-bump classification.
 *
 * Usage:
 *   php scripts/api-sync.php [--check] [--apply] [--audit-types] [--spec=path] [--report=path]
 * Default mode is --check. Default --spec is .api-sync/spec-current.json.
 * --audit-types is a separate, always-exit-0 mode: it prints every already-modeled property
 * whose CURRENT spec type disagrees with its SDK type (state, not just forward drift), so
 * pre-existing type debt stays visible without blocking CI. See auditTypes() for why this is
 * broader than the blocking check.
 * No Composer dependencies -- uses only the Tokenizer/JSON extensions PHP ships with.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

// ---------------------------------------------------------------------------
// CLI
// ---------------------------------------------------------------------------

function parseArgs(array $argv): array
{
    $opts = ['apply' => false, 'check' => false, 'auditTypes' => false, 'spec' => null, 'report' => null];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--apply') {
            $opts['apply'] = true;
        } elseif ($arg === '--check') {
            $opts['check'] = true;
        } elseif ($arg === '--audit-types') {
            $opts['auditTypes'] = true;
        } elseif (str_starts_with($arg, '--spec=')) {
            $opts['spec'] = substr($arg, strlen('--spec='));
        } elseif (str_starts_with($arg, '--report=')) {
            $opts['report'] = substr($arg, strlen('--report='));
        } else {
            fwrite(STDERR, "[api-sync] unknown argument: {$arg}\n");
            exit(1);
        }
    }

    return $opts;
}

// ---------------------------------------------------------------------------
// Path validation -- every filesystem path this program touches that originates from a CLI
// argument or a config file on disk is resolved and validated here, once, right where it enters
// the program, rather than trusted implicitly at each later read/write call site.
// ---------------------------------------------------------------------------

/**
 * Canonicalize and validate a path this program is about to READ (--spec, and any file the
 * tokenizer scans). Rejects NUL-byte injection and resolves `..`/symlinks via realpath() so every
 * later use operates on one already-validated canonical path instead of re-trusting a raw string.
 */
function resolveReadablePath(string $path, string $argName): string
{
    if ($path === '' || str_contains($path, "\0")) {
        fwrite(STDERR, "[api-sync] FAIL: invalid {$argName} path\n");
        exit(1);
    }
    $real = realpath($path);
    if ($real === false || ! is_file($real)) {
        fwrite(STDERR, "[api-sync] FAIL: {$argName} does not exist or is not a file: {$path}\n");
        exit(1);
    }

    return $real;
}

/**
 * Canonicalize and validate a path this program is about to WRITE (--report). The parent
 * directory must already exist -- this program never creates directories -- and resolving it via
 * realpath() collapses `..`/symlinks so the eventual file_put_contents() target is unambiguous.
 * Deliberately does NOT restrict the result to the repository root: --report is designed to write
 * outside it (CI writes to /tmp; the determinism proof writes into scratch copies elsewhere on
 * disk) -- that is required functionality, not a path-traversal vulnerability, since the value
 * comes from a trusted CI workflow or an operator's own CLI invocation, never from request input.
 */
function resolveWritablePath(string $path, string $argName): string
{
    if ($path === '' || str_contains($path, "\0")) {
        fwrite(STDERR, "[api-sync] FAIL: invalid {$argName} path\n");
        exit(1);
    }
    $dir = realpath(dirname($path));
    if ($dir === false || ! is_dir($dir)) {
        fwrite(STDERR, "[api-sync] FAIL: directory for {$argName} does not exist: {$path}\n");
        exit(1);
    }

    return $dir.DIRECTORY_SEPARATOR.basename($path);
}

/**
 * Resolve a file path declared in spec-map.json (or the hardcoded VERSION file) against $root,
 * and refuse to touch anything outside it. spec-map.json is a committed, human-reviewed config
 * file, not runtime request input, but every path built from it is still contained here: a
 * corrupted or malicious entry (e.g. a `..` sequence) must never let --apply write outside the
 * repository it was invoked on.
 */
function resolveWithinRoot(string $root, string $relativeFile, string $context): string
{
    $realRoot = realpath($root);
    $candidate = rtrim($root, '/').'/'.$relativeFile;
    $realDir = realpath(dirname($candidate));
    if ($realRoot === false || $realDir === false || ! str_starts_with($realDir.'/', $realRoot.'/')) {
        fwrite(STDERR, "[api-sync] FAIL: {$context} resolves outside the repository root: {$relativeFile}\n");
        exit(1);
    }

    return $realDir.'/'.basename($candidate);
}

// ---------------------------------------------------------------------------
// JSON / spec helpers
// ---------------------------------------------------------------------------

function loadJson(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "[api-sync] FAIL: file not found: {$path}\n");
        exit(1);
    }

    return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

/** Recursively collect every `#/components/schemas/X` ref target under $node. */
function collectRefs(mixed $node, array &$out): void
{
    if (is_array($node)) {
        if (isset($node['$ref']) && is_string($node['$ref']) && str_starts_with($node['$ref'], '#/components/schemas/')) {
            $out[substr($node['$ref'], strlen('#/components/schemas/'))] = true;
        }
        foreach ($node as $v) {
            collectRefs($v, $out);
        }
    }
}

/** BFS transitive closure of schema names reachable from paths + webhooks. */
function computeReachable(array $spec): array
{
    $schemas = $spec['components']['schemas'] ?? [];
    $roots = [];
    collectRefs($spec['paths'] ?? [], $roots);
    collectRefs($spec['webhooks'] ?? [], $roots);

    $visited = $roots;
    $queue = array_keys($roots);
    while (! empty($queue)) {
        $name = array_pop($queue);
        if (! isset($schemas[$name])) {
            continue;
        }
        $refs = [];
        collectRefs($schemas[$name], $refs);
        foreach ($refs as $r => $_) {
            if (! isset($visited[$r])) {
                $visited[$r] = true;
                $queue[] = $r;
            }
        }
    }

    return $visited; // name => true
}

/** Top-level property names of a component schema. */
function schemaProps(array $spec, string $schema): ?array
{
    $s = $spec['components']['schemas'][$schema] ?? null;
    if ($s === null || ! isset($s['properties'])) {
        return null;
    }

    return array_keys($s['properties']);
}

/** Enum values for `schema.property`, where property may itself be a nested inline object (dot path). */
function specEnumValues(array $spec, string $schema, string $propertyPath): ?array
{
    $node = $spec['components']['schemas'][$schema] ?? null;
    if ($node === null) {
        return null;
    }
    foreach (explode('.', $propertyPath) as $part) {
        $node = $node['properties'][$part] ?? null;
        if ($node === null) {
            return null;
        }
    }

    return $node['enum'] ?? null;
}

/** Enum values for an inline (non-$ref) request-body property, addressed by "METHOD /path". */
function operationEnumValues(array $spec, string $operation, string $property): ?array
{
    // $urlPath is an OpenAPI paths-map KEY (e.g. "/v1/instances/{instance_id}/quotes/fx"), never a
    // filesystem path -- named distinctly from every filesystem $path in this file.
    [$method, $urlPath] = explode(' ', $operation, 2);
    $method = strtolower($method);
    $node = $spec['paths'][$urlPath][$method]['requestBody']['content']['application/json']['schema']['properties'][$property] ?? null;

    return $node['enum'] ?? null;
}

/**
 * Nested inline object property names, e.g. schemaProps but for a dotted nested path.
 * $schemaPath is a dotted property path WITHIN a JSON schema (e.g. "tracking_payment"), never a
 * filesystem path -- named distinctly from every filesystem $path in this file so a static
 * analyzer's data-flow tracing has no name-based reason to conflate the two.
 */
function nestedProps(array $spec, string $schema, string $schemaPath): ?array
{
    $node = $spec['components']['schemas'][$schema] ?? null;
    if ($node === null) {
        return null;
    }
    foreach (explode('.', $schemaPath) as $part) {
        $node = $node['properties'][$part] ?? null;
        if ($node === null) {
            return null;
        }
    }

    return isset($node['properties']) ? array_keys($node['properties']) : null;
}

// ---------------------------------------------------------------------------
// SDK source scanning (tokenizer, mirrors scripts/contract-check.php's style)
// ---------------------------------------------------------------------------

function phpFilesUnder(string $dir): array
{
    $files = [];
    if (! is_dir($dir)) {
        return $files;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
}

/**
 * Scan one PHP file and return per-class info:
 *  isEnum, enumCases: [{name, value, line}], ctor: {startLine?, closeParenLine?, lastParamLine?, params:[{name,line}]},
 *  fromArray: {startLine?, closeParenLine?, lastArgLine?, keys:[...]},
 *  toArray: {style: 'none'|'literal'|'conditional', literalCloseLine?, literalLastLine?,
 *            conditionalReturnLine?, conditionalLastIfLine?, keys:[...]}
 */
function scanClassesDetailed(string $path): array
{
    $source = file_get_contents($path);
    $tokens = token_get_all($source);
    $tokens = array_values(array_filter($tokens, function ($t) {
        if (! is_array($t)) {
            return true;
        }

        return ! in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }));

    $classes = [];
    $braceDepth = 0;
    $classStack = []; // [depth, name]
    $functionStack = []; // [depth, name]
    $pendingClassName = null;
    $pendingIsEnum = false;
    $pendingFunctionName = null;
    $awaitingCtorParen = false;
    $inCtorParams = false;
    $ctorParenDepth = 0;

    $count = count($tokens);
    $line = 1;

    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        $id = is_array($t) ? $t[0] : null;
        $text = is_array($t) ? $t[1] : $t;
        $tokenLine = is_array($t) ? $t[2] : $line;
        $line = $tokenLine;

        if (is_array($t) && in_array($id, [T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT], true)) {
            $pendingIsEnum = ($id === T_ENUM);
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
                    if ($nt[1] === '__construct') {
                        $awaitingCtorParen = true;
                    }

                    break;
                }
                if (! is_array($nt) && $nt === '{') {
                    break;
                }
            }
        }

        $currentClass = end($classStack)[1] ?? null;
        $currentFunction = end($functionStack)[1] ?? null;

        // Promoted constructor properties live in the parameter list, which closes BEFORE the
        // function body opens -- track it via paren depth instead of the function-body stack.
        if (! is_array($t) && $text === '(' && $awaitingCtorParen && ! $inCtorParams) {
            $inCtorParams = true;
            $ctorParenDepth = 1;
            $awaitingCtorParen = false;
            if ($currentClass !== null && isset($classes[$currentClass])) {
                $classes[$currentClass]['ctor'] ??= ['params' => []];
            }

            continue;
        }
        if ($inCtorParams) {
            if (! is_array($t) && $text === '(') {
                $ctorParenDepth++;
            } elseif (! is_array($t) && $text === ')') {
                $ctorParenDepth--;
                if ($ctorParenDepth === 0) {
                    $inCtorParams = false;
                }
            } elseif ($ctorParenDepth === 1 && is_array($t) && in_array($id, [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)
                && $currentClass !== null && isset($classes[$currentClass])) {
                $typeParts = [];
                for ($j = $i + 1; $j < $count; $j++) {
                    $nt = $tokens[$j];
                    if (! is_array($nt) && in_array($nt, [',', ')'], true)) {
                        break;
                    }
                    if (is_array($nt) && $nt[0] === T_VARIABLE) {
                        $classes[$currentClass]['ctor']['params'][] = [
                            'name' => ltrim($nt[1], '$'),
                            'line' => $nt[2],
                            'type' => implode('', $typeParts),
                        ];

                        break;
                    }
                    if (is_array($nt) && $nt[0] === T_READONLY) {
                        continue;
                    }
                    // Type declaration tokens: `?`, a name (possibly namespaced with `\`), `|` for
                    // unions, or `array` -- which tokenizes as the dedicated T_ARRAY, not T_STRING.
                    if ((! is_array($nt) && in_array($nt, ['?', '\\', '|'], true))
                        || (is_array($nt) && in_array($nt[0], [T_STRING, T_ARRAY], true))) {
                        $typeParts[] = is_array($nt) ? $nt[1] : $nt;
                    }
                }
            }
        }

        if (! is_array($t) && $text === '{') {
            $braceDepth++;
            if ($pendingClassName !== null) {
                $classStack[] = [$braceDepth, $pendingClassName];
                if (! isset($classes[$pendingClassName])) {
                    $classes[$pendingClassName] = [
                        'isEnum' => $pendingIsEnum,
                        'enumCases' => [],
                        'ctor' => null,
                        'fromArray' => null,
                        'toArray' => ['style' => 'none', 'keys' => []],
                    ];
                }
                $pendingClassName = null;
                $pendingIsEnum = false;
            } elseif ($pendingFunctionName !== null) {
                $functionStack[] = [$braceDepth, $pendingFunctionName];
                // record function open info
                if ($currentClass !== null && isset($classes[$currentClass])) {
                    if ($pendingFunctionName === '__construct') {
                        $classes[$currentClass]['ctor'] ??= ['params' => []];
                    } elseif ($pendingFunctionName === 'fromArray') {
                        $classes[$currentClass]['fromArray'] ??= ['args' => []];
                    }
                }
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

        // enum case NAME = 'value'; at line $line
        // The case name is always the token immediately after `case` -- read it positionally
        // rather than by token type, since PHP allows semi-reserved keywords (e.g. `AS`, `DO`)
        // as enum case names, and those tokenize as their keyword type, not T_STRING.
        if ($currentClass !== null && isset($classes[$currentClass]) && $classes[$currentClass]['isEnum']
            && is_array($t) && $id === T_CASE) {
            $caseName = (isset($tokens[$i + 1]) && is_array($tokens[$i + 1])) ? $tokens[$i + 1][1] : null;
            $caseValue = null;
            $caseLine = $line;
            for ($j = $i + 1; $j < $count && $tokens[$j] !== ';'; $j++) {
                $nt = $tokens[$j];
                if (is_array($nt) && $nt[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $caseValue = trim($nt[1], "'\"");
                }
            }
            if ($caseName !== null) {
                $classes[$currentClass]['enumCases'][] = ['name' => $caseName, 'value' => $caseValue, 'line' => $caseLine];
            }

            continue;
        }

        // fromArray: $data['key'] read anywhere in the body
        if ($currentFunction === 'fromArray' && $currentClass !== null && isset($classes[$currentClass])) {
            if (is_array($t) && $id === T_VARIABLE
                && isset($tokens[$i + 1], $tokens[$i + 2], $tokens[$i + 3])
                && $tokens[$i + 1] === '['
                && is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING
                && $tokens[$i + 3] === ']'
            ) {
                $key = trim($tokens[$i + 2][1], "'\"");
                $classes[$currentClass]['fromArray']['keys'][$key] = true;
            }
            // named-argument line: `camelName: ` at top level of the `new self(` call -- record for anchor purposes
            if (is_array($t) && $id === T_STRING
                && isset($tokens[$i + 1]) && $tokens[$i + 1] === ':'
                && (! isset($tokens[$i + 2]) || ! (is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_DOUBLE_COLON))
            ) {
                $classes[$currentClass]['fromArray']['args'][] = ['name' => $text, 'line' => $line];
            }
        }

        // toArray: two possible styles.
        if ($currentFunction === 'toArray' && $currentClass !== null && isset($classes[$currentClass])) {
            $ta = &$classes[$currentClass]['toArray'];

            // style "literal": 'key' => value inside an array literal
            if (is_array($t) && $id === T_CONSTANT_ENCAPSED_STRING
                && isset($tokens[$i + 1]) && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] === T_DOUBLE_ARROW
            ) {
                $key = trim($text, "'\"");
                $ta['keys'][$key] = true;
                if ($ta['style'] !== 'conditional') {
                    $ta['style'] = 'literal';
                }
                $ta['literalLastLine'] = $line;
            }

            // style "conditional": $data['key'] = value; (subscript assignment, not literal)
            if (is_array($t) && $id === T_VARIABLE
                && isset($tokens[$i + 1], $tokens[$i + 2], $tokens[$i + 3], $tokens[$i + 4])
                && $tokens[$i + 1] === '['
                && is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING
                && $tokens[$i + 3] === ']'
                && $tokens[$i + 4] === '='
            ) {
                $key = trim($tokens[$i + 2][1], "'\"");
                $ta['keys'][$key] = true;
                $ta['style'] = 'conditional';
                $ta['conditionalLastIfLine'] = $line;
            }

            // track `return $data;` line for the conditional style anchor
            if (is_array($t) && $id === T_RETURN) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $nt = $tokens[$j];
                    if (! is_array($nt)) {
                        break;
                    }
                    if ($nt[0] === T_VARIABLE && $nt[1] === '$data') {
                        $ta['conditionalReturnLine'] = $line;
                    }

                    break;
                }
            }
            unset($ta);
        }
    }

    return $classes;
}

/** name => {file, isEnum, enumCases, ctor, fromArray, toArray} across src/Resources and src/Types. */
function scanAllClasses(string $root): array
{
    $out = [];
    foreach (['src/Resources', 'src/Types'] as $dir) {
        foreach (phpFilesUnder("{$root}/{$dir}") as $file) {
            $classes = scanClassesDetailed($file);
            foreach ($classes as $name => $info) {
                $info['file'] = substr($file, strlen($root) + 1);
                $out[$name] = $info;
            }
        }
    }

    return $out;
}

// ---------------------------------------------------------------------------
// JSON schema type -> PHP type
// ---------------------------------------------------------------------------

function phpTypeFor(array $propSchema): string
{
    $type = $propSchema['type'] ?? 'string';
    $types = is_array($type) ? $type : [$type];
    $types = array_values(array_diff($types, ['null']));
    $t = $types[0] ?? 'string';

    return match ($t) {
        'integer' => 'int',
        'number' => 'float',
        'boolean' => 'bool',
        'array' => 'array',
        'object' => 'array',
        default => 'string',
    };
}

function snakeToCamel(string $snake): string
{
    $parts = explode('_', $snake);
    $first = array_shift($parts);

    return $first.implode('', array_map(fn ($p) => $p === '' ? '' : ucfirst($p), $parts));
}

// ---------------------------------------------------------------------------
// Type-mismatch detection: spec declared type vs SDK declared property type
// ---------------------------------------------------------------------------

/**
 * Normalize a spec property's JSON Schema type into {category, nullable}.
 * category is one of: null (untyped in spec -- cannot compare), 'ambiguous' (more than one
 * non-null JSON type -- always needs a human), 'enum' (has an `enum` list; may legitimately be
 * modeled as a scalar OR a backed-enum/object in the SDK), or a plain JSON Schema type name
 * (string/integer/number/boolean/array/object).
 */
function specTypeCategory(array $propSchema): array
{
    $type = $propSchema['type'] ?? null;
    if ($type === null) {
        return ['category' => null, 'nullable' => null];
    }
    $types = is_array($type) ? $type : [$type];
    $nullable = in_array('null', $types, true);
    $nonNull = array_values(array_diff($types, ['null']));
    if (count($nonNull) !== 1) {
        return ['category' => 'ambiguous', 'nullable' => $nullable];
    }
    $category = isset($propSchema['enum']) ? 'enum' : $nonNull[0];

    return ['category' => $category, 'nullable' => $nullable];
}

/**
 * Normalize a PHP promoted-property type declaration (e.g. "?string", "Network", "?array")
 * into {category, nullable}. A bare class/enum name normalizes to 'object'.
 */
function phpTypeCategory(?string $phpType): array
{
    if ($phpType === null || $phpType === '') {
        return ['category' => null, 'nullable' => null, 'className' => null];
    }
    $nullable = str_starts_with($phpType, '?');
    $base = ltrim($phpType, '?');
    if (str_contains($base, '|')) {
        return ['category' => 'ambiguous', 'nullable' => $nullable, 'className' => null];
    }
    $scalarMap = ['string' => 'string', 'int' => 'integer', 'float' => 'number', 'bool' => 'boolean', 'array' => 'array'];
    $category = $scalarMap[$base] ?? 'object';

    return ['category' => $category, 'nullable' => $nullable, 'className' => $category === 'object' ? $base : null];
}

/**
 * Whether a spec property's base representation and an SDK property's base representation are
 * compatible enough that a real spec value is guaranteed to decode correctly. Nullability is
 * deliberately NOT considered here -- see typeNullabilityWidened() below for why. Deliberately
 * conservative on the base category: anything not positively known to be safe is a mismatch.
 */
function categoriesCompatible(array $spec, array $php): bool
{
    if ($spec['category'] === null || $php['category'] === null) {
        return true; // one side can't be determined -- do not force a false positive
    }
    if ($spec['category'] === 'ambiguous' || $php['category'] === 'ambiguous') {
        return false;
    }
    // This SDK's established decode pattern: a date-time string is parsed into DateTimeImmutable.
    if ($spec['category'] === 'string' && ($php['className'] ?? null) === 'DateTimeImmutable') {
        return true;
    }
    // PHP widens int to float safely, even under strict_types (a documented special case) -- the
    // reverse (spec `number`, SDK `int`) is NOT safe, a fractional value would lose precision.
    if ($spec['category'] === 'integer' && $php['category'] === 'number') {
        return true;
    }
    if ($spec['category'] === 'enum') {
        // May be modeled as a plain scalar (deliberately loose, e.g. known-divergences.json) or as
        // a backed enum/class -- both parse every spec value without error.
        return in_array($php['category'], ['string', 'object'], true);
    }
    // json_decode(..., true) throughout this codebase represents a JSON object as a PHP
    // associative array, so a spec `object` modeled as PHP `array` is this SDK's norm, not a bug.
    if ($spec['category'] === 'object' && $php['category'] === 'array') {
        return true;
    }

    return $spec['category'] === $php['category'];
}

/**
 * Whether nullability was newly ADDED to this property's spec type between $oldPropSchema and
 * $newPropSchema, while the SDK's declared type is not itself nullable. Checked as an old-vs-new
 * event, not a pure state check: this codebase's spec has long-standing, pervasive `|null`
 * annotations on fields the SDK still declares non-nullable (predating every snapshot on record),
 * and re-litigating that entire backlog on every run is neither this patcher's job nor safe to
 * silently mass-fix. A genuinely NEW nullability relaxation is a real, actionable signal though.
 */
function typeNullabilityWidened(array $oldPropSchema, array $newPropSchema, array $phpType): bool
{
    if ($phpType['nullable'] ?? true) {
        return false; // SDK already tolerates null -- nothing at risk
    }
    $old = specTypeCategory($oldPropSchema);
    $new = specTypeCategory($newPropSchema);

    return ! $old['nullable'] && $new['nullable'];
}

// ---------------------------------------------------------------------------
// Map validation
// ---------------------------------------------------------------------------

function validateMap(array $map, array $classIndex, string $root): array
{
    $errors = [];
    foreach ($map['types'] as $entry) {
        foreach ($entry['sdk'] as $site) {
            $class = $site['class'];
            if (! isset($classIndex[$class])) {
                $errors[] = "map anchor not found: class {$class} declared in spec-map.json types (file {$site['file']}) does not exist in src/";

                continue;
            }
            $actualFile = $classIndex[$class]['file'];
            if ($actualFile !== $site['file']) {
                $errors[] = "map anchor mismatch: class {$class} is declared at {$actualFile}, spec-map.json says {$site['file']}";
            }
        }
    }
    foreach ($map['enums'] as $entry) {
        $class = $entry['sdk']['class'];
        if (! isset($classIndex[$class]) || ! $classIndex[$class]['isEnum']) {
            $errors[] = "map anchor not found: enum {$class} declared in spec-map.json enums does not exist (or is not an enum) in src/";
        }
    }

    return $errors;
}

// ---------------------------------------------------------------------------
// Reconciliation: enums
// ---------------------------------------------------------------------------

function enumSpecValues(array $spec, array $entry): ?array
{
    $s = $entry['spec'];
    if (isset($s['kind']) && $s['kind'] === 'webhookTopics') {
        $topics = $spec['webhooks'] ?? [];

        return array_keys($topics);
    }
    if (isset($s['operation'])) {
        return operationEnumValues($spec, $s['operation'], $s['property']);
    }

    return specEnumValues($spec, $s['schema'], $s['property']);
}

function reconcileEnums(array $map, array $newSpec, array $reachable, array $classIndex, array $divergenceEnumIndex): array
{
    $applicable = [];
    $needsHuman = [];

    foreach ($map['enums'] as $entry) {
        $s = $entry['spec'];
        if (isset($s['schema']) && ! isset($reachable[$s['schema']])) {
            continue; // schema no longer reachable -- skip by construction
        }

        $specValues = enumSpecValues($newSpec, $entry);
        if ($specValues === null) {
            continue; // property/operation gone entirely; handled by structural diff
        }

        $className = $entry['sdk']['class'];
        $classInfo = $classIndex[$className] ?? null;
        if ($classInfo === null) {
            continue; // already reported by validateMap
        }
        $sdkValues = array_map(fn ($c) => $c['value'], $classInfo['enumCases']);
        $sdkValueSet = array_flip($sdkValues);

        foreach ($specValues as $value) {
            if (isset($sdkValueSet[$value])) {
                continue;
            }
            if (isset($divergenceEnumIndex["{$className}|{$value}"])) {
                continue; // recorded known divergence, not pending drift
            }
            $applicable[] = [
                'kind' => 'enum-member-added',
                'enum' => $className,
                'file' => $classInfo['file'],
                'value' => $value,
                'sortKey' => "{$className}|{$value}",
            ];
        }
    }

    return [$applicable, $needsHuman];
}

// ---------------------------------------------------------------------------
// Reconciliation: types / fields
// ---------------------------------------------------------------------------

function typeSpecSchemas(array $entry): array
{
    $spec = $entry['spec'];

    return is_array($spec) ? $spec : [$spec];
}

function reconcileTypes(array $map, array $newSpec, array $reachable, array $classIndex, array $unmodeledIndex, array $divergenceFieldIndex = []): array
{
    $applicable = [];
    $needsHuman = [];

    foreach ($map['types'] as $entry) {
        $schemas = typeSpecSchemas($entry);
        // A dotted schema property path (e.g. "tracking_payment"), never a filesystem path.
        $schemaPath = $entry['path'] ?? null;
        // unmodeled.json records one entry per group (using the first schema as the canonical
        // name), not once per schema in a multi-schema entry like ["PayoutOut","PayoutOnEvmOut"].
        $canonicalSchema = $schemas[0];

        // camelCase param name -> PHP type string. Only meaningful for a 1:1 schema-to-class
        // mapping: a discriminator fan-out (e.g. CreateBankAccountIn's 10 rails) has each class
        // model only its own rail's requiredness for a shared wire key, which genuinely diverges
        // from the flattened spec schema's nullability -- that's covered by field-presence
        // checking only, not per-field type strictness.
        $ctorTypesByParam = [];
        if (count($entry['sdk']) === 1) {
            $info = $classIndex[$entry['sdk'][0]['class']] ?? null;
            foreach ($info['ctor']['params'] ?? [] as $param) {
                $ctorTypesByParam[$param['name']] ??= $param['type'];
            }
        }

        foreach ($schemas as $schemaName) {
            if (! isset($reachable[$schemaName])) {
                continue; // no longer reachable -- skip by construction
            }

            $specProps = $schemaPath !== null
                ? nestedProps($newSpec, $schemaName, $schemaPath)
                : schemaProps($newSpec, $schemaName);

            if ($specProps === null) {
                continue; // schema/property gone; handled by structural diff
            }

            // union of wire keys already modeled across all mapped SDK classes for this schema
            $modeled = [];
            foreach ($entry['sdk'] as $site) {
                $info = $classIndex[$site['class']] ?? null;
                if ($info === null) {
                    continue;
                }
                foreach (array_keys($info['fromArray']['keys'] ?? []) as $k) {
                    $modeled[$k] = true;
                }
                foreach (array_keys($info['toArray']['keys'] ?? []) as $k) {
                    $modeled[$k] = true;
                }
            }

            foreach ($specProps as $field) {
                if (isset($modeled[$field])) {
                    // Already modeled -- check the declared type still matches, not just presence.
                    $divergenceKey = "{$canonicalSchema}|{$field}";
                    if (isset($divergenceFieldIndex[$divergenceKey])) {
                        continue;
                    }
                    $camel = snakeToCamel($field);
                    $phpType = $ctorTypesByParam[$camel] ?? null;
                    if ($phpType === null) {
                        continue; // modeled via fromArray/toArray only (e.g. no promoted ctor prop) -- nothing to compare
                    }
                    $propSchema = $schemaPath !== null
                        ? nestedPropSchema($newSpec, $schemaName, $schemaPath, $field)
                        : ($newSpec['components']['schemas'][$schemaName]['properties'][$field] ?? []);
                    $specType = specTypeCategory($propSchema);
                    $sdkType = phpTypeCategory($phpType);
                    if (! categoriesCompatible($specType, $sdkType)) {
                        $label = $schemaName.($schemaPath !== null ? ".{$schemaPath}" : '');
                        $needsHuman[] = "NEEDS_HUMAN: type mismatch on {$label}.{$field}: spec declares "
                            .($specType['category'] ?? 'unknown').($specType['nullable'] ? '|null' : '')
                            .", SDK declares {$phpType} (mapped class(es): ".implode(', ', array_column($entry['sdk'], 'class')).')';
                    }

                    continue;
                }
                $unmodeledKey = $canonicalSchema.'|'.($schemaPath ?? '').'|'.$field;
                if (isset($unmodeledIndex[$unmodeledKey])) {
                    continue;
                }

                // determine target class(es) to patch: prefer a class whose family (input vs response)
                // is unambiguous; for a fan-out with multiple sdk sites we apply to ALL of them, since
                // any of them might legitimately accept/return the new field.
                foreach ($entry['sdk'] as $site) {
                    $applicable[] = [
                        'kind' => 'field-added',
                        'schema' => $schemaName,
                        'path' => $schemaPath,
                        'field' => $field,
                        'class' => $site['class'],
                        'file' => $site['file'],
                        'propSchema' => $schemaPath !== null
                            ? (nestedPropSchema($newSpec, $schemaName, $schemaPath, $field))
                            : ($newSpec['components']['schemas'][$schemaName]['properties'][$field] ?? []),
                        'sortKey' => "{$schemaName}|".($schemaPath ?? '')."|{$field}|{$site['class']}",
                    ];
                }
            }
        }
    }

    return [$applicable, $needsHuman];
}

// $schemaPath is a dotted schema property path, never a filesystem path -- see nestedProps().
function nestedPropSchema(array $spec, string $schema, string $schemaPath, string $field): array
{
    $node = $spec['components']['schemas'][$schema] ?? [];
    foreach (explode('.', $schemaPath) as $part) {
        $node = $node['properties'][$part] ?? [];
    }

    return $node['properties'][$field] ?? [];
}

// ---------------------------------------------------------------------------
// Structural diff (old vs new): removals + new-and-unmapped surface
// ---------------------------------------------------------------------------

function pathMethodSet(array $spec): array
{
    $out = [];
    foreach ($spec['paths'] ?? [] as $p => $methods) {
        foreach ($methods as $m => $_) {
            if (in_array($m, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                $out["{$m} {$p}"] = true;
            }
        }
    }

    return $out;
}

function computeStructuralDiff(array $oldSpec, array $newSpec, array $reachableOld, array $reachableNew, array $map, array $classIndex = []): array
{
    $issues = [];

    $oldOps = pathMethodSet($oldSpec);
    $newOps = pathMethodSet($newSpec);
    foreach (array_diff(array_keys($oldOps), array_keys($newOps)) as $op) {
        $issues[] = "NEEDS_HUMAN: operation removed: {$op} (breaking, requires a deliberate major)";
    }
    foreach (array_diff(array_keys($newOps), array_keys($oldOps)) as $op) {
        $issues[] = "NEEDS_HUMAN: new operation: {$op} (needs naming/grouping decisions, cannot be auto-modeled)";
    }

    $oldSchemas = array_keys($oldSpec['components']['schemas'] ?? []);
    $newSchemas = array_keys($newSpec['components']['schemas'] ?? []);
    foreach (array_diff($oldSchemas, $newSchemas) as $s) {
        if (isset($reachableOld[$s])) {
            $issues[] = "NEEDS_HUMAN: schema removed: {$s} (breaking, requires a deliberate major)";
        }
    }

    $ignored = [];
    foreach ($map['ignore']['schemas'] ?? [] as $ig) {
        $ignored[$ig['schema']] = true;
    }
    $mapped = [];
    foreach ($map['types'] as $entry) {
        foreach (typeSpecSchemas($entry) as $s) {
            $mapped[$s] = true;
        }
    }
    foreach ($map['enums'] as $entry) {
        if (isset($entry['spec']['schema'])) {
            $mapped[$entry['spec']['schema']] = true;
        }
    }
    foreach (array_diff($newSchemas, $oldSchemas) as $s) {
        if (isset($reachableNew[$s]) && ! isset($ignored[$s]) && ! isset($mapped[$s])) {
            $issues[] = "NEEDS_HUMAN: new schema: {$s} (needs a mapping decision, cannot be auto-modeled)";
        }
    }

    $oldWebhooks = array_keys($oldSpec['webhooks'] ?? []);
    $newWebhooks = array_keys($newSpec['webhooks'] ?? []);
    foreach (array_diff($oldWebhooks, $newWebhooks) as $w) {
        $issues[] = "NEEDS_HUMAN: webhook topic removed: {$w} (breaking, requires a deliberate major)";
    }

    // removed enum values / removed or type-changed properties on mapped schemas
    foreach ($map['enums'] as $entry) {
        $s = $entry['spec'];
        if (isset($s['schema']) && (! isset($reachableOld[$s['schema']]) || ! isset($reachableNew[$s['schema']]))) {
            continue;
        }
        $oldValues = enumSpecValues($oldSpec, $entry);
        $newValues = enumSpecValues($newSpec, $entry);
        if ($oldValues === null || $newValues === null) {
            continue;
        }
        $removed = array_diff($oldValues, $newValues);
        if (! empty($removed)) {
            $label = $entry['sdk']['class'];
            $issues[] = 'NEEDS_HUMAN: enum value(s) removed from '.$label.': '.implode(', ', $removed).' (breaking, requires a deliberate major)';
        }
    }

    foreach ($map['types'] as $entry) {
        // A dotted schema property path (e.g. "tracking_payment"), never a filesystem path.
        $schemaPath = $entry['path'] ?? null;
        $schemas = typeSpecSchemas($entry);
        // See reconcileTypes(): a discriminator fan-out's flattened schema nullability doesn't
        // apply uniformly to every rail's class, so per-field type/nullability checks only make
        // sense for an unambiguous 1:1 mapping.
        $ctorTypesByParam = [];
        if (count($entry['sdk']) === 1) {
            $info = $classIndex[$entry['sdk'][0]['class']] ?? null;
            foreach ($info['ctor']['params'] ?? [] as $param) {
                $ctorTypesByParam[$param['name']] ??= $param['type'];
            }
        }

        foreach ($schemas as $schemaName) {
            if (! isset($reachableOld[$schemaName]) || ! isset($reachableNew[$schemaName])) {
                continue;
            }
            $oldProps = $schemaPath !== null ? nestedProps($oldSpec, $schemaName, $schemaPath) : schemaProps($oldSpec, $schemaName);
            $newProps = $schemaPath !== null ? nestedProps($newSpec, $schemaName, $schemaPath) : schemaProps($newSpec, $schemaName);
            if ($oldProps === null || $newProps === null) {
                continue;
            }
            $removed = array_diff($oldProps, $newProps);
            if (! empty($removed)) {
                $label = $schemaName.($schemaPath !== null ? ".{$schemaPath}" : '');
                $issues[] = 'NEEDS_HUMAN: propert'.(count($removed) === 1 ? 'y' : 'ies')." removed from {$label}: ".implode(', ', $removed).' (breaking, requires a deliberate major)';
            }

            foreach (array_intersect($oldProps, $newProps) as $field) {
                $phpType = $ctorTypesByParam[snakeToCamel($field)] ?? null;
                if ($phpType === null) {
                    continue;
                }
                $oldPropSchema = $schemaPath !== null ? nestedPropSchema($oldSpec, $schemaName, $schemaPath, $field) : ($oldSpec['components']['schemas'][$schemaName]['properties'][$field] ?? []);
                $newPropSchema = $schemaPath !== null ? nestedPropSchema($newSpec, $schemaName, $schemaPath, $field) : ($newSpec['components']['schemas'][$schemaName]['properties'][$field] ?? []);
                if (typeNullabilityWidened($oldPropSchema, $newPropSchema, phpTypeCategory($phpType))) {
                    $label = $schemaName.($schemaPath !== null ? ".{$schemaPath}" : '');
                    $issues[] = "NEEDS_HUMAN: {$label}.{$field} newly allows null in the spec, but the SDK declares a non-nullable {$phpType} (mapped class: {$entry['sdk'][0]['class']})";
                }
            }
        }
    }

    sort($issues);

    return $issues;
}

// ---------------------------------------------------------------------------
// Coverage report (non-blocking)
// ---------------------------------------------------------------------------

function computeCoverageReport(array $map, array $spec, array $reachable): array
{
    $mapped = [];
    foreach ($map['types'] as $entry) {
        foreach (typeSpecSchemas($entry) as $s) {
            $mapped[$s] = true;
        }
    }
    $ignored = [];
    foreach ($map['ignore']['schemas'] ?? [] as $ig) {
        $ignored[$ig['schema']] = true;
    }

    $gaps = [];
    foreach (array_keys($reachable) as $schema) {
        if (! isset($mapped[$schema]) && ! isset($ignored[$schema])) {
            $gaps[] = $schema;
        }
    }
    sort($gaps);

    return $gaps;
}

// ---------------------------------------------------------------------------
// Type audit (non-blocking): full state comparison, not just forward drift
// ---------------------------------------------------------------------------

/**
 * Compares every already-modeled property's CURRENT spec type against its declared PHP type,
 * for every mapped schema/path -- regardless of whether that mismatch is old or new. This is
 * deliberately broader than the blocking --check type-mismatch gate (reconcileTypes) and than
 * typeNullabilityWidened() (computeStructuralDiff), which only fire on genuinely NEW drift: this
 * is the same state-vs-event distinction the whole design is built on, applied to types instead
 * of presence. Never blocks: purely a printed report for a human to triage (Phase C).
 *
 * Discriminator fan-outs (more than one SDK class per entry) are reported as skipped, not
 * silently omitted: a flattened spec schema's nullability/requiredness genuinely differs per
 * rail, so a union-of-classes comparison would be noise, not signal.
 */
function auditTypes(array $map, array $spec, array $reachable, array $classIndex, array $divergenceFieldIndex): array
{
    $findings = [];
    $skippedFanOuts = [];

    foreach ($map['types'] as $entry) {
        $schemas = typeSpecSchemas($entry);
        // A dotted schema property path (e.g. "tracking_payment"), never a filesystem path.
        $schemaPath = $entry['path'] ?? null;
        $canonicalSchema = $schemas[0];

        if (count($entry['sdk']) > 1) {
            $skippedFanOuts[] = $canonicalSchema.($schemaPath !== null ? ".{$schemaPath}" : '');

            continue;
        }

        $info = $classIndex[$entry['sdk'][0]['class']] ?? null;
        $ctorTypesByParam = [];
        foreach ($info['ctor']['params'] ?? [] as $param) {
            $ctorTypesByParam[$param['name']] ??= $param['type'];
        }

        foreach ($schemas as $schemaName) {
            if (! isset($reachable[$schemaName])) {
                continue;
            }
            $specProps = $schemaPath !== null ? nestedProps($spec, $schemaName, $schemaPath) : schemaProps($spec, $schemaName);
            if ($specProps === null) {
                continue;
            }

            foreach ($specProps as $field) {
                $camel = snakeToCamel($field);
                $phpType = $ctorTypesByParam[$camel] ?? null;
                if ($phpType === null) {
                    continue; // not modeled via a promoted constructor property -- nothing to compare
                }
                $propSchema = $schemaPath !== null
                    ? nestedPropSchema($spec, $schemaName, $schemaPath, $field)
                    : ($spec['components']['schemas'][$schemaName]['properties'][$field] ?? []);
                $specType = specTypeCategory($propSchema);
                $sdkType = phpTypeCategory($phpType);

                $categoryMismatch = ! categoriesCompatible($specType, $sdkType);
                $nullabilityMismatch = $specType['nullable'] === true && $sdkType['nullable'] !== true;
                if (! $categoryMismatch && ! $nullabilityMismatch) {
                    continue;
                }

                $label = $schemaName.($schemaPath !== null ? ".{$schemaPath}" : '');
                $findings[] = [
                    'field' => "{$label}.{$field}",
                    'class' => $entry['sdk'][0]['class'],
                    'specType' => ($specType['category'] ?? 'unknown').($specType['nullable'] ? '|null' : ''),
                    'sdkType' => $phpType,
                    'categoryMismatch' => $categoryMismatch,
                    'nullabilityMismatch' => $nullabilityMismatch,
                    'recordedDivergence' => isset($divergenceFieldIndex["{$canonicalSchema}|{$field}"]),
                ];
            }
        }
    }

    usort($findings, fn ($a, $b) => $a['field'] <=> $b['field']);
    sort($skippedFanOuts);

    return ['findings' => $findings, 'skippedFanOuts' => $skippedFanOuts];
}

// ---------------------------------------------------------------------------
// Applying changes (surgical text splicing)
// ---------------------------------------------------------------------------

function detectIndent(string $line): string
{
    preg_match('/^(\s*)/', $line, $m);

    return $m[1];
}

function applyEnumCaseInsertion(string $root, string $file, string $className, string $value, array $newSpec, array $specValuesByEnum): void
{
    $path = resolveWithinRoot($root, $file, "enum class file for {$className}");
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $classes = scanClassesDetailed($path);
    $info = $classes[$className];
    $cases = $info['enumCases'];

    $specOrder = $specValuesByEnum[$className] ?? null;
    $anchorLine = null;

    if ($specOrder !== null) {
        $idx = array_search($value, $specOrder, true);
        if ($idx !== false) {
            $sdkValueToLine = [];
            foreach ($cases as $c) {
                $sdkValueToLine[$c['value']] = $c['line'];
            }
            for ($j = $idx - 1; $j >= 0; $j--) {
                if (isset($sdkValueToLine[$specOrder[$j]])) {
                    $anchorLine = $sdkValueToLine[$specOrder[$j]];

                    break;
                }
            }
        }
    }

    if ($anchorLine === null) {
        // no earlier spec value already modeled -- insert as the first case, right after the enum's opening brace
        $anchorLine = $cases[0]['line'] - 1; // insert before first case (i.e. after opening brace line)
        $indent = detectIndent($lines[$cases[0]['line'] - 1]);
        $caseName = deriveEnumCaseName($className, $value);
        array_splice($lines, $anchorLine, 0, ["{$indent}case {$caseName} = '{$value}';"]);
    } else {
        $indent = detectIndent($lines[$anchorLine - 1]);
        $caseName = deriveEnumCaseName($className, $value);
        array_splice($lines, $anchorLine, 0, ["{$indent}case {$caseName} = '{$value}';"]);
    }

    file_put_contents($path, implode("\n", $lines)."\n");
}

/** Derive an UPPER_SNAKE case name for a new enum value, following the file's own convention. */
function deriveEnumCaseName(string $className, string $value): string
{
    if ($className === 'BusinessIndustry' && ctype_digit($value)) {
        return "NAICS_{$value}";
    }
    if ($className === 'EstimatedAnnualRevenue') {
        return 'RANGE_'.strtoupper($value);
    }
    $name = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $value));
    if (preg_match('/^[0-9]/', $name)) {
        $name = 'V_'.$name;
    }

    return $name;
}

/**
 * Apply a 2-or-3-part field insertion to one class: constructor property (always),
 * fromArray line (only if the class has fromArray), toArray line (only if the class has toArray,
 * honoring its existing literal-vs-conditional style).
 */
function applyFieldInsertion(string $root, string $file, string $className, string $field, array $propSchema): void
{
    $path = resolveWithinRoot($root, $file, "class file for {$className}");
    $camel = snakeToCamel($field);
    $phpType = phpTypeFor($propSchema);

    // Re-scan fresh each time so line numbers stay valid across successive edits to the same file.
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $classes = scanClassesDetailed($path);
    $info = $classes[$className];

    // 1) constructor promoted property -- always last param, so it is always syntactically safe.
    // Preserve the file's own convention: if the previous last param had no trailing comma, the
    // new (now-last) line gets none either, and the previous line gains one since it's no longer last.
    if ($info['ctor'] !== null && ! empty($info['ctor']['params'])) {
        $lastParam = end($info['ctor']['params']);
        $lastLine = $lastParam['line'];
        $indent = detectIndent($lines[$lastLine - 1]);
        $hadTrailingComma = str_ends_with(rtrim($lines[$lastLine - 1]), ',');
        if (! $hadTrailingComma) {
            $lines[$lastLine - 1] = rtrim($lines[$lastLine - 1]).',';
        }
        $newLine = "{$indent}public ?{$phpType} \${$camel} = null".($hadTrailingComma ? ',' : '');
        array_splice($lines, $lastLine, 0, [$newLine]);
        file_put_contents($path, implode("\n", $lines)."\n");
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $classes = scanClassesDetailed($path);
        $info = $classes[$className];
    }

    // 2) fromArray line -- only if the method exists. Same trailing-comma convention as above.
    if ($info['fromArray'] !== null && ! empty($info['fromArray']['args'])) {
        $lastArg = end($info['fromArray']['args']);
        $lastLine = $lastArg['line'];
        $indent = detectIndent($lines[$lastLine - 1]);
        $hadTrailingComma = str_ends_with(rtrim($lines[$lastLine - 1]), ',');
        if (! $hadTrailingComma) {
            $lines[$lastLine - 1] = rtrim($lines[$lastLine - 1]).',';
        }
        $newLine = "{$indent}{$camel}: \$data['{$field}'] ?? null".($hadTrailingComma ? ',' : '');
        array_splice($lines, $lastLine, 0, [$newLine]);
        file_put_contents($path, implode("\n", $lines)."\n");
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $classes = scanClassesDetailed($path);
        $info = $classes[$className];
    }

    // 3) toArray line -- only if the method exists, honoring the class's own style.
    $ta = $info['toArray'];
    if ($ta['style'] === 'literal' && isset($ta['literalLastLine'])) {
        $lastLine = $ta['literalLastLine'];
        $indent = detectIndent($lines[$lastLine - 1]);
        if (! str_ends_with(rtrim($lines[$lastLine - 1]), ',')) {
            $lines[$lastLine - 1] = rtrim($lines[$lastLine - 1]).',';
        }
        array_splice($lines, $lastLine, 0, ["{$indent}'{$field}' => \$this->{$camel},"]);
        file_put_contents($path, implode("\n", $lines)."\n");
    } elseif ($ta['style'] === 'conditional' && isset($ta['conditionalReturnLine'])) {
        $returnLine = $ta['conditionalReturnLine'];
        $indent = detectIndent($lines[$returnLine - 1]);
        $block = [
            '',
            "{$indent}if (\$this->{$camel} !== null) {",
            "{$indent}    \$data['{$field}'] = \$this->{$camel};",
            "{$indent}}",
        ];
        array_splice($lines, $returnLine - 1, 0, $block);
        file_put_contents($path, implode("\n", $lines)."\n");
    }
}

// ---------------------------------------------------------------------------
// Version bump
// ---------------------------------------------------------------------------

function computeBump(array $applicable): ?string
{
    $hasEnum = false;
    $hasField = false;
    foreach ($applicable as $c) {
        if ($c['kind'] === 'enum-member-added') {
            $hasEnum = true;
        }
        if ($c['kind'] === 'field-added') {
            $hasField = true;
        }
    }
    if ($hasEnum) {
        return 'minor';
    }
    if ($hasField) {
        return 'patch';
    }

    return null;
}

function bumpVersionString(string $version, string $bump): string
{
    [$major, $minor, $patch] = array_map('intval', explode('.', $version));
    if ($bump === 'minor') {
        $minor++;
        $patch = 0;
    } elseif ($bump === 'patch') {
        $patch++;
    }

    return "{$major}.{$minor}.{$patch}";
}

function bumpVersion(string $root, string $bump): string
{
    $file = resolveWithinRoot($root, 'src/BlindPay.php', 'VERSION file');
    $source = file_get_contents($file);
    if (! preg_match("/private const VERSION = '([0-9]+\\.[0-9]+\\.[0-9]+)';/", $source, $m)) {
        fwrite(STDERR, "[api-sync] FAIL: could not find VERSION const in src/BlindPay.php\n");
        exit(1);
    }
    $new = bumpVersionString($m[1], $bump);
    $source = str_replace("private const VERSION = '{$m[1]}';", "private const VERSION = '{$new}';", $source);
    file_put_contents($file, $source);

    return $new;
}

// ---------------------------------------------------------------------------
// Determinism helpers
// ---------------------------------------------------------------------------

function encodeJsonDeterministic(array $data): string
{
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

/**
 * Entry point, extracted into a function so tests can `define('API_SYNC_LIB_ONLY', true)`
 * and require this file to get the helpers above without running the CLI.
 */
function runCli(array $argv, string $root): void
{
    $opts = parseArgs($argv);
    $mode = $opts['apply'] ? 'apply' : 'check';
    // Resolved and validated once, right where they enter the program (see resolveReadablePath()/
    // resolveWritablePath() above), not re-trusted as raw strings at each later read/write.
    $specPath = resolveReadablePath($opts['spec'] ?? ($root.'/.api-sync/spec-current.json'), '--spec');
    $reportPath = $opts['report'] !== null ? resolveWritablePath($opts['report'], '--report') : null;

    $map = loadJson($root.'/.api-sync/spec-map.json');
    $unmodeled = loadJson($root.'/.api-sync/unmodeled.json');
    $divergences = loadJson($root.'/.api-sync/known-divergences.json');
    $oldSpec = loadJson($root.'/.api-sync/spec-snapshot.json');
    $newSpec = loadJson($specPath);

    $unmodeledIndex = [];
    foreach ($unmodeled['entries'] as $e) {
        $key = ($e['schema'] ?? '').'|'.($e['path'] ?? '').'|'.($e['field'] ?? '');
        $unmodeledIndex[$key] = true;
    }

    $divergenceEnumIndex = [];
    foreach ($divergences['enumValues'] as $d) {
        if ($d['specValue'] !== null) {
            $divergenceEnumIndex["{$d['enum']}|{$d['specValue']}"] = true;
        }
    }
    $divergenceFieldIndex = [];
    foreach ($divergences['fields'] as $d) {
        $divergenceFieldIndex["{$d['schema']}|{$d['field']}"] = true;
    }

    $classIndex = scanAllClasses($root);

    $mapErrors = validateMap($map, $classIndex, $root);
    $mapErrors = array_map(fn ($e) => "NEEDS_HUMAN: {$e}", $mapErrors);

    $reachableOld = computeReachable($oldSpec);
    $reachableNew = computeReachable($newSpec);

    if ($opts['auditTypes']) {
        $audit = auditTypes($map, $newSpec, $reachableNew, $classIndex, $divergenceFieldIndex);
        fwrite(STDOUT, "[api-sync] --audit-types: full state comparison of every mapped property's spec type vs SDK type.\nNon-blocking and informational -- pre-existing mismatches are Phase C triage, not a gate.\n\n");
        if (empty($audit['findings'])) {
            fwrite(STDOUT, "No type mismatches found.\n");
        }
        foreach ($audit['findings'] as $f) {
            $tags = [];
            if ($f['categoryMismatch']) {
                $tags[] = 'category';
            }
            if ($f['nullabilityMismatch']) {
                $tags[] = 'nullability';
            }
            $recorded = $f['recordedDivergence'] ? ' [recorded in known-divergences.json]' : ' [NOT YET RECORDED]';
            fwrite(STDOUT, "  - {$f['field']} ({$f['class']}): spec={$f['specType']} sdk={$f['sdkType']} mismatch=".implode('+', $tags).$recorded."\n");
        }
        if (! empty($audit['skippedFanOuts'])) {
            fwrite(STDOUT, "\nSkipped (discriminator fan-out -- per-rail requiredness genuinely differs from the flattened schema): ".implode(', ', $audit['skippedFanOuts'])."\n");
        }
        if ($reportPath !== null) {
            file_put_contents($reportPath, encodeJsonDeterministic(['mode' => 'audit-types', 'spec' => $specPath, 'audit' => $audit]));
        }
        exit(0);
    }

    $structuralIssues = empty($mapErrors) ? computeStructuralDiff($oldSpec, $newSpec, $reachableOld, $reachableNew, $map, $classIndex) : [];

    [$enumApplicable, $enumNeedsHuman] = empty($mapErrors)
        ? reconcileEnums($map, $newSpec, $reachableNew, $classIndex, $divergenceEnumIndex)
        : [[], []];
    [$fieldApplicable, $fieldNeedsHuman] = empty($mapErrors)
        ? reconcileTypes($map, $newSpec, $reachableNew, $classIndex, $unmodeledIndex, $divergenceFieldIndex)
        : [[], []];

    $coverage = computeCoverageReport($map, $newSpec, $reachableNew);

    $needsHuman = array_merge($mapErrors, $structuralIssues, $enumNeedsHuman, $fieldNeedsHuman);

    $applicable = array_merge($enumApplicable, $fieldApplicable);
    usort($applicable, fn ($a, $b) => $a['sortKey'] <=> $b['sortKey']);

    $report = [
        'mode' => $mode,
        'spec' => $specPath,
        'applied' => [],
        'needsHuman' => $needsHuman,
        'bump' => null,
        'coverageGaps' => $coverage,
    ];

    if ($mode === 'check') {
        if (! empty($needsHuman)) {
            fwrite(STDERR, "\n[api-sync] FAIL -- needs a human decision:\n");
            foreach ($needsHuman as $line) {
                fwrite(STDERR, "  - {$line}\n");
            }
            if ($reportPath !== null) {
                file_put_contents($reportPath, encodeJsonDeterministic($report));
            }
            exit(1);
        }
        if (! empty($applicable)) {
            fwrite(STDERR, "\n[api-sync] FAIL -- pending drift, run `php scripts/api-sync.php --apply`:\n");
            foreach ($applicable as $c) {
                if ($c['kind'] === 'enum-member-added') {
                    fwrite(STDERR, "  - {$c['enum']}: missing case for spec value '{$c['value']}' ({$c['file']})\n");
                } else {
                    fwrite(STDERR, "  - {$c['schema']}".($c['path'] ? ".{$c['path']}" : '')." -> {$c['class']}: missing field '{$c['field']}' ({$c['file']})\n");
                }
            }
            if ($reportPath !== null) {
                $report['applied'] = $applicable;
                file_put_contents($reportPath, encodeJsonDeterministic($report));
            }
            exit(1);
        }
        if ($reportPath !== null) {
            file_put_contents($reportPath, encodeJsonDeterministic($report));
        }
        exit(0);
    }

    // --apply
    if (! empty($needsHuman)) {
        fwrite(STDERR, "\n[api-sync] FAIL -- needs a human decision, nothing applied:\n");
        foreach ($needsHuman as $line) {
            fwrite(STDERR, "  - {$line}\n");
        }
        if ($reportPath !== null) {
            file_put_contents($reportPath, encodeJsonDeterministic($report));
        }
        exit(1);
    }

    if (empty($applicable)) {
        if ($reportPath !== null) {
            file_put_contents($reportPath, encodeJsonDeterministic($report));
        }
        exit(0);
    }

    // Precompute the full spec-ordered value list per enum (for anchor lookup), sorted deterministically.
    $specValuesByEnum = [];
    foreach ($map['enums'] as $entry) {
        $values = enumSpecValues($newSpec, $entry);
        if ($values !== null) {
            $specValuesByEnum[$entry['sdk']['class']] = array_values($values);
        }
    }

    foreach ($applicable as $change) {
        if ($change['kind'] === 'enum-member-added') {
            applyEnumCaseInsertion($root, $change['file'], $change['enum'], $change['value'], $newSpec, $specValuesByEnum);
        } else {
            applyFieldInsertion($root, $change['file'], $change['class'], $change['field'], $change['propSchema']);
        }
    }

    $bump = computeBump($applicable);
    if ($bump !== null) {
        $newVersion = bumpVersion($root, $bump);
        $report['bump'] = ['type' => $bump, 'version' => $newVersion];
    }

    // Refresh the snapshot with the SOURCE SPEC FILE'S BYTES, verbatim -- never re-serialize via
    // json_decode/json_encode, which would silently reformat indentation, escaping and key order
    // and turn every future sync PR into an ~86k-line unreviewable snapshot diff.
    copy($specPath, resolveWithinRoot($root, '.api-sync/spec-snapshot.json', 'spec snapshot'));

    $report['applied'] = $applicable;
    if ($reportPath !== null) {
        file_put_contents($reportPath, encodeJsonDeterministic($report));
    }

    fwrite(STDOUT, '[api-sync] applied '.count($applicable).' change(s)'.($bump !== null ? ", version bump: {$bump} -> {$report['bump']['version']}" : '').".\n");
    exit(0);
}

if (! defined('API_SYNC_LIB_ONLY')) {
    runCli($argv, $root);
}
