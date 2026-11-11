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
                        'x-sdk' => true,
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

    #[Test]
    public function a_multi_schema_entry_missing_the_same_field_on_both_schemas_is_only_scheduled_once(): void
    {
        // Reproduces the exact double-apply bug: a single spec-map.json entry with TWO schemas
        // (e.g. spec: ["PayinOut", "CreatePayinOut"]) sharing one SDK class. If both schemas are
        // independently missing the same field, reconcileTypes() used to emit one 'field-added'
        // per schema -- two entries for the same (class, field) -- and --apply inserted the
        // promoted property/fromArray line/toArray line twice into one file in a single run.
        $map = $this->widgetMap();
        $map['types'][0]['spec'] = ['WidgetOut', 'WidgetOutAlias'];

        $classIndex = scanAllClasses($this->fixtureRoot);
        $newSpec = $this->baseSpec([
            'components' => ['schemas' => [
                'WidgetOut' => ['properties' => ['description' => ['type' => ['string', 'null']]]],
                'WidgetOutAlias' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'color' => ['type' => 'string', 'enum' => ['red', 'blue']],
                        'description' => ['type' => ['string', 'null']],
                    ],
                ],
            ]],
        ]);
        // WidgetOutAlias is only reachable if something references it -- webhooks is a
        // convenient root for a schema with no operation of its own.
        $newSpec['webhooks'] = ['widget.alias' => ['post' => ['requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/WidgetOutAlias']]]]]]];
        $reachable = computeReachable($newSpec);

        [$applicable, $needsHuman] = reconcileTypes($map, $newSpec, $reachable, $classIndex, []);

        $this->assertEmpty($needsHuman);
        $this->assertCount(1, $applicable, 'the same (class, field) gap reported by two schemas in one entry must be scheduled only once');
        $this->assertSame('description', $applicable[0]['field']);
        $this->assertSame('WidgetResponse', $applicable[0]['class']);

        foreach ($applicable as $change) {
            applyFieldInsertion($this->fixtureRoot, $change['file'], $change['class'], $change['field'], $change['propSchema']);
        }

        $source = file_get_contents("{$this->fixtureRoot}/{$applicable[0]['file']}");
        $this->assertSame(3, substr_count($source, 'description'), 'exactly one constructor param + one fromArray line + one toArray line, never two of each');

        // a second --apply-equivalent pass over the now-patched source finds nothing left to do.
        $classIndex = scanAllClasses($this->fixtureRoot);
        [$applicableAgain, $needsHumanAgain] = reconcileTypes($map, $newSpec, $reachable, $classIndex, []);
        $this->assertEmpty($applicableAgain);
        $this->assertEmpty($needsHumanAgain);
    }

    #[Test]
    public function two_separate_entries_mapping_the_same_class_missing_the_same_field_are_only_scheduled_once(): void
    {
        // Same bug, different trigger shape: TWO separate spec-map.json `types` entries (not one
        // multi-schema entry) independently point at the same SDK class -- this is exactly how
        // TrackingTransaction is reused across the payin and payout tracking_transaction paths.
        $map = $this->widgetMap();
        $map['types'][] = [
            'spec' => 'WidgetOutAlias',
            'sdk' => [['file' => 'src/Resources/Widgets/Widgets.php', 'class' => 'WidgetResponse']],
        ];

        $classIndex = scanAllClasses($this->fixtureRoot);
        $newSpec = $this->baseSpec([
            'components' => ['schemas' => [
                'WidgetOut' => ['properties' => ['description' => ['type' => ['string', 'null']]]],
                'WidgetOutAlias' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'color' => ['type' => 'string', 'enum' => ['red', 'blue']],
                        'description' => ['type' => ['string', 'null']],
                    ],
                ],
            ]],
        ]);
        $newSpec['webhooks'] = ['widget.alias' => ['post' => ['requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/WidgetOutAlias']]]]]]];
        $reachable = computeReachable($newSpec);

        [$applicable, $needsHuman] = reconcileTypes($map, $newSpec, $reachable, $classIndex, []);

        $this->assertEmpty($needsHuman);
        $this->assertCount(1, $applicable, 'two entries mapping the same class must not double-schedule the same missing field');

        foreach ($applicable as $change) {
            applyFieldInsertion($this->fixtureRoot, $change['file'], $change['class'], $change['field'], $change['propSchema']);
        }

        $source = file_get_contents("{$this->fixtureRoot}/{$applicable[0]['file']}");
        $this->assertSame(3, substr_count($source, 'description'));
    }

    #[Test]
    public function two_enum_entries_mapping_the_same_class_missing_the_same_value_are_only_scheduled_once(): void
    {
        // Mirrors the field-insertion bug on the enum side: two spec-map.json `enums` entries
        // (e.g. the same LimitIncreaseRequestSupportingDocumentType class mapped from both
        // CustomerLimitIncreaseIn and GetCustomerLimitIncreaseOut) independently report the same
        // missing case, which used to insert it twice into the enum file in one --apply run.
        $map = $this->widgetMap();
        $map['enums'][] = [
            'spec' => ['schema' => 'WidgetOutAlias', 'property' => 'color'],
            'sdk' => ['file' => 'src/Types/WidgetColor.php', 'class' => 'WidgetColor'],
        ];

        $classIndex = scanAllClasses($this->fixtureRoot);
        $newSpec = $this->baseSpec([
            'components' => ['schemas' => [
                'WidgetOut' => ['properties' => ['color' => ['enum' => ['red', 'blue', 'green']]]],
                'WidgetOutAlias' => [
                    'type' => 'object',
                    'properties' => [
                        'color' => ['type' => 'string', 'enum' => ['red', 'blue', 'green']],
                    ],
                ],
            ]],
        ]);
        $newSpec['webhooks'] = ['widget.alias' => ['post' => ['requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/WidgetOutAlias']]]]]]];
        $reachable = computeReachable($newSpec);

        [$applicable, $needsHuman] = reconcileEnums($map, $newSpec, $reachable, $classIndex, []);

        $this->assertEmpty($needsHuman);
        $this->assertCount(1, $applicable, 'two enum entries reporting the same missing case on the same class must not double-schedule it');
        $this->assertSame('green', $applicable[0]['value']);

        $specValuesByEnum = ['WidgetColor' => ['red', 'blue', 'green']];
        applyEnumCaseInsertion($this->fixtureRoot, $applicable[0]['file'], 'WidgetColor', 'green', $newSpec, $specValuesByEnum);

        $source = file_get_contents("{$this->fixtureRoot}/src/Types/WidgetColor.php");
        $this->assertSame(1, substr_count($source, "case GREEN = 'green';"));
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
    public function computestructuraldiff_no_longer_reports_new_operations_itself(): void
    {
        // New-operation classification moved to reconcileOperations() (STANDARD vs NON-STANDARD),
        // so computeStructuralDiff() must stay silent about additions -- only removals are still
        // its concern.
        $oldSpec = $this->baseSpec();
        $newSpec = $this->baseSpec();
        $newSpec['paths']['/v1/gadgets'] = ['post' => ['x-sdk' => true, 'tags' => ['Gadgets'], 'requestBody' => [], 'responses' => []]];
        $reachableOld = computeReachable($oldSpec);
        $reachableNew = computeReachable($newSpec);

        $issues = computeStructuralDiff($oldSpec, $newSpec, $reachableOld, $reachableNew, $this->widgetMap());

        $this->assertEmpty(array_filter($issues, fn ($i) => str_contains($i, 'gadgets')));
    }

    // ---- operation reconciliation: STANDARD (operation-insert) vs NON-STANDARD (needs-human) ----

    #[Test]
    public function it_flags_a_new_operation_with_no_matching_resource_tag_as_needs_human_with_a_specific_reason(): void
    {
        $oldSpec = $this->baseSpec();
        $newSpec = $this->baseSpec();
        $newSpec['paths']['/v1/gadgets'] = ['post' => [
            'x-sdk' => true,
            'tags' => ['Gadgets'],
            'responses' => ['200' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]]]]],
        ]];

        [$applicable, $needsHuman] = reconcileOperations($oldSpec, $newSpec);

        $this->assertEmpty($applicable);
        $this->assertNotEmpty(array_filter($needsHuman, fn ($i) => str_contains($i, 'post /v1/gadgets') && str_contains($i, "no matching resource for operation's tag 'Gadgets'")));
    }

    #[Test]
    public function it_flags_a_new_operation_with_a_multipart_request_body_as_needs_human_with_a_precise_reason(): void
    {
        $oldSpec = $this->baseSpec();
        $newSpec = $this->baseSpec();
        $newSpec['paths']['/v1/instances/{instance_id}/widgets/upload'] = ['post' => [
            'x-sdk' => true,
            'tags' => ['Available'],
            'requestBody' => ['content' => ['multipart/form-data' => ['schema' => ['type' => 'object']]]],
            'responses' => ['200' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]]],
        ]];

        [$applicable, $needsHuman] = reconcileOperations($oldSpec, $newSpec);

        $this->assertEmpty($applicable);
        $this->assertNotEmpty(array_filter($needsHuman, fn ($i) => str_contains($i, 'multipart/form-data request body not supported by generator')));
    }

    #[Test]
    public function it_flags_a_new_operation_with_a_polymorphic_response_as_needs_human(): void
    {
        $oldSpec = $this->baseSpec();
        $newSpec = $this->baseSpec();
        $newSpec['paths']['/v1/instances/{instance_id}/widgets/poly'] = ['get' => [
            'x-sdk' => true,
            'tags' => ['Available'],
            'responses' => ['200' => ['content' => ['application/json' => ['schema' => ['oneOf' => [['type' => 'object'], ['type' => 'string']]]]]]],
        ]];

        [$applicable, $needsHuman] = reconcileOperations($oldSpec, $newSpec);

        $this->assertEmpty($applicable);
        $this->assertNotEmpty(array_filter($needsHuman, fn ($i) => str_contains($i, 'polymorphic/composed response body schema')));
    }

    #[Test]
    public function it_classifies_a_standard_new_json_operation_as_an_operation_insert(): void
    {
        $oldSpec = $this->baseSpec();
        $newSpec = $this->baseSpec();
        $newSpec['paths']['/v1/instances/{instance_id}/widgets/standard'] = ['get' => [
            'x-sdk' => true,
            'tags' => ['Available'],
            'responses' => ['200' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]]]]],
        ]];

        [$applicable, $needsHuman] = reconcileOperations($oldSpec, $newSpec);

        $this->assertEmpty($needsHuman);
        $this->assertCount(1, $applicable);
        $this->assertSame('operation-insert', $applicable[0]['kind']);
        $this->assertSame('Available', $applicable[0]['resource']['class']);
    }

    // ---- golden self-test: delete a hand-written GET and a hand-written POST-with-body method
    // (plus their generated classes) from a repo shaped like the real one, run --apply against
    // the spec that already contains both operations, and assert the regenerated code matches the
    // deleted originals' route/verb/parameter/return-type shape; then assert a second --apply is a
    // byte-identical no-op, and that a synthetic multipart operation lands in needs-human. ----

    /** Spec used by the golden test: the baseline op the fixture starts with, plus two new
     * "Widgets" resource operations (a GET-by-id and a POST-with-body) under the "Widgets" tag
     * that resourceForTag() maps to this fixture's own Widgets.php/Widgets class. */
    private function goldenNewSpec(): array
    {
        return $this->baseSpec([
            'paths' => [
                '/v1/instances/{instance_id}/widgets/{id}' => [
                    'get' => [
                        'x-sdk' => true,
                        'tags' => ['Widgets'],
                        'responses' => ['200' => ['content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'string'], 'name' => ['type' => 'string']],
                            'required' => ['id', 'name'],
                        ]]]]],
                    ],
                ],
                '/v1/instances/{instance_id}/widgets' => [
                    'post' => [
                        'x-sdk' => true,
                        'tags' => ['Widgets'],
                        'requestBody' => ['content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => ['name' => ['type' => 'string'], 'note' => ['type' => ['string', 'null']]],
                            'required' => ['name'],
                        ]]]],
                        'responses' => ['200' => ['content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'string'], 'name' => ['type' => 'string'], 'note' => ['type' => ['string', 'null']]],
                            'required' => ['id', 'name'],
                        ]]]]],
                    ],
                ],
            ],
        ]);
    }

    /** The hand-written "as it exists today" source for the golden test's Widgets resource class,
     * matching goldenNewSpec()'s two operations field-for-field. */
    private function goldenWidgetsResourceSource(): string
    {
        return <<<'PHP'


            readonly class GetWidgetResponse
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
            }

            readonly class CreateWidgetInput
            {
                public function __construct(
                    public string $name,
                    public ?string $note = null
                ) {}

                public function toArray(): array
                {
                    $data = [
                        'name' => $this->name,
                    ];

                    if ($this->note !== null) {
                        $data['note'] = $this->note;
                    }

                    return $data;
                }
            }

            readonly class CreateWidgetResponse
            {
                public function __construct(
                    public string $id,
                    public string $name,
                    public ?string $note = null
                ) {}

                public static function fromArray(array $data): self
                {
                    return new self(
                        id: $data['id'],
                        name: $data['name'],
                        note: $data['note'] ?? null
                    );
                }
            }

            class Widgets
            {
                public function __construct(
                    private readonly string $instanceId,
                    private readonly ApiClientInterface $client
                ) {}

                /**
                 * @return BlindPayApiResponse<GetWidgetResponse>
                 */
                public function get(string $id, ?RequestOptions $options = null): BlindPayApiResponse
                {
                    if ($id === '') {
                        return BlindPayApiResponse::error(
                            ErrorResponse::validation('ID cannot be empty')
                        );
                    }

                    $response = $this->client->get(Path::of('instances/%s/widgets/%s', $this->instanceId, $id), $options);

                    return $response->map(fn (array $data) => GetWidgetResponse::fromArray($data));
                }

                /**
                 * @return BlindPayApiResponse<CreateWidgetResponse>
                 */
                public function create(CreateWidgetInput $input, ?RequestOptions $options = null): BlindPayApiResponse
                {
                    $response = $this->client->post(
                        Path::of('instances/%s/widgets', $this->instanceId),
                        $input->toArray(),
                        $options
                    );

                    return $response->map(fn (array $data) => CreateWidgetResponse::fromArray($data));
                }
            }

            PHP;
    }

    /** Builds a full scratch repo (real script copy + .api-sync/*.json + BlindPay.php + the
     * type-only Widgets.php from setUp(), extended with the hand-written resource class above) at
     * a fresh temp path, independent of $this->fixtureRoot so the golden test can freely mutate it. */
    private function buildGoldenRepo(): string
    {
        $root = sys_get_temp_dir().'/blindpay-api-sync-golden-'.bin2hex(random_bytes(6));
        mkdir("{$root}/src/Types", 0777, true);
        mkdir("{$root}/src/Resources/Widgets", 0777, true);
        mkdir("{$root}/.api-sync", 0777, true);
        mkdir("{$root}/scripts", 0777, true);

        copy("{$this->fixtureRoot}/src/Types/WidgetColor.php", "{$root}/src/Types/WidgetColor.php");
        copy(__DIR__.'/../../scripts/api-sync.php', "{$root}/scripts/api-sync.php");

        $baseWidgetsSource = file_get_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");
        $baseWidgetsSource = str_replace(
            "use BlindPay\SDK\Types\WidgetColor;\n",
            "use BlindPay\SDK\Internal\ApiClientInterface;\nuse BlindPay\SDK\Internal\Path;\nuse BlindPay\SDK\Types\BlindPayApiResponse;\nuse BlindPay\SDK\Types\ErrorResponse;\nuse BlindPay\SDK\Types\RequestOptions;\nuse BlindPay\SDK\Types\WidgetColor;\n",
            $baseWidgetsSource
        );
        file_put_contents("{$root}/src/Resources/Widgets/Widgets.php", rtrim($baseWidgetsSource)."\n".$this->goldenWidgetsResourceSource());

        file_put_contents("{$root}/src/BlindPay.php", "<?php\nclass BlindPay { private const VERSION = '1.0.0'; }\n");
        file_put_contents("{$root}/.api-sync/spec-map.json", json_encode($this->widgetMap()));
        file_put_contents("{$root}/.api-sync/unmodeled.json", json_encode(['entries' => []]));
        file_put_contents("{$root}/.api-sync/known-divergences.json", json_encode(['enumValues' => [], 'fields' => []]));
        // The "old" baseline this scratch repo's SDK was (per the golden test's premise) written
        // against -- deliberately missing the two Widgets operations, so reconcileOperations()'s
        // old-vs-new diff treats them as newly appeared, exactly as it would for a real spec bump.
        file_put_contents("{$root}/.api-sync/spec-snapshot.json", json_encode($this->baseSpec()));

        return $root;
    }

    #[Test]
    public function golden_self_test_regenerates_two_deleted_methods_idempotently_and_needs_humans_a_multipart_op(): void
    {
        $root = $this->buildGoldenRepo();

        try {
            $widgetsPath = "{$root}/src/Resources/Widgets/Widgets.php";
            $originalSource = file_get_contents($widgetsPath);
            $this->assertPhpFileParses($widgetsPath);

            // 1) Delete both hand-written methods and their three generated classes, simulating
            // the SDK having fallen behind a spec that already has these two operations. (There
            // are no spec-map.json/coverage-ledger entries referencing them to remove: their
            // request/response bodies are inline schemas, never eligible for a coverage-ledger
            // entry in the first place -- see nonStandardBodyReason()'s scope note.)
            $mutated = preg_replace('/\n\nreadonly class GetWidgetResponse.*\nclass Widgets\n\{\n/s', "\n\nclass Widgets\n{\n", $originalSource);
            $mutated = preg_replace('/\n\n    \/\*\*\n     \* @return BlindPayApiResponse<GetWidgetResponse>.*\n\}\n$/s', "\n}\n", $mutated);
            $this->assertStringNotContainsString('GetWidgetResponse', $mutated, 'setup sanity check: the deletion regex must actually strip the target classes');
            $this->assertStringNotContainsString('function create(', $mutated);
            // The regenerated code must bring its own imports back: drop the ones only the
            // deleted methods use, so the file starts without them.
            $mutated = str_replace(["use BlindPay\\SDK\\Internal\\Path;\n", "use BlindPay\\SDK\\Types\\ErrorResponse;\n", "use BlindPay\\SDK\\Types\\RequestOptions;\n"], '', $mutated);
            $this->assertStringNotContainsString('Internal\\Path;', $mutated);
            file_put_contents($widgetsPath, $mutated);
            $this->assertPhpFileParses($widgetsPath);

            // 2) Run --apply against the spec that already has both operations.
            $specPath = "{$root}/.api-sync/spec-current.json";
            file_put_contents($specPath, json_encode($this->goldenNewSpec()));

            $cmd = sprintf('php %s --apply --spec=%s 2>&1', escapeshellarg("{$root}/scripts/api-sync.php"), escapeshellarg($specPath));
            exec($cmd, $output, $exitCode);
            $this->assertSame(0, $exitCode, 'apply failed: '.implode("\n", $output));
            $this->assertPhpFileParses($widgetsPath);

            $regenerated = file_get_contents($widgetsPath);

            // 3) Assert the regenerated method matches the deleted original's route, HTTP verb,
            // and parameter/return types (class *names* are allowed to differ -- the generator
            // derives them from the method name, not from the hand-written original's naming
            // choice -- but the wire route, verb, and field-level shape must match exactly).
            $this->assertMatchesRegularExpression('/public function get\(string \$id, \?RequestOptions \$options = null\): BlindPayApiResponse/', $regenerated);
            $this->assertStringContainsString("if (\$id === '') {", $regenerated);
            $this->assertStringContainsString("ErrorResponse::validation('ID cannot be empty')", $regenerated);
            $this->assertStringContainsString("\$this->client->get(Path::of('instances/%s/widgets/%s', \$this->instanceId, \$id), \$options);", $regenerated);
            $this->assertStringContainsString('return $response->map(fn (array $data) => GetResponse::fromArray($data));', $regenerated);
            $this->assertMatchesRegularExpression('/public function create\((\w+) \$input, \?RequestOptions \$options = null\): BlindPayApiResponse/', $regenerated);
            $this->assertStringContainsString("\$this->client->post(\n            Path::of('instances/%s/widgets', \$this->instanceId),\n            \$input->toArray(),\n            \$options\n        );", $regenerated);
            $this->assertStringContainsString('return $response->map(fn (array $data) => CreateResponse::fromArray($data));', $regenerated);
            $this->assertStringNotContainsString('{$this->instanceId}', $regenerated, 'paths go through Path::of(), never string interpolation');
            // The imports the new code needs are back, in the file's alphabetical order.
            $this->assertStringContainsString("use BlindPay\\SDK\\Internal\\ApiClientInterface;\nuse BlindPay\\SDK\\Internal\\Path;\nuse BlindPay\\SDK\\Types\\BlindPayApiResponse;\nuse BlindPay\\SDK\\Types\\ErrorResponse;\nuse BlindPay\\SDK\\Types\\RequestOptions;\nuse BlindPay\\SDK\\Types\\WidgetColor;\n", $regenerated);

            $classes = scanClassesDetailed($widgetsPath);
            $getResponseClass = null;
            $createInputClass = null;
            $createResponseClass = null;
            foreach ($classes as $name => $info) {
                if ($name === 'Widgets') {
                    continue;
                }
                $fields = array_column($info['ctor']['params'] ?? [], 'type', 'name');
                if ($fields === ['id' => 'string', 'name' => 'string']) {
                    $getResponseClass = $name;
                } elseif ($fields === ['name' => 'string', 'note' => '?string']) {
                    $createInputClass = $name;
                } elseif ($fields === ['id' => 'string', 'name' => 'string', 'note' => '?string']) {
                    $createResponseClass = $name;
                }
            }
            $this->assertNotNull($getResponseClass, 'regenerated GET response class must have exactly {id: string, name: string}');
            $this->assertNotNull($createInputClass, 'regenerated POST input class must have exactly {name: string, note: ?string}');
            $this->assertNotNull($createResponseClass, 'regenerated POST response class must have exactly {id: string, name: string, note: ?string}');

            // 4) Idempotency: a second --apply against the same (now-current) spec must be a
            // byte-identical no-op -- no double insertion, no thrash.
            exec($cmd, $output2, $exitCode2);
            $this->assertSame(0, $exitCode2, 'second apply failed: '.implode("\n", $output2));
            $this->assertSame($regenerated, file_get_contents($widgetsPath), 'a second --apply must not modify the file again (idempotent, no double-insert)');
            $this->assertSame(1, substr_count($regenerated, 'function get('), 'exactly one get() method after two applies');
            $this->assertSame(1, substr_count($regenerated, 'function create('), 'exactly one create() method after two applies');

            // 5) Version was bumped exactly once, by the first apply (operation-insert bumps minor,
            // wired the same way applyOperationInsert() is called with --apply in runCli()).
            $this->assertStringContainsString("VERSION = '1.1.0'", file_get_contents("{$root}/src/BlindPay.php"));

            // 6) A synthetic multipart/form-data operation added to the same "Widgets" tag must be
            // rejected with a precise, non-generic needs-human reason, not silently skipped and not
            // auto-generated.
            $specWithMultipart = $this->goldenNewSpec();
            $specWithMultipart['paths']['/v1/instances/{instance_id}/widgets/upload'] = ['post' => [
                'x-sdk' => true,
                'tags' => ['Widgets'],
                'requestBody' => ['content' => ['multipart/form-data' => ['schema' => ['type' => 'object']]]],
                'responses' => ['200' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]]]]],
            ]];
            $multipartSpecPath = "{$root}/.api-sync/spec-with-multipart.json";
            file_put_contents($multipartSpecPath, json_encode($specWithMultipart));
            $checkCmd = sprintf('php %s --check --spec=%s 2>&1', escapeshellarg("{$root}/scripts/api-sync.php"), escapeshellarg($multipartSpecPath));
            exec($checkCmd, $checkOutput, $checkExitCode);
            $this->assertSame(1, $checkExitCode, '--check must fail (CI-red) while the multipart operation is unimplemented');
            $this->assertNotEmpty(array_filter($checkOutput, fn ($l) => str_contains($l, 'multipart/form-data request body not supported by generator')));
        } finally {
            $this->removeDirectory($root);
        }
    }

    #[Test]
    public function check_mode_fails_on_a_pending_unapplied_operation_insert(): void
    {
        $root = $this->buildGoldenRepo();

        try {
            $widgetsPath = "{$root}/src/Resources/Widgets/Widgets.php";
            $original = file_get_contents($widgetsPath);
            $mutated = preg_replace('/\n\nreadonly class GetWidgetResponse.*\nclass Widgets\n\{\n/s', "\n\nclass Widgets\n{\n", $original);
            $mutated = preg_replace('/\n\n    \/\*\*\n     \* @return BlindPayApiResponse<GetWidgetResponse>.*\n\}\n$/s', "\n}\n", $mutated);
            file_put_contents($widgetsPath, $mutated);

            $specPath = "{$root}/.api-sync/spec-current.json";
            file_put_contents($specPath, json_encode($this->goldenNewSpec()));

            $cmd = sprintf('php %s --check --spec=%s 2>&1', escapeshellarg("{$root}/scripts/api-sync.php"), escapeshellarg($specPath));
            exec($cmd, $output, $exitCode);

            $this->assertSame(1, $exitCode, '--check must be CI-red while an operation-insert change is pending and unapplied');
            $this->assertNotEmpty(array_filter($output, fn ($l) => str_contains($l, 'pending drift')));
            $this->assertNotEmpty(array_filter($output, fn ($l) => str_contains($l, 'new operation not yet implemented')));

            // Confirm --apply then clears it (same spec, same repo).
            $applyCmd = sprintf('php %s --apply --spec=%s 2>&1', escapeshellarg("{$root}/scripts/api-sync.php"), escapeshellarg($specPath));
            exec($applyCmd, $applyOutput, $applyExitCode);
            $this->assertSame(0, $applyExitCode, 'apply failed: '.implode("\n", $applyOutput));

            exec($cmd, $recheckOutput, $recheckExitCode);
            $this->assertSame(0, $recheckExitCode, '--check must be clean once the operation-insert has been applied: '.implode("\n", $recheckOutput));
        } finally {
            $this->removeDirectory($root);
        }
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
    public function it_treats_an_open_enum_union_as_compatible_with_a_spec_enum_or_string_and_carries_nullability(): void
    {
        $this->assertSame(['category' => 'string', 'nullable' => false, 'className' => null], phpTypeCategory('WidgetColor|string'));
        $this->assertSame(['category' => 'string', 'nullable' => true, 'className' => null], phpTypeCategory('WidgetColor|string|null'));
        $this->assertSame('ambiguous', phpTypeCategory('int|float')['category']);
        $this->assertTrue(categoriesCompatible(['category' => 'enum', 'nullable' => false], phpTypeCategory('WidgetColor|string')));
        $this->assertTrue(categoriesCompatible(['category' => 'string', 'nullable' => true], phpTypeCategory('WidgetColor|string|null')));
        $this->assertFalse(categoriesCompatible(['category' => 'integer', 'nullable' => false], phpTypeCategory('WidgetColor|string')));
        $this->assertSame('WidgetColor', openEnumSymbol('WidgetColor|string|null'));
        $this->assertSame('WidgetColor', openEnumSymbol('?WidgetColor'));
        $this->assertSame('string', openEnumSymbol('?string'));

        // end to end: the open-enum property passes reconciliation against an enum spec property
        $source = file_get_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");
        file_put_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php", str_replace('public WidgetColor $color', 'public WidgetColor|string $color', $source));
        [, $needsHuman] = reconcileTypes($this->widgetMap(), $this->baseSpec(), computeReachable($this->baseSpec()), scanAllClasses($this->fixtureRoot), []);
        $this->assertEmpty($needsHuman);
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

    // ---- regression: v3.4.1 spliced new arguments into a multi-line ternary ----

    /** A class shaped like PayinTrackingPayment before v3.4.1: a non-promoted last constructor
     * parameter, and multi-line last elements in both fromArray and toArray. */
    private function writeMultiLineTrackingFixture(string $namespace): string
    {
        $file = "{$this->fixtureRoot}/src/Types/TrackingFixture.php";
        file_put_contents($file, <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use DateTimeImmutable;

            abstract readonly class BaseTrackingFixture
            {
                public function __construct(
                    public string \$step,
                    public ?DateTimeImmutable \$completedAt
                ) {}
            }

            readonly class TrackingFixture extends BaseTrackingFixture
            {
                public function __construct(
                    string \$step,
                    public ?string \$providerName = null,
                    ?DateTimeImmutable \$completedAt = null
                ) {
                    parent::__construct(\$step, \$completedAt);
                }

                public static function fromArray(array \$data): self
                {
                    return new self(
                        step: \$data['step'],
                        providerName: \$data['provider_name'] ?? null,
                        completedAt: isset(\$data['completed_at'])
                            ? new DateTimeImmutable(\$data['completed_at'])
                            : null
                    );
                }

                public function toArray(): array
                {
                    return [
                        'step' => \$this->step,
                        'completed_at' => \$this->completedAt !== null
                            ? \$this->completedAt->format('c')
                            : null,
                    ];
                }
            }

            PHP);

        return $file;
    }

    #[Test]
    public function it_appends_a_field_after_a_multi_line_last_argument_instead_of_splitting_it(): void
    {
        $namespace = 'BlindPay\\SDK\\Tests\\ApiSyncRegression\\N'.bin2hex(random_bytes(6));
        $file = $this->writeMultiLineTrackingFixture($namespace);

        foreach (['review_contexts', 'review_sources'] as $field) {
            applyFieldInsertion($this->fixtureRoot, 'src/Types/TrackingFixture.php', 'TrackingFixture', $field, ['type' => ['array', 'null']]);
        }

        $source = file_get_contents($file);
        $this->assertStringContainsString(<<<'PHP'
                        completedAt: isset($data['completed_at'])
                            ? new DateTimeImmutable($data['completed_at'])
                            : null,
                        reviewContexts: $data['review_contexts'] ?? null,
                        reviewSources: $data['review_sources'] ?? null
                    );
            PHP, $source);
        $this->assertStringContainsString(<<<'PHP'
                    ?DateTimeImmutable $completedAt = null,
                    public ?array $reviewContexts = null,
                    public ?array $reviewSources = null
                ) {
            PHP, $source);
        $this->assertStringContainsString(<<<'PHP'
                            : null,
                        'review_contexts' => $this->reviewContexts,
                        'review_sources' => $this->reviewSources,
                    ];
            PHP, $source);
        $this->assertPhpFileParses($file);

        // `php -l` accepted the v3.4.1 output too (the bug was a TypeError at runtime), so also
        // execute the patched fromArray/toArray.
        require $file;
        $class = "{$namespace}\\TrackingFixture";
        $decoded = $class::fromArray([
            'step' => 'processing',
            'completed_at' => '2026-01-01T00:00:00Z',
            'review_contexts' => [['source' => 'x']],
            'review_sources' => ['manual'],
        ]);
        $this->assertInstanceOf(\DateTimeImmutable::class, $decoded->completedAt);
        $this->assertSame([['source' => 'x']], $decoded->reviewContexts);
        $this->assertSame(['manual'], $decoded->reviewSources);
        $this->assertSame(['manual'], $decoded->toArray()['review_sources']);
        $this->assertNull($class::fromArray(['step' => 'on_hold'])->completedAt);
    }

    #[Test]
    public function it_refuses_to_splice_a_field_when_the_closing_paren_shares_the_last_argument_line(): void
    {
        file_put_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php", <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace BlindPay\SDK\Resources\Widgets;

            readonly class WidgetInline
            {
                public function __construct(
                    public string $id
                ) {}

                public static function fromArray(array $data): self
                {
                    return new self(
                        id: $data['id']);
                }
            }

            PHP);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('closing delimiter shares a line');

        applyFieldInsertion($this->fixtureRoot, 'src/Resources/Widgets/Widgets.php', 'WidgetInline', 'label', ['type' => 'string']);
    }

    #[Test]
    public function it_extends_a_toarray_literal_wrapped_in_array_filter(): void
    {
        file_put_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php", <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace BlindPay\SDK\Resources\Widgets;

            readonly class WidgetUpdateInput
            {
                public function __construct(
                    public ?string $name = null
                ) {}

                public function toArray(): array
                {
                    return array_filter([
                        'name' => $this->name,
                    ], fn ($value) => $value !== null);
                }
            }

            PHP);

        applyFieldInsertion($this->fixtureRoot, 'src/Resources/Widgets/Widgets.php', 'WidgetUpdateInput', 'label', ['type' => 'string']);

        $source = file_get_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");
        $this->assertStringContainsString("            'name' => \$this->name,\n            'label' => \$this->label,\n        ], fn", $source);
        $this->assertPhpFileParses("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php");
    }

    #[Test]
    public function it_fails_loudly_instead_of_skipping_a_fromarray_it_cannot_anchor(): void
    {
        file_put_contents("{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php", <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace BlindPay\SDK\Resources\Widgets;

            readonly class WidgetFactory
            {
                public function __construct(
                    public string $id
                ) {}

                public static function fromArray(array $data): self
                {
                    $args = ['id' => $data['id']];

                    return new WidgetFactory(...$args);
                }
            }

            PHP);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no `new self(...)` call found');

        applyFieldInsertion($this->fixtureRoot, 'src/Resources/Widgets/Widgets.php', 'WidgetFactory', 'label', ['type' => 'string']);
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

    // ---- x-sdk filter: public-but-not-SDK operations are not this tool's business ----

    /** A spec whose only SDK operation is POST /v1/widgets plus one public-but-non-SDK operation
     * (no `x-sdk`) on a known tag, with a schema that only that operation references. */
    private function specWithNonSdkOperation(): array
    {
        $spec = $this->baseSpec();
        $spec['paths']['/v1/instances/{instance_id}/widgets/{id}/yield'] = ['get' => [
            'tags' => ['Widgets'],
            'responses' => ['200' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/YieldOut']]]]],
        ]];
        $spec['components']['schemas']['YieldOut'] = [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'string'], 'state' => ['$ref' => '#/components/schemas/YieldState']],
        ];
        $spec['components']['schemas']['YieldState'] = ['type' => 'string', 'enum' => ['on', 'off']];

        return $spec;
    }

    #[Test]
    public function an_operation_without_x_sdk_true_is_not_an_sdk_operation(): void
    {
        $spec = $this->specWithNonSdkOperation();
        $spec['paths']['/v1/instances/{instance_id}/widgets/{id}/string-flag'] = ['get' => ['x-sdk' => 'true', 'tags' => ['Widgets']]];
        $spec['paths']['/v1/instances/{instance_id}/widgets/{id}/false-flag'] = ['get' => ['x-sdk' => false, 'tags' => ['Widgets']]];

        $this->assertSame(['post /v1/widgets'], array_keys(pathMethodSet($spec)));
        $this->assertTrue(isSdkOperation(['x-sdk' => true]));
        $this->assertFalse(isSdkOperation(['tags' => ['Widgets']]));
    }

    #[Test]
    public function reconcile_operations_ignores_a_new_non_sdk_operation_even_on_a_mapped_tag(): void
    {
        [$applicable, $needsHuman] = reconcileOperations($this->baseSpec(), $this->specWithNonSdkOperation());

        $this->assertSame([], $applicable);
        $this->assertSame([], $needsHuman);
    }

    #[Test]
    public function schemas_only_non_sdk_operations_reference_are_unreachable_and_never_need_a_mapping_decision(): void
    {
        $oldSpec = $this->baseSpec();
        $newSpec = $this->specWithNonSdkOperation();

        $reachable = computeReachable($newSpec);
        $issues = computeStructuralDiff($oldSpec, $newSpec, computeReachable($oldSpec), $reachable, $this->widgetMap());

        $this->assertArrayNotHasKey('YieldOut', $reachable);
        $this->assertArrayNotHasKey('YieldState', $reachable, 'a schema reachable only through a non-SDK schema is unreachable too');
        $this->assertSame([], array_filter($issues, fn ($i) => str_contains($i, 'Yield')));
        $this->assertSame([], computeCoverageReport($this->widgetMap(), $newSpec, $reachable));
    }

    #[Test]
    public function a_schema_shared_with_an_sdk_operation_stays_reachable(): void
    {
        $spec = $this->specWithNonSdkOperation();
        $spec['paths']['/v1/widgets']['post']['responses']['200']['content']['application/json']['schema'] = ['$ref' => '#/components/schemas/YieldOut'];

        $this->assertArrayHasKey('YieldOut', computeReachable($spec));
        $this->assertArrayHasKey('YieldState', computeReachable($spec));
    }

    #[Test]
    public function check_mode_exits_zero_for_a_spec_whose_only_new_surface_is_non_sdk(): void
    {
        $root = $this->buildGoldenRepo();

        try {
            $specPath = "{$root}/.api-sync/spec-current.json";
            file_put_contents($specPath, json_encode($this->specWithNonSdkOperation()));

            exec(sprintf('php %s --check --spec=%s 2>&1', escapeshellarg("{$root}/scripts/api-sync.php"), escapeshellarg($specPath)), $output, $exitCode);

            $this->assertSame(0, $exitCode, implode("\n", $output));
            $this->assertSame([], array_filter($output, fn ($l) => str_contains($l, 'Yield')));
        } finally {
            $this->removeDirectory($root);
        }
    }

    #[Test]
    public function an_operation_that_loses_x_sdk_is_reported_as_removed(): void
    {
        $oldSpec = $this->baseSpec();
        $newSpec = $this->baseSpec();
        unset($newSpec['paths']['/v1/widgets']['post']['x-sdk']);

        $issues = computeStructuralDiff($oldSpec, $newSpec, computeReachable($oldSpec), computeReachable($newSpec), $this->widgetMap());

        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'operation removed: post /v1/widgets')));
    }

    // ---- coverage and reconciliation key on METHOD + path, never the path alone ----

    #[Test]
    public function a_new_method_on_an_already_known_path_is_a_new_operation(): void
    {
        $oldSpec = $this->baseSpec(); // POST /v1/widgets only
        $newSpec = $this->baseSpec();
        $newSpec['paths']['/v1/widgets']['get'] = [
            'x-sdk' => true,
            'tags' => ['Gadgets'],
            'responses' => ['200' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]]]]]],
        ];

        [$applicable, $needsHuman] = reconcileOperations($oldSpec, $newSpec);

        $this->assertSame([], $applicable);
        $this->assertCount(1, $needsHuman);
        $this->assertStringContainsString('get /v1/widgets', $needsHuman[0]);
        $this->assertStringNotContainsString('post /v1/widgets', $needsHuman[0]);
    }

    #[Test]
    public function removing_one_method_of_a_path_reports_only_that_method(): void
    {
        $oldSpec = $this->baseSpec();
        $oldSpec['paths']['/v1/widgets']['get'] = ['x-sdk' => true, 'tags' => ['Widgets']];
        $newSpec = $this->baseSpec();

        $issues = computeStructuralDiff($oldSpec, $newSpec, computeReachable($oldSpec), computeReachable($newSpec), $this->widgetMap());

        $this->assertNotEmpty(array_filter($issues, fn ($i) => str_contains($i, 'operation removed: get /v1/widgets')));
        $this->assertSame([], array_filter($issues, fn ($i) => str_contains($i, 'post /v1/widgets')));
    }

    #[Test]
    public function a_non_sdk_method_does_not_hide_or_hold_up_an_sdk_method_on_the_same_path(): void
    {
        $spec = $this->baseSpec();
        $spec['paths']['/v1/widgets']['get'] = ['tags' => ['Widgets']]; // public, not SDK
        $sdkPaths = sdkPaths($spec);

        $this->assertSame(['post'], array_keys($sdkPaths['/v1/widgets']));
        $this->assertSame(['post /v1/widgets'], array_keys(pathMethodSet($spec)));
    }

    // ---- nullable enums: `"enum": ["a", "b", null]` ----

    /** Runs $body with every PHP deprecation/warning turned into an exception, so a "Using null as
     * an array offset" style regression fails the test instead of printing to stderr. */
    private function withStrictErrors(callable $body): mixed
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });

        try {
            return $body();
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function a_null_member_of_a_spec_enum_is_not_an_enum_value(): void
    {
        $spec = $this->baseSpec(['components' => ['schemas' => ['WidgetOut' => ['properties' => ['color' => ['enum' => ['red', null, 'blue']]]]]]]);

        $this->assertSame(['red', 'blue'], specEnumValues($spec, 'WidgetOut', 'color'));
        $this->assertSame(['red', 'blue'], enumSpecValues($spec, ['spec' => ['schema' => 'WidgetOut', 'property' => 'color']]));
    }

    #[Test]
    public function reconcile_enums_survives_a_nullable_enum_without_a_false_positive_or_a_deprecation(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $newSpec = $this->baseSpec(['components' => ['schemas' => ['WidgetOut' => ['properties' => ['color' => ['enum' => ['red', 'blue', null]]]]]]]);

        [$applicable, $needsHuman] = $this->withStrictErrors(fn () => reconcileEnums($this->widgetMap(), $newSpec, computeReachable($newSpec), $classIndex, []));

        $this->assertSame([], $applicable);
        $this->assertSame([], $needsHuman);
    }

    #[Test]
    public function a_nullable_enum_with_a_genuinely_new_value_schedules_only_that_value(): void
    {
        $classIndex = scanAllClasses($this->fixtureRoot);
        $newSpec = $this->baseSpec(['components' => ['schemas' => ['WidgetOut' => ['properties' => ['color' => ['enum' => ['red', 'blue', 'green', null]]]]]]]);

        [$applicable] = $this->withStrictErrors(fn () => reconcileEnums($this->widgetMap(), $newSpec, computeReachable($newSpec), $classIndex, []));

        $this->assertSame(['green'], array_column($applicable, 'value'));
    }

    #[Test]
    public function adding_or_dropping_a_null_enum_member_is_not_a_removed_value(): void
    {
        $withNull = $this->baseSpec(['components' => ['schemas' => ['WidgetOut' => ['properties' => ['color' => ['enum' => ['red', 'blue', null]]]]]]]);
        $withoutNull = $this->baseSpec();

        foreach ([[$withNull, $withoutNull], [$withoutNull, $withNull]] as [$old, $new]) {
            $issues = $this->withStrictErrors(fn () => computeStructuralDiff($old, $new, computeReachable($old), computeReachable($new), $this->widgetMap()));
            $this->assertSame([], array_filter($issues, fn ($i) => str_contains($i, 'enum value(s) removed')));
        }
    }

    #[Test]
    public function a_null_enum_member_makes_the_spec_type_nullable(): void
    {
        $this->assertTrue(specTypeCategory(['type' => 'string', 'enum' => ['a', null]])['nullable']);
        $this->assertSame('enum', specTypeCategory(['type' => 'string', 'enum' => ['a', null]])['category']);
        $this->assertFalse(specTypeCategory(['type' => 'string', 'enum' => ['a']])['nullable']);
    }

    #[Test]
    public function check_mode_runs_clean_against_a_nullable_enum_spec(): void
    {
        $root = $this->buildGoldenRepo();

        try {
            // The baseline already carries the nullable enum, so nullability is not newly added.
            $nullableSpec = $this->baseSpec(['components' => ['schemas' => ['WidgetOut' => ['properties' => ['color' => ['enum' => ['red', 'blue', null]]]]]]]);
            file_put_contents("{$root}/.api-sync/spec-snapshot.json", json_encode($nullableSpec));
            $specPath = "{$root}/.api-sync/spec-current.json";
            file_put_contents($specPath, json_encode($nullableSpec));

            exec(sprintf('php -d error_reporting=-1 -d display_errors=1 %s --check --spec=%s 2>&1', escapeshellarg("{$root}/scripts/api-sync.php"), escapeshellarg($specPath)), $output, $exitCode);

            $this->assertSame(0, $exitCode, implode("\n", $output));
            $this->assertSame([], array_filter($output, fn ($l) => str_contains($l, 'Deprecated') || str_contains($l, 'Warning')));
        } finally {
            $this->removeDirectory($root);
        }
    }

    // ---- resourceForTag ----

    #[Test]
    public function resource_for_tag_maps_the_tags_that_have_a_resource_in_src(): void
    {
        $expected = [
            'Payables' => ['src/Resources/Payables/Payables.php', 'Payables'],
            'Wallets' => ['src/Resources/CustodialWallets/CustodialWallets.php', 'CustodialWallets'],
            'Transfer Quotes' => ['src/Resources/Transfers/Transfers.php', 'Transfers'],
            'Solana' => ['src/Resources/Wallets/BlockchainWallets.php', 'BlockchainWallets'],
            'Stellar' => ['src/Resources/Wallets/BlockchainWallets.php', 'BlockchainWallets'],
        ];
        foreach ($expected as $tag => [$file, $class]) {
            $this->assertSame(['file' => $file, 'class' => $class], resourceForTag($tag), $tag);
            // the table must point at real files and classes, not just plausible ones
            $realFile = dirname(__DIR__, 2).'/'.$file;
            $this->assertFileExists($realFile, $tag);
            $this->assertMatchesRegularExpression('/^class '.$class.'$/m', file_get_contents($realFile), $tag);
        }
        $this->assertNull(resourceForTag('No Such Tag'));
    }

    // ---- generated code follows the SDK conventions (request options, Path::of, Query::build,
    // ErrorResponse::validation, $response->map, open response enums) ----

    /** Appends an empty `Widgets` resource class (and its own imports) to the fixture's Widgets.php
     * so operation-insert has a class to splice into, then reconciles and applies every STANDARD
     * operation $newSpec adds over the baseline. Returns the resulting file's source. */
    private function applyOperationsToWidgetsResource(array $newSpec, string $imports = "use BlindPay\\SDK\\Internal\\ApiClientInterface;\n"): string
    {
        $path = "{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php";
        $source = file_get_contents($path);
        $source = str_replace("use BlindPay\\SDK\\Types\\WidgetColor;\n", $imports.'use BlindPay\\SDK\\Types\\WidgetColor;'."\n", $source);
        $source = rtrim($source)."\n\nclass Widgets\n{\n    public function __construct(\n        private readonly string \$instanceId,\n        private readonly ApiClientInterface \$client\n    ) {}\n}\n";
        file_put_contents($path, $source);

        [$applicable, $needsHuman] = reconcileOperations($this->baseSpec(), $newSpec, buildEnumIndex($this->widgetMap()));
        $this->assertSame([], $needsHuman);
        $this->assertNotEmpty($applicable);
        foreach ($applicable as $change) {
            applyOperationInsert($this->fixtureRoot, $change, $newSpec);
        }
        $this->assertPhpFileParses($path);

        return file_get_contents($path);
    }

    private function widgetsOperation(string $verb, string $urlPath, array $op): array
    {
        return $this->baseSpec(['paths' => [$urlPath => [$verb => array_merge(['x-sdk' => true, 'tags' => ['Widgets']], $op)]]]);
    }

    private function jsonContent(array $schema): array
    {
        return ['content' => ['application/json' => ['schema' => $schema]]];
    }

    #[Test]
    public function a_generated_delete_forwards_null_body_and_options_and_validates_the_id(): void
    {
        $source = $this->applyOperationsToWidgetsResource($this->widgetsOperation('delete', '/v1/instances/{instance_id}/widgets/{id}', [
            'responses' => ['204' => ['description' => 'gone']],
        ]));

        $this->assertStringContainsString('public function delete(string $id, ?RequestOptions $options = null): BlindPayApiResponse', $source);
        $this->assertStringContainsString("if (\$id === '') {\n", $source);
        $this->assertStringContainsString("ErrorResponse::validation('ID cannot be empty')", $source);
        $this->assertStringContainsString("return \$this->client->delete(Path::of('instances/%s/widgets/%s', \$this->instanceId, \$id), null, \$options);", $source);
        $this->assertStringNotContainsString('->map(', $source, 'no response body, so nothing to decode');
    }

    #[Test]
    public function a_generated_put_without_a_body_sends_an_empty_array_and_options(): void
    {
        $source = $this->applyOperationsToWidgetsResource($this->widgetsOperation('put', '/v1/instances/{instance_id}/customers/{customer_id}/widgets/{id}/archive', [
            'responses' => ['200' => $this->jsonContent(['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok']])],
        ]));

        $this->assertStringContainsString('public function update(string $customerId, string $id, ?RequestOptions $options = null): BlindPayApiResponse', $source);
        $this->assertStringContainsString("ErrorResponse::validation('Customer ID cannot be empty')", $source);
        $this->assertStringContainsString("\$this->client->put(Path::of('instances/%s/customers/%s/widgets/%s/archive', \$this->instanceId, \$customerId, \$id), [], \$options);", $source);
        $this->assertStringContainsString('return $response->map(fn (array $data) => UpdateResponse::fromArray($data));', $source);
    }

    #[Test]
    public function a_generated_get_with_query_parameters_uses_query_build(): void
    {
        $source = $this->applyOperationsToWidgetsResource($this->widgetsOperation('get', '/v1/instances/{instance_id}/widgets/{id}/history', [
            'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                ['name' => 'status_filter', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                ['name' => 'min_amount', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer']],
                ['name' => 'Idempotency-Key', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string']],
            ],
            'responses' => ['200' => $this->jsonContent(['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']])],
        ]));

        $this->assertStringContainsString('public function list(string $id, ?ListInput $input = null, ?RequestOptions $options = null): BlindPayApiResponse', $source);
        $this->assertStringContainsString("Path::of('instances/%s/widgets/%s/history', \$this->instanceId, \$id).Query::build(\$input?->toArray() ?? [])", $source);
        $this->assertStringContainsString("public ?string \$statusFilter = null,\n        public ?int \$minAmount = null", $source);
        $this->assertStringContainsString("\$data['status_filter'] = \$this->statusFilter;", $source);
        $this->assertStringNotContainsString('Idempotency', $source, 'header parameters are not query parameters');
        $this->assertStringContainsString("use BlindPay\\SDK\\Internal\\Query;\n", $source);
    }

    #[Test]
    public function a_generated_get_with_a_required_query_parameter_takes_a_required_input(): void
    {
        $source = $this->applyOperationsToWidgetsResource($this->widgetsOperation('get', '/v1/instances/{instance_id}/widgets/lookup', [
            'parameters' => [['name' => 'code', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']]],
            'responses' => ['200' => $this->jsonContent(['type' => 'object', 'properties' => ['id' => ['type' => 'string']]])],
        ]));

        $this->assertStringContainsString('public function list(LookupInput $input, ?RequestOptions $options = null)', str_replace('ListInput', 'LookupInput', $source));
        $this->assertStringContainsString("Path::of('instances/%s/widgets/lookup', \$this->instanceId).Query::build(\$input->toArray())", $source);
    }

    #[Test]
    public function an_enum_constrained_query_parameter_still_needs_a_human(): void
    {
        [$applicable, $needsHuman] = reconcileOperations($this->baseSpec(), $this->widgetsOperation('get', '/v1/instances/{instance_id}/widgets/by-color', [
            'parameters' => [['name' => 'color', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['red', 'blue']]]],
            'responses' => ['200' => $this->jsonContent(['type' => 'object', 'properties' => ['id' => ['type' => 'string']]])],
        ]));

        $this->assertSame([], $applicable);
        $this->assertStringContainsString('enum-constrained query parameter', $needsHuman[0]);
    }

    #[Test]
    public function a_mapped_response_enum_is_decoded_open_with_decode_enum(): void
    {
        $widgetOut = ['$ref' => '#/components/schemas/WidgetOut'];
        $spec = $this->widgetsOperation('get', '/v1/instances/{instance_id}/widgets/{id}/state', ['responses' => ['200' => $this->jsonContent($widgetOut)]]);
        $spec['components']['schemas']['WidgetOut']['required'] = ['id', 'color'];

        $source = $this->applyOperationsToWidgetsResource($spec);

        $this->assertStringContainsString("public string \$id,\n        public WidgetColor|string \$color,\n        public ?string \$name = null\n", $source);
        $this->assertStringContainsString("color: Decode::enum(WidgetColor::class, \$data['color']),", $source);
        $this->assertStringContainsString("name: \$data['name'] ?? null\n", $source);
        $this->assertStringContainsString("use BlindPay\\SDK\\Internal\\Decode;\n", $source);
    }

    #[Test]
    public function an_optional_or_nullable_mapped_response_enum_is_typed_enum_string_null(): void
    {
        $spec = $this->widgetsOperation('get', '/v1/instances/{instance_id}/widgets/{id}/state', ['responses' => ['200' => $this->jsonContent(['$ref' => '#/components/schemas/WidgetOut'])]]);
        // listed in `required` but nullable: still optional (C8)
        $spec['components']['schemas']['WidgetOut']['required'] = ['id', 'color'];
        $spec['components']['schemas']['WidgetOut']['properties']['color'] = ['type' => ['string', 'null'], 'enum' => ['red', 'blue', null]];

        $source = $this->applyOperationsToWidgetsResource($spec);

        $this->assertStringContainsString('public WidgetColor|string|null $color = null', $source);
        $this->assertStringContainsString("color: Decode::enum(WidgetColor::class, \$data['color'] ?? null)", $source);
    }

    #[Test]
    public function a_mapped_request_enum_stays_closed(): void
    {
        $widgetOut = ['$ref' => '#/components/schemas/WidgetOut'];
        $spec = $this->widgetsOperation('post', '/v1/instances/{instance_id}/widgets/paint', [
            'requestBody' => $this->jsonContent($widgetOut),
            'responses' => ['204' => ['description' => 'ok']],
        ]);
        $spec['components']['schemas']['WidgetOut']['required'] = ['name', 'color'];

        $source = $this->applyOperationsToWidgetsResource($spec);

        $this->assertStringContainsString("public string \$name,\n        public WidgetColor \$color,\n        public ?string \$id = null\n", $source);
        $this->assertStringContainsString("'color' => \$this->color->value,", $source);
        $this->assertStringNotContainsString('Decode', $source);
        $this->assertStringContainsString("\$this->client->post(\n            Path::of('instances/%s/widgets/paint', \$this->instanceId),\n            \$input->toArray(),\n            \$options\n        );", $source);
    }

    #[Test]
    public function an_unmapped_enum_property_in_a_new_operation_still_needs_a_human(): void
    {
        [$applicable, $needsHuman] = reconcileOperations($this->baseSpec(), $this->widgetsOperation('post', '/v1/instances/{instance_id}/widgets/paint', [
            'requestBody' => $this->jsonContent(['type' => 'object', 'properties' => ['shade' => ['type' => 'string', 'enum' => ['light', 'dark']]]]),
            'responses' => ['204' => ['description' => 'ok']],
        ]), buildEnumIndex($this->widgetMap()));

        $this->assertSame([], $applicable);
        $this->assertStringContainsString('enum-constrained property in request body', $needsHuman[0]);
    }

    #[Test]
    public function an_operation_the_sdk_already_implements_is_not_regenerated(): void
    {
        $path = "{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php";
        file_put_contents($path, file_get_contents($path)."\nclass Existing\n{\n    public function get(string \$id)\n    {\n        return \$this->client->get(Path::of('instances/%s/widgets/%s', \$this->instanceId, \$id));\n    }\n\n    public function presign()\n    {\n        return \$this->client->post('presign'.Query::build([]), []);\n    }\n}\n");
        $spec = $this->widgetsOperation('get', '/v1/instances/{instance_id}/widgets/{id}', ['responses' => ['200' => $this->jsonContent(['type' => 'object', 'properties' => ['id' => ['type' => 'string']]])]]);
        $isImplemented = fn (string $method, string $urlPath) => operationAlreadyImplemented($this->fixtureRoot, $method, $urlPath);

        [$applicable, $needsHuman] = reconcileOperations($this->baseSpec(), $spec, [], $isImplemented);

        $this->assertSame([], $applicable);
        $this->assertSame([], $needsHuman);
        $this->assertTrue(operationAlreadyImplemented($this->fixtureRoot, 'post', '/v1/presign'));
        $this->assertFalse(operationAlreadyImplemented($this->fixtureRoot, 'delete', '/v1/instances/{instance_id}/widgets/{id}'), 'same path, other verb is a different operation');
        $this->assertFalse(operationAlreadyImplemented($this->fixtureRoot, 'get', '/v1/instances/{instance_id}/gadgets/{id}'));
    }

    // ---- use statements ----

    #[Test]
    public function ensure_use_statements_adds_only_what_is_missing_in_alphabetical_place(): void
    {
        $path = "{$this->fixtureRoot}/src/Resources/Widgets/Widgets.php";
        $before = file_get_contents($path); // imports only BlindPay\SDK\Types\WidgetColor

        ensureUseStatements($path, ['BlindPay\SDK\Types\WidgetColor', 'BlindPay\SDK\Resources\Widgets\Sibling', 'BlindPay\SDK\Internal\Path', 'BlindPay\SDK\Types\RequestOptions', 'BlindPay\SDK\Internal\Path']);

        $after = file_get_contents($path);
        $this->assertStringContainsString("use BlindPay\\SDK\\Internal\\Path;\nuse BlindPay\\SDK\\Types\\RequestOptions;\nuse BlindPay\\SDK\\Types\\WidgetColor;\n", $after);
        $this->assertStringNotContainsString('Sibling', $after, 'a class in the file\'s own namespace needs no import');
        ensureUseStatements($path, ['BlindPay\SDK\Internal\Path']);
        $this->assertSame($after, file_get_contents($path), 'idempotent');
        $this->assertNotSame($before, $after);
        $this->assertPhpFileParses($path);
    }

    #[Test]
    public function ensure_use_statements_creates_the_block_in_a_file_that_imports_nothing(): void
    {
        $path = "{$this->fixtureRoot}/src/Types/WidgetColor.php";

        ensureUseStatements($path, ['BlindPay\SDK\Internal\Decode']);

        $this->assertStringContainsString("namespace BlindPay\\SDK\\Types;\n\nuse BlindPay\\SDK\\Internal\\Decode;\n\nenum WidgetColor", file_get_contents($path));
        $this->assertPhpFileParses($path);
    }

    // ---- field insertion of enum-constrained fields ----

    private function enumFieldChange(): array
    {
        return ['class' => 'EnumHolder', 'file' => 'src/Resources/Widgets/EnumHolder.php', 'enum' => ['class' => 'WidgetColor', 'file' => 'src/Types/WidgetColor.php']];
    }

    private function writeEnumHolder(string $body): string
    {
        $path = "{$this->fixtureRoot}/src/Resources/Widgets/EnumHolder.php";
        file_put_contents($path, "<?php\n\ndeclare(strict_types=1);\n\nnamespace BlindPay\\SDK\\Resources\\Widgets;\n\n{$body}\n");

        return $path;
    }

    #[Test]
    public function a_new_enum_field_on_a_response_class_is_decoded_open(): void
    {
        $path = $this->writeEnumHolder("readonly class EnumHolder\n{\n    public function __construct(\n        public string \$id\n    ) {}\n\n    public static function fromArray(array \$data): self\n    {\n        return new self(\n            id: \$data['id']\n        );\n    }\n}");
        $c = $this->enumFieldChange();

        applyFieldInsertion($this->fixtureRoot, $c['file'], 'EnumHolder', 'color', ['type' => 'string', 'enum' => ['red', 'blue']], $c['enum']);

        $source = file_get_contents($path);
        $this->assertStringContainsString('public WidgetColor|string|null $color = null', $source);
        $this->assertStringContainsString("color: Decode::enum(WidgetColor::class, \$data['color'] ?? null)", $source);
        $this->assertStringContainsString("use BlindPay\\SDK\\Internal\\Decode;\nuse BlindPay\\SDK\\Types\\WidgetColor;\n", $source);
        $this->assertPhpFileParses($path);
    }

    #[Test]
    public function a_new_enum_field_on_an_input_class_stays_closed(): void
    {
        $path = $this->writeEnumHolder("readonly class EnumHolder\n{\n    public function __construct(\n        public string \$name,\n        public ?string \$extra = null\n    ) {}\n\n    public function toArray(): array\n    {\n        \$data = [\n            'name' => \$this->name,\n        ];\n\n        if (\$this->extra !== null) {\n            \$data['extra'] = \$this->extra;\n        }\n\n        return \$data;\n    }\n}");
        $c = $this->enumFieldChange();

        applyFieldInsertion($this->fixtureRoot, $c['file'], 'EnumHolder', 'color', ['type' => 'string', 'enum' => ['red', 'blue']], $c['enum']);

        $source = file_get_contents($path);
        $this->assertStringContainsString('public ?WidgetColor $color = null', $source);
        $this->assertStringContainsString("\$data['color'] = \$this->color->value;", $source);
        $this->assertStringNotContainsString('Decode', $source);
        $this->assertStringContainsString("use BlindPay\\SDK\\Types\\WidgetColor;\n", $source);
        $this->assertPhpFileParses($path);
    }

    #[Test]
    public function a_new_enum_field_on_a_class_that_both_decodes_and_serialises_round_trips_through_decode_wire(): void
    {
        $path = $this->writeEnumHolder("readonly class EnumHolder\n{\n    public function __construct(\n        public string \$id\n    ) {}\n\n    public static function fromArray(array \$data): self\n    {\n        return new self(\n            id: \$data['id']\n        );\n    }\n\n    public function toArray(): array\n    {\n        return [\n            'id' => \$this->id,\n        ];\n    }\n}");
        $c = $this->enumFieldChange();

        applyFieldInsertion($this->fixtureRoot, $c['file'], 'EnumHolder', 'color', ['type' => 'string', 'enum' => ['red', 'blue']], $c['enum']);

        $source = file_get_contents($path);
        $this->assertStringContainsString("'color' => Decode::wire(\$this->color)", $source);
        $this->assertStringContainsString("color: Decode::enum(WidgetColor::class, \$data['color'] ?? null)", $source);
        $this->assertPhpFileParses($path);
    }

    #[Test]
    public function reconcile_types_binds_a_new_enum_field_to_its_mapped_enum_class(): void
    {
        $map = $this->widgetMap();
        $map['enums'][] = ['spec' => ['schema' => 'WidgetOut', 'property' => 'shade'], 'sdk' => ['file' => 'src/Types/WidgetColor.php', 'class' => 'WidgetColor']];
        $newSpec = $this->baseSpec(['components' => ['schemas' => ['WidgetOut' => ['properties' => ['shade' => ['type' => 'string', 'enum' => ['red']], 'plain' => ['type' => 'string']]]]]]);

        [$applicable] = reconcileTypes($map, $newSpec, computeReachable($newSpec), scanAllClasses($this->fixtureRoot), []);

        $byField = array_column($applicable, 'enum', 'field');
        $this->assertSame(['class' => 'WidgetColor', 'file' => 'src/Types/WidgetColor.php'], $byField['shade']);
        $this->assertNull($byField['plain']);
    }

    #[Test]
    public function a_class_constant_access_is_not_mistaken_for_a_class_declaration(): void
    {
        $path = $this->writeEnumHolder("readonly class EnumHolder\n{\n    public function __construct(\n        public WidgetColor|string|null \$color = null\n    ) {}\n\n    public static function fromArray(array \$data): self\n    {\n        return new self(\n            color: Decode::enum(WidgetColor::class, \$data['color'] ?? null)\n        );\n    }\n\n    public function toArray(): array\n    {\n        return [\n            'color' => Decode::wire(\$this->color),\n        ];\n    }\n}\n\nclass Resource\n{\n    public function get(): string\n    {\n        return WidgetColor::class;\n    }\n}");

        $classes = scanClassesDetailed($path);

        $this->assertSame(['EnumHolder', 'Resource'], array_keys($classes));
        $this->assertSame('literal', $classes['EnumHolder']['toArray']['style']);
        $this->assertArrayHasKey('literal', $classes['EnumHolder']['toArray']);
        $boundaries = findClassBoundaries($path, 'Resource');
        $this->assertNotNull($boundaries['declLine']);
        $this->assertNotNull($boundaries['endLine']);
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $this->assertSame('class Resource', $lines[$boundaries['declLine'] - 1]);
        $this->assertSame('}', $lines[$boundaries['endLine'] - 1]);
        $this->assertSame(count($lines), $boundaries['endLine'], 'the class ends at the file\'s last closing brace');
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
