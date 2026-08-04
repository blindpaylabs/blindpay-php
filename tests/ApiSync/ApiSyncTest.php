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

    // ---- NEEDS_HUMAN: type mismatches on already-modeled properties ----

    #[Test]
    public function a_string_property_changing_to_integer_in_the_spec_is_needs_human(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $newSpec = $this->baseSpec();
        $newSpec['components']['schemas']['WidgetOut']['properties']['name'] = ['type' => 'integer'];
        $reachable = computeReachable($newSpec);

        [$applicable, $needsHuman] = reconcileTypes($this->widgetMap(), $newSpec, $reachable, $classIndex, []);

        $this->assertEmpty($applicable);
        $this->assertNotEmpty(array_filter($needsHuman, fn ($i) => str_contains($i, 'WidgetOut.name') && str_contains($i, 'integer') && str_contains($i, 'string')));
    }

    #[Test]
    public function a_property_newly_allowing_null_while_the_sdk_stays_non_nullable_is_needs_human(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $oldSpec = $this->baseSpec(); // WidgetOut.name: {"type": "string"} -- non-nullable
        $newSpec = $this->baseSpec();
        $newSpec['components']['schemas']['WidgetOut']['properties']['name'] = ['type' => ['string', 'null']];
        $reachableOld = computeReachable($oldSpec);
        $reachableNew = computeReachable($newSpec);

        // WidgetResponse declares `public string $name` (no `?`) -- a real null would break it.
        $issues = computeStructuralDiff($oldSpec, $newSpec, $reachableOld, $reachableNew, $this->widgetMap(), $classIndex);

        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'WidgetOut.name') && str_contains($i, 'newly allows null') && str_contains($i, 'string')));
    }

    #[Test]
    public function an_enum_backed_sdk_property_whose_spec_constraint_degrades_to_a_bare_string_is_needs_human(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $newSpec = $this->baseSpec();
        // WidgetResponse declares `public WidgetColor $color` -- an enum class. If the spec drops
        // the enum constraint down to a bare, unconstrained string, WidgetColor::from() can now
        // throw on a value outside its known set.
        $newSpec['components']['schemas']['WidgetOut']['properties']['color'] = ['type' => 'string'];
        $reachable = computeReachable($newSpec);

        [$applicable, $needsHuman] = reconcileTypes($this->widgetMap(), $newSpec, $reachable, $classIndex, []);

        $this->assertEmpty($applicable);
        $this->assertNotEmpty(array_filter($needsHuman, fn ($i) => str_contains($i, 'WidgetOut.color')));
    }

    #[Test]
    public function an_integer_spec_property_modeled_as_a_php_float_is_deliberately_treated_as_compatible(): void
    {
        // PHP widens int to float safely, even under strict_types -- a spec `integer` read into a
        // `float`-typed property parses every value without error, so this is not a mismatch.
        $spec = specTypeCategory(['type' => 'integer']);
        $php = phpTypeCategory('float');

        $this->assertTrue(categoriesCompatible($spec, $php));
    }

    // ---- constructor insertion never reorders existing parameters ----

    #[Test]
    public function the_new_promoted_property_always_lands_last_and_no_existing_parameter_moves(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $before = array_column($classIndex['WidgetResponse']['ctor']['params'], 'name');

        applyFieldInsertion(
            $this->fixtureRoot,
            'src/Resources/Widgets/Widgets.php',
            'WidgetResponse',
            'nickname',
            ['type' => ['string', 'null']]
        );

        $after = array_column(scanAllClasses($this->fixtureRoot)['WidgetResponse']['ctor']['params'], 'name');

        $this->assertSame([...$before, 'nickname'], $after, 'existing parameters must keep their exact order, with the new one appended last');
        $this->assertPhpFileParses("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");
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

    // ---- --audit-types: non-blocking, full state comparison (not just forward drift) ----

    #[Test]
    public function audit_types_reports_a_pre_existing_nullability_mismatch_that_check_mode_would_not_flag(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        // WidgetResponse declares `public string $name` (non-nullable); the spec allows null.
        // This did NOT change between "old" and "new" here -- audit-types must still surface it,
        // unlike the blocking checks, which only fire on genuinely new drift.
        $spec = $this->baseSpec();
        $spec['components']['schemas']['WidgetOut']['properties']['name'] = ['type' => ['string', 'null']];
        $reachable = computeReachable($spec);

        $audit = auditTypes($this->widgetMap(), $spec, $reachable, $classIndex, []);

        $finding = current(array_filter($audit['findings'], fn ($f) => $f['field'] === 'WidgetOut.name'));
        $this->assertNotFalse($finding);
        $this->assertTrue($finding['nullabilityMismatch']);
        $this->assertFalse($finding['categoryMismatch']);
        $this->assertFalse($finding['recordedDivergence']);
    }

    #[Test]
    public function audit_types_marks_a_recorded_known_divergence_instead_of_hiding_it(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $spec = $this->baseSpec();
        $spec['components']['schemas']['WidgetOut']['properties']['name'] = ['type' => ['string', 'null']];
        $reachable = computeReachable($spec);

        $audit = auditTypes($this->widgetMap(), $spec, $reachable, $classIndex, ['WidgetOut|name' => true]);

        $finding = current(array_filter($audit['findings'], fn ($f) => $f['field'] === 'WidgetOut.name'));
        $this->assertNotFalse($finding);
        $this->assertTrue($finding['recordedDivergence'], 'a recorded divergence must still be reported, just marked, never hidden');
    }

    #[Test]
    public function audit_types_skips_discriminator_fan_outs_instead_of_guessing(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $map = $this->widgetMap();
        // Simulate a fan-out: the same WidgetOut schema modeled by two classes.
        $map['types'][0]['sdk'][] = ['file' => 'src/Resources/Widgets/Widgets.php', 'class' => 'WidgetInputLiteral'];
        $spec = $this->baseSpec();
        $reachable = computeReachable($spec);

        $audit = auditTypes($map, $spec, $reachable, $classIndex, []);

        $this->assertContains('WidgetOut', $audit['skippedFanOuts']);
        $this->assertEmpty($audit['findings']);
    }

    #[Test]
    public function audit_types_cli_mode_always_exits_zero_even_with_findings(): void
    {
        // Reuse the byte-copy fixture wiring (real script copy + full .api-sync/*.json set), but
        // seed a spec with a deliberate nullability mismatch so --audit-types has something to say.
        mkdir("{$this->fixtureRoot}/.api-sync", 0777, true);
        mkdir("{$this->fixtureRoot}/scripts", 0777, true);
        copy(__DIR__.'/../../scripts/api-sync.php', "{$this->fixtureRoot}/scripts/api-sync.php");
        file_put_contents("{$this->fixtureRoot}/src/BlindPay.php", "<?php\nclass BlindPay { private const VERSION = '1.0.0'; }\n");

        file_put_contents("{$this->fixtureRoot}/.api-sync/spec-map.json", json_encode($this->widgetMap()));
        file_put_contents("{$this->fixtureRoot}/.api-sync/unmodeled.json", json_encode(['entries' => []]));
        file_put_contents("{$this->fixtureRoot}/.api-sync/known-divergences.json", json_encode(['enumValues' => [], 'fields' => []]));
        file_put_contents("{$this->fixtureRoot}/.api-sync/spec-snapshot.json", json_encode($this->baseSpec()));

        $spec = $this->baseSpec();
        $spec['components']['schemas']['WidgetOut']['properties']['name'] = ['type' => ['string', 'null']];
        $specPath = "{$this->fixtureRoot}/.api-sync/spec-current.json";
        file_put_contents($specPath, json_encode($spec));

        exec(sprintf(
            'php %s --audit-types --spec=%s 2>&1',
            escapeshellarg("{$this->fixtureRoot}/scripts/api-sync.php"),
            escapeshellarg($specPath)
        ), $output, $exitCode);

        $this->assertSame(0, $exitCode);
        $this->assertNotEmpty(array_filter($output, fn ($l) => str_contains($l, 'WidgetOut.name')));
    }

    // ---- path validation: every path from a CLI arg or spec-map.json is resolved and validated
    // ---- before any read/write touches it, and writes derived from spec-map.json cannot escape
    // ---- the repository root.

    #[Test]
    public function resolve_readable_path_accepts_an_existing_file_and_returns_its_canonical_form(): void
    {
        $file = "{$this->fixtureRoot}/src/BlindPay.php";
        file_put_contents($file, "<?php\n");

        $resolved = resolveReadablePath($file, '--spec');

        $this->assertSame(realpath($file), $resolved);
    }

    #[Test]
    public function resolve_readable_path_rejects_a_nul_byte(): void
    {
        // A NUL byte cannot survive in a real argv element (execve() argv strings are
        // NUL-terminated), so this is tested as a direct unit call, isolated in a subprocess since
        // resolveReadablePath() calls exit(1) on rejection, which would otherwise kill the test
        // runner. The `"\0"` is a PHP string escape evaluated at runtime by the subprocess, not a
        // raw byte passed through shell argv.
        [$output, $exitCode] = $this->runPhpSnippetInSubprocess(
            'resolveReadablePath("/tmp/whatever"."\0".".json", "--spec");'
        );

        $this->assertSame(1, $exitCode);
        $this->assertNotEmpty(array_filter($output, fn ($l) => str_contains($l, 'invalid --spec path')));
    }

    #[Test]
    public function resolve_readable_path_rejects_a_file_that_does_not_exist(): void
    {
        [$output, $exitCode] = $this->runCliSubprocess(['--check', '--spec='."{$this->fixtureRoot}/does-not-exist.json"]);

        $this->assertSame(1, $exitCode);
        $this->assertNotEmpty(array_filter($output, fn ($l) => str_contains($l, 'does not exist')));
    }

    #[Test]
    public function resolve_writable_path_rejects_a_parent_directory_that_does_not_exist(): void
    {
        [$output, $exitCode] = $this->runCliSubprocess([
            '--check',
            '--spec='."{$this->fixtureRoot}/.api-sync/spec-snapshot.json",
            '--report='."{$this->fixtureRoot}/no-such-dir/report.json",
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertNotEmpty(array_filter($output, fn ($l) => str_contains($l, 'directory for --report does not exist')));
    }

    #[Test]
    public function resolve_writable_path_accepts_a_destination_outside_the_repository_root(): void
    {
        // Required, legitimate functionality: CI writes --report to /tmp; the determinism proof
        // writes into scratch copies elsewhere on disk. Writable paths are deliberately NOT
        // restricted to the repository root (only spec-map.json-driven writes are, see below).
        $outside = sys_get_temp_dir().'/blindpay-api-sync-report-'.bin2hex(random_bytes(6)).'.json';

        $resolved = resolveWritablePath($outside, '--report');

        $this->assertSame(realpath(dirname($outside)).'/'.basename($outside), $resolved);
    }

    #[Test]
    public function resolve_within_root_accepts_a_legitimate_spec_map_file(): void
    {
        $resolved = resolveWithinRoot($this->fixtureRoot, 'src/Resources/Widgets/Widgets.php', 'class file');

        $this->assertSame(realpath("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php"), $resolved);
    }

    #[Test]
    public function resolve_within_root_refuses_a_path_that_escapes_the_repository_root(): void
    {
        // Simulates a corrupted or malicious spec-map.json entry trying to write outside the repo.
        // Unit-tested directly against resolveWithinRoot(): going through the full CLI, validateMap()
        // would independently catch this first (the named class isn't actually declared at the
        // claimed file), which is good defense in depth but would mask the check this test targets.
        $escapeDir = dirname($this->fixtureRoot).'/escape-target-'.basename($this->fixtureRoot);
        mkdir($escapeDir, 0777, true);

        try {
            [$output, $exitCode] = $this->runPhpSnippetInSubprocess(sprintf(
                'resolveWithinRoot(%s, %s, "class file");',
                var_export($this->fixtureRoot, true),
                var_export('../'.basename($escapeDir).'/evil.php', true)
            ));

            $this->assertSame(1, $exitCode);
            $this->assertNotEmpty(array_filter($output, fn ($l) => str_contains($l, 'resolves outside the repository root')));
            $this->assertFileDoesNotExist("{$escapeDir}/evil.php");
        } finally {
            $this->removeDirectory($escapeDir);
        }
    }

    /** @return array{0: array<int, string>, 1: int} */
    private function runCliSubprocess(array $args): array
    {
        mkdir("{$this->fixtureRoot}/.api-sync", 0777, true);
        mkdir("{$this->fixtureRoot}/scripts", 0777, true);
        copy(__DIR__.'/../../scripts/api-sync.php', "{$this->fixtureRoot}/scripts/api-sync.php");
        file_put_contents("{$this->fixtureRoot}/src/BlindPay.php", "<?php\nclass BlindPay { private const VERSION = '1.0.0'; }\n");
        file_put_contents("{$this->fixtureRoot}/.api-sync/spec-map.json", json_encode($this->widgetMap()));
        file_put_contents("{$this->fixtureRoot}/.api-sync/unmodeled.json", json_encode(['entries' => []]));
        file_put_contents("{$this->fixtureRoot}/.api-sync/known-divergences.json", json_encode(['enumValues' => [], 'fields' => []]));
        file_put_contents("{$this->fixtureRoot}/.api-sync/spec-snapshot.json", json_encode($this->baseSpec()));

        $cmd = 'php '.escapeshellarg("{$this->fixtureRoot}/scripts/api-sync.php");
        foreach ($args as $arg) {
            $cmd .= ' '.escapeshellarg($arg);
        }
        exec($cmd.' 2>&1', $output, $exitCode);

        return [$output, $exitCode];
    }

    /**
     * Runs one PHP statement in a fresh subprocess that has already `require_once`'d api-sync.php
     * in lib-only mode, so a helper under test that calls exit() on failure doesn't kill the test
     * runner. Used to unit-test resolveReadablePath()/resolveWithinRoot()'s exit(1) paths directly.
     *
     * @return array{0: array<int, string>, 1: int}
     */
    private function runPhpSnippetInSubprocess(string $statement): array
    {
        $script = $this->fixtureRoot.'/snippet.php';
        file_put_contents($script, sprintf(
            "<?php\ndefine('API_SYNC_LIB_ONLY', true);\nrequire %s;\n%s\n",
            var_export(__DIR__.'/../../scripts/api-sync.php', true),
            $statement
        ));
        exec('php '.escapeshellarg($script).' 2>&1', $output, $exitCode);

        return [$output, $exitCode];
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
