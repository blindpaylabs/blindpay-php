<?php

declare(strict_types=1);

namespace BlindPay\SDK\Tests\ApiSync;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if (! defined('API_SYNC_LIB_ONLY')) {
    define('API_SYNC_LIB_ONLY', true);
}
require_once __DIR__.'/../../scripts/api-sync.php';

class ApiSyncTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        $this->fixtureRoot = sys_get_temp_dir().'/blindpay-api-sync-test-'.bin2hex(random_bytes(6));
        mkdir("{$this->fixtureRoot}/src/Types", 0777, true);
        mkdir("{$this->fixtureRoot}/src/Resources/Widgets", 0777, true);

        file_put_contents("{$this->fixtureRoot}/src/Types/WidgetColor.php", <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace BlindPay\SDK\Types;

            enum WidgetColor: string
            {
                case RED = 'red';
                case BLUE = 'blue';
            }

            PHP);

        file_put_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php", <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace BlindPay\SDK\Resources\Widgets;

            use BlindPay\SDK\Types\WidgetColor;

            readonly class WidgetResponse
            {
                public function __construct(
                    public string $id,
                    public string $name,
                    public WidgetColor $color
                ) {}

                public static function fromArray(array $data): self
                {
                    return new self(
                        id: $data['id'],
                        name: $data['name'],
                        color: WidgetColor::from($data['color'])
                    );
                }
            }

            readonly class WidgetInputLiteral
            {
                public function __construct(
                    public string $name
                ) {}

                public function toArray(): array
                {
                    return [
                        'name' => $this->name,
                    ];
                }
            }

            readonly class WidgetInputConditional
            {
                public function __construct(
                    public string $name,
                    public ?string $extra = null
                ) {}

                public function toArray(): array
                {
                    $data = [
                        'name' => $this->name,
                    ];

                    if ($this->extra !== null) {
                        $data['extra'] = $this->extra;
                    }

                    return $data;
                }
            }

            readonly class WidgetNoToArray
            {
                public function __construct(
                    public string $id
                ) {}

                public static function fromArray(array $data): self
                {
                    return new self(
                        id: $data['id']
                    );
                }
            }

            PHP);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixtureRoot);
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    private function baseSpec(array $overrides = []): array
    {
        $spec = [
            'paths' => [
                '/v1/widgets' => [
                    'post' => [
                        'requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/WidgetOut']]]],
                        'responses' => ['200' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/WidgetOut']]]]],
                    ],
                ],
            ],
            'webhooks' => [],
            'components' => [
                'schemas' => [
                    'WidgetOut' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'color' => ['type' => 'string', 'enum' => ['red', 'blue']],
                        ],
                    ],
                ],
            ],
        ];

        return array_replace_recursive($spec, $overrides);
    }

    private function widgetMap(): array
    {
        return [
            'enums' => [
                [
                    'spec' => ['schema' => 'WidgetOut', 'property' => 'color'],
                    'sdk' => ['file' => 'src/Types/WidgetColor.php', 'class' => 'WidgetColor'],
                ],
            ],
            'types' => [
                [
                    'spec' => 'WidgetOut',
                    'sdk' => [['file' => 'src/Resources/Widgets/Widgets.php', 'class' => 'WidgetResponse']],
                ],
            ],
            'ignore' => ['schemas' => []],
        ];
    }

    // ---- enum case insertion ----

    #[Test]
    public function it_detects_a_missing_enum_case_as_applicable(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $newSpec = $this->baseSpec(['components' => ['schemas' => ['WidgetOut' => ['properties' => ['color' => ['enum' => ['red', 'blue', 'green']]]]]]]);
        $reachable = computeReachable($newSpec);

        [$applicable, $needsHuman] = reconcileEnums($this->widgetMap(), $newSpec, $reachable, $classIndex, []);

        $this->assertEmpty($needsHuman);
        $this->assertCount(1, $applicable);
        $this->assertSame('enum-member-added', $applicable[0]['kind']);
        $this->assertSame('green', $applicable[0]['value']);
    }

    #[Test]
    public function it_applies_a_missing_enum_case_following_spec_order(): void
    {
        $newSpec = $this->baseSpec(['components' => ['schemas' => ['WidgetOut' => ['properties' => ['color' => ['enum' => ['red', 'green', 'blue']]]]]]]);
        $specValuesByEnum = ['WidgetColor' => ['red', 'green', 'blue']];

        applyEnumCaseInsertion($this->fixtureRoot, 'src/Types/WidgetColor.php', 'WidgetColor', 'green', $newSpec, $specValuesByEnum);

        $source = file_get_contents("{$this->fixtureRoot}/src/Types/WidgetColor.php");
        $this->assertStringContainsString("case RED = 'red';\n    case GREEN = 'green';\n    case BLUE = 'blue';", $source);
    }

    #[Test]
    public function it_honors_a_known_divergence_and_does_not_flag_a_mismatched_enum_value_as_missing(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $newSpec = $this->baseSpec(['components' => ['schemas' => ['WidgetOut' => ['properties' => ['color' => ['enum' => ['red', 'blu']]]]]]]); // spec has a typo'd value
        $reachable = computeReachable($newSpec);
        $divergenceIndex = ['WidgetColor|blu' => true];

        [$applicable, $needsHuman] = reconcileEnums($this->widgetMap(), $newSpec, $reachable, $classIndex, $divergenceIndex);

        $this->assertEmpty($needsHuman);
        $this->assertEmpty($applicable);
    }

    // ---- field insertion: three-part (constructor + fromArray + toArray) ----

    #[Test]
    public function it_detects_a_missing_field_as_applicable(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $newSpec = $this->baseSpec(['components' => ['schemas' => ['WidgetOut' => ['properties' => ['description' => ['type' => ['string', 'null']]]]]]]);
        $reachable = computeReachable($newSpec);

        [$applicable, $needsHuman] = reconcileTypes($this->widgetMap(), $newSpec, $reachable, $classIndex, []);

        $this->assertEmpty($needsHuman);
        $this->assertCount(1, $applicable);
        $this->assertSame('field-added', $applicable[0]['kind']);
        $this->assertSame('description', $applicable[0]['field']);
        $this->assertSame('WidgetResponse', $applicable[0]['class']);
    }

    #[Test]
    public function it_applies_the_three_part_insertion_for_a_class_with_both_fromarray_and_toarray(): void
    {
        // WidgetInputConditional has neither fromArray nor a *response* shape by itself; use a
        // class with both fromArray and toArray to exercise all three edits at once.
        file_put_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php", <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace BlindPay\SDK\Resources\Widgets;

            readonly class WidgetBoth
            {
                public function __construct(
                    public string $id,
                    public string $name
                ) {}

                public static function fromArray(array $data): self
                {
                    return new self(
                        id: $data['id'],
                        name: $data['name']
                    );
                }

                public function toArray(): array
                {
                    return [
                        'id' => $this->id,
                        'name' => $this->name,
                    ];
                }
            }

            PHP);

        applyFieldInsertion(
            $this->fixtureRoot,
            'src/Resources/Widgets/Widgets.php',
            'WidgetBoth',
            'description',
            ['type' => ['string', 'null']]
        );

        $source = file_get_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");

        $this->assertStringContainsString('public string $name,', $source);
        $this->assertStringContainsString('public ?string $description = null', $source);
        $this->assertStringContainsString("name: \$data['name'],", $source);
        $this->assertStringContainsString("description: \$data['description'] ?? null", $source);
        $this->assertStringContainsString("'name' => \$this->name,", $source);
        $this->assertStringContainsString("'description' => \$this->description,", $source);

        // still valid PHP
        $this->assertPhpFileParses("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");
    }

    #[Test]
    public function it_applies_only_two_edits_for_a_class_without_toarray(): void
    {
        applyFieldInsertion(
            $this->fixtureRoot,
            'src/Resources/Widgets/Widgets.php',
            'WidgetNoToArray',
            'label',
            ['type' => 'string']
        );

        $source = file_get_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");
        $this->assertStringContainsString('public ?string $label = null', $source);
        $this->assertStringContainsString("label: \$data['label'] ?? null", $source);
        // no toArray in this class at all -- nothing named 'label' =>  should appear
        $this->assertStringNotContainsString("'label' =>", $source);

        $this->assertPhpFileParses("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");
    }

    #[Test]
    public function it_uses_the_literal_style_for_a_class_whose_toarray_is_an_unconditional_array(): void
    {
        applyFieldInsertion(
            $this->fixtureRoot,
            'src/Resources/Widgets/Widgets.php',
            'WidgetInputLiteral',
            'nickname',
            ['type' => ['string', 'null']]
        );

        $source = file_get_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");
        $this->assertStringContainsString("'nickname' => \$this->nickname,", $source);
        $this->assertStringNotContainsString('if ($this->nickname !== null)', $source);
        $this->assertPhpFileParses("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");
    }

    #[Test]
    public function it_uses_the_conditional_style_for_a_class_whose_toarray_already_conditionally_includes_fields(): void
    {
        applyFieldInsertion(
            $this->fixtureRoot,
            'src/Resources/Widgets/Widgets.php',
            'WidgetInputConditional',
            'nickname',
            ['type' => ['string', 'null']]
        );

        $source = file_get_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");
        $this->assertStringContainsString('if ($this->nickname !== null) {', $source);
        $this->assertStringContainsString("\$data['nickname'] = \$this->nickname;", $source);
        $this->assertPhpFileParses("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");
    }

    #[Test]
    public function it_honors_unmodeled_json_and_does_not_flag_a_listed_field_as_missing(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $newSpec = $this->baseSpec(['components' => ['schemas' => ['WidgetOut' => ['properties' => ['legacy_field' => ['type' => 'string']]]]]]);
        $reachable = computeReachable($newSpec);
        $unmodeledIndex = ['WidgetOut||legacy_field' => true];

        [$applicable, $needsHuman] = reconcileTypes($this->widgetMap(), $newSpec, $reachable, $classIndex, $unmodeledIndex);

        $this->assertEmpty($needsHuman);
        $this->assertEmpty($applicable);
    }

    // ---- idempotency ----

    #[Test]
    public function applying_a_field_twice_only_inserts_it_once(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $newSpec = $this->baseSpec(['components' => ['schemas' => ['WidgetOut' => ['properties' => ['description' => ['type' => ['string', 'null']]]]]]]);
        $reachable = computeReachable($newSpec);

        [$applicable] = reconcileTypes($this->widgetMap(), $newSpec, $reachable, $classIndex, []);
        $this->assertCount(1, $applicable);
        applyFieldInsertion($this->fixtureRoot, $applicable[0]['file'], $applicable[0]['class'], $applicable[0]['field'], $applicable[0]['propSchema']);

        // re-scan and reconcile again -- should now be fully caught up, nothing left to apply
        $classIndex = scanAllClasses($this->fixtureRoot);
        [$applicableAgain, $needsHumanAgain] = reconcileTypes($this->widgetMap(), $newSpec, $reachable, $classIndex, []);
        $this->assertEmpty($applicableAgain);
        $this->assertEmpty($needsHumanAgain);

        // one edit each in the constructor, fromArray and toArray -- never a duplicate insertion.
        $source = file_get_contents("{$this->fixtureRoot}/{$applicable[0]['file']}");
        $this->assertSame(3, substr_count($source, 'description'));
    }

    // ---- NEEDS_HUMAN: removals ----

    #[Test]
    public function it_flags_a_removed_property_as_needs_human(): void
    {
        $oldSpec = $this->baseSpec();
        $newSpec = $this->baseSpec();
        unset($newSpec['components']['schemas']['WidgetOut']['properties']['name']);
        $reachableOld = computeReachable($oldSpec);
        $reachableNew = computeReachable($newSpec);

        $issues = computeStructuralDiff($oldSpec, $newSpec, $reachableOld, $reachableNew, $this->widgetMap());

        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'removed from WidgetOut') && str_contains($i, 'name')));
    }

    #[Test]
    public function it_flags_a_removed_enum_value_as_needs_human(): void
    {
        $oldSpec = $this->baseSpec();
        $newSpec = $this->baseSpec();
        // array_replace_recursive merges indexed arrays by key, it cannot truncate one -- replace outright.
        $newSpec['components']['schemas']['WidgetOut']['properties']['color']['enum'] = ['red'];
        $reachableOld = computeReachable($oldSpec);
        $reachableNew = computeReachable($newSpec);

        $issues = computeStructuralDiff($oldSpec, $newSpec, $reachableOld, $reachableNew, $this->widgetMap());

        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'enum value(s) removed from WidgetColor') && str_contains($i, 'blue')));
    }

    #[Test]
    public function it_flags_a_removed_operation_as_needs_human(): void
    {
        $oldSpec = $this->baseSpec();
        $newSpec = $this->baseSpec();
        unset($newSpec['paths']['/v1/widgets']['post']);
        unset($newSpec['paths']['/v1/widgets']);
        $reachableOld = computeReachable($oldSpec);
        $reachableNew = computeReachable($newSpec);

        $issues = computeStructuralDiff($oldSpec, $newSpec, $reachableOld, $reachableNew, $this->widgetMap());

        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'operation removed: post /v1/widgets')));
    }

    #[Test]
    public function it_flags_a_new_operation_as_needs_human(): void
    {
        $oldSpec = $this->baseSpec();
        $newSpec = $this->baseSpec();
        $newSpec['paths']['/v1/gadgets'] = ['post' => ['requestBody' => [], 'responses' => []]];
        $reachableOld = computeReachable($oldSpec);
        $reachableNew = computeReachable($newSpec);

        $issues = computeStructuralDiff($oldSpec, $newSpec, $reachableOld, $reachableNew, $this->widgetMap());

        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'new operation: post /v1/gadgets')));
    }

    #[Test]
    public function it_flags_a_new_reachable_unmapped_schema_as_needs_human(): void
    {
        $oldSpec = $this->baseSpec();
        $newSpec = $this->baseSpec();
        $newSpec['components']['schemas']['GadgetOut'] = ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]];
        $newSpec['paths']['/v1/widgets']['post']['responses']['200']['content']['application/json']['schema'] = ['$ref' => '#/components/schemas/GadgetOut'];
        $reachableOld = computeReachable($oldSpec);
        $reachableNew = computeReachable($newSpec);

        $issues = computeStructuralDiff($oldSpec, $newSpec, $reachableOld, $reachableNew, $this->widgetMap());

        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'new schema: GadgetOut')));
    }

    #[Test]
    public function a_new_unreferenced_schema_is_not_flagged_at_all(): void
    {
        $oldSpec = $this->baseSpec();
        $newSpec = $this->baseSpec();
        $newSpec['components']['schemas']['OrphanOut'] = ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]];
        $reachableOld = computeReachable($oldSpec);
        $reachableNew = computeReachable($newSpec);

        $issues = computeStructuralDiff($oldSpec, $newSpec, $reachableOld, $reachableNew, $this->widgetMap());

        $this->assertEmpty(array_filter($issues, fn ($i) => str_contains($i, 'OrphanOut')));
    }

    // ---- map validity ----

    #[Test]
    public function it_reports_a_map_anchor_that_does_not_exist(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $map = $this->widgetMap();
        $map['types'][0]['sdk'][0]['class'] = 'NoSuchClass';

        $errors = validateMap($map, $classIndex, $this->fixtureRoot);

        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'NoSuchClass')));
    }

    #[Test]
    public function it_reports_a_map_anchor_at_the_wrong_file(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $map = $this->widgetMap();
        $map['types'][0]['sdk'][0]['file'] = 'src/Resources/Widgets/WrongFile.php';

        $errors = validateMap($map, $classIndex, $this->fixtureRoot);

        $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, 'mismatch')));
    }

    #[Test]
    public function a_valid_map_produces_no_errors(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $errors = validateMap($this->widgetMap(), $classIndex, $this->fixtureRoot);
        $this->assertEmpty($errors);
    }

    // ---- version bump classification ----

    #[Test]
    public function an_enum_member_addition_bumps_minor(): void
    {
        $this->assertSame('minor', computeBump([['kind' => 'enum-member-added']]));
    }

    #[Test]
    public function a_field_only_addition_bumps_patch(): void
    {
        $this->assertSame('patch', computeBump([['kind' => 'field-added']]));
    }

    #[Test]
    public function a_mix_of_enum_and_field_changes_bumps_minor(): void
    {
        $this->assertSame('minor', computeBump([['kind' => 'field-added'], ['kind' => 'enum-member-added']]));
    }

    #[Test]
    public function no_changes_means_no_bump(): void
    {
        $this->assertNull(computeBump([]));
    }

    #[Test]
    public function version_string_bumps_correctly(): void
    {
        $this->assertSame('3.1.0', bumpVersionString('3.0.0', 'minor'));
        $this->assertSame('3.0.6', bumpVersionString('3.0.5', 'patch'));
        $this->assertSame('3.1.0', bumpVersionString('3.0.9', 'minor'));
    }

    // ---- snapshot refresh must copy bytes verbatim, never re-serialize ----

    #[Test]
    public function apply_refreshes_the_snapshot_as_a_byte_identical_copy_of_the_source_spec(): void
    {
        // A full, real invocation via a copy of the real script, run as a subprocess against a
        // throwaway repo root -- exercises the exact code path apply uses, including the file
        // copy, without exiting the test process.
        mkdir("{$this->fixtureRoot}/.api-sync", 0777, true);
        mkdir("{$this->fixtureRoot}/scripts", 0777, true);
        copy(__DIR__.'/../../scripts/api-sync.php', "{$this->fixtureRoot}/scripts/api-sync.php");
        file_put_contents("{$this->fixtureRoot}/src/BlindPay.php", <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace BlindPay\SDK;

            class BlindPay
            {
                private const VERSION = '1.0.0';
            }

            PHP);

        file_put_contents("{$this->fixtureRoot}/.api-sync/spec-map.json", json_encode($this->widgetMap()));
        file_put_contents("{$this->fixtureRoot}/.api-sync/unmodeled.json", json_encode(['entries' => []]));
        file_put_contents("{$this->fixtureRoot}/.api-sync/known-divergences.json", json_encode(['enumValues' => [], 'fields' => []]));
        file_put_contents("{$this->fixtureRoot}/.api-sync/spec-snapshot.json", json_encode($this->baseSpec(), JSON_PRETTY_PRINT));

        // Deliberately unusual formatting (2-space indent, escaped slashes, a trailing newline) so
        // a round-tripped json_decode/json_encode would visibly differ from a raw byte copy.
        $newSpec = $this->baseSpec();
        $newSpec['components']['schemas']['WidgetOut']['properties']['description'] = ['type' => ['string', 'null']];
        $sourceSpecPath = "{$this->fixtureRoot}/.api-sync/spec-current.json";
        // default json_encode escapes slashes and this appends a distinctive double trailing
        // newline, so a re-serialized (json_decode + json_encode) snapshot would visibly differ.
        file_put_contents($sourceSpecPath, json_encode($newSpec, JSON_PRETTY_PRINT).\PHP_EOL.\PHP_EOL);

        $cmd = sprintf(
            'php %s --apply --spec=%s 2>&1',
            escapeshellarg("{$this->fixtureRoot}/scripts/api-sync.php"),
            escapeshellarg($sourceSpecPath)
        );
        exec($cmd, $output, $exitCode);

        $this->assertSame(0, $exitCode, 'apply failed: '.implode("\n", $output));
        $this->assertSame(
            file_get_contents($sourceSpecPath),
            file_get_contents("{$this->fixtureRoot}/.api-sync/spec-snapshot.json"),
            'refreshed snapshot must be a byte-identical copy of the source spec file, not a re-serialization'
        );
    }

    // ---- helper assertion ----

    private function assertPhpFileParses(string $path): void
    {
        $output = [];
        $exitCode = 0;
        exec('php -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, "php -l failed for {$path}: ".implode("\n", $output));
    }
}
