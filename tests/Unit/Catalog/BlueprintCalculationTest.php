<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use App\Modules\Catalog\Services\BlueprintCalculationService;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\Catalog\Models\BlueprintOption;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Unit tests for BlueprintCalculationService.
 *
 * These tests exercise the pure calculation and condition-evaluation logic
 * without touching the database. All test data is synthesised using plain
 * stdClass objects that match the shape the service reads via property access.
 */
class BlueprintCalculationTest extends TestCase
{
    use RefreshDatabase;

    private BlueprintCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BlueprintCalculationService();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Private helpers — build minimal value-objects the service reads
    // ────────────────────────────────────────────────────────────────────────

    /** Build a synthetic BlueprintOptionValue-like object keyed by option_id. */
    private function makeSelectedByOptionId(string $optionId, float $numeric, ?string $code = null): Collection
    {
        $val = new class($optionId, $numeric, $code) {
            public string $id;
            public string $option_id;
            public ?float $numeric_value;
            public ?string $code;

            public function __construct(string $opId, float $n, ?string $c)
            {
                $this->id           = 'val-' . $opId;
                $this->option_id    = $opId;
                $this->numeric_value = $n;
                $this->code         = $c;
            }
        };

        return collect([$optionId => $val]);
    }

    /** Build a minimal consumable-like object the service reads. */
    private function makeConsumable(
        string  $calcType,
        ?string $sourceOptionId,
        string  $calcMethod,
        ?float  $calcParam,
        float   $fixedQty,
        ?array  $conditions = null
    ): object {
        return new class(
            $calcType,
            $sourceOptionId,
            $calcMethod,
            $calcParam,
            $fixedQty,
            $conditions
        ) {
            public string  $calculation_type;
            public ?string $calculation_source_option_id;
            public string  $calculation_method;
            public ?float  $calculation_parameter;
            public float   $fixed_quantity;
            public ?array  $applicability_conditions;
            public string  $consumable_item_id = 'item-dummy';

            public function __construct(
                string $t,
                ?string $so,
                string $m,
                ?float $p,
                float $fq,
                ?array $cond
            ) {
                $this->calculation_type             = $t;
                $this->calculation_source_option_id = $so;
                $this->calculation_method           = $m;
                $this->calculation_parameter        = $p;
                $this->fixed_quantity               = $fq;
                $this->applicability_conditions     = $cond;
            }
        };
    }

    // ────────────────────────────────────────────────────────────────────────
    // evaluateConditions / isApplicable
    // ────────────────────────────────────────────────────────────────────────

    /** @test */
    public function conditions_null_always_applicable(): void
    {
        $this->assertTrue(
            $this->service->evaluateConditions(null, collect(), [])
        );
    }

    /** @test */
    public function conditions_empty_array_always_applicable(): void
    {
        $this->assertTrue(
            $this->service->evaluateConditions([], collect(), [])
        );
    }

    /** @test */
    public function evaluates_equals_rule_by_numeric_value(): void
    {
        $optionId = 'opt-cap';
        $selected = $this->makeSelectedByOptionId($optionId, 6.0, '6kg');

        // Rule: capacity == 6  (numeric)
        $conditions = [
            'match_type' => 'all',
            'rules'      => [
                ['option_id' => $optionId, 'operator' => 'equals', 'value' => 6],
            ],
        ];

        $blueprintOptionIds = [$optionId];

        $this->assertTrue($this->service->evaluateConditions($conditions, $selected, $blueprintOptionIds));
    }

    /** @test */
    public function evaluates_greater_than_rule(): void
    {
        $optionId = 'opt-cap';
        $selected = $this->makeSelectedByOptionId($optionId, 9.0);

        $conditions = [
            'match_type' => 'all',
            'rules'      => [
                ['option_id' => $optionId, 'operator' => 'greater_than', 'value' => 6],
            ],
        ];

        $blueprintOptionIds = [$optionId];

        $this->assertTrue($this->service->evaluateConditions($conditions, $selected, $blueprintOptionIds));

        // Now select 3 kg — should fail
        $selected3 = $this->makeSelectedByOptionId($optionId, 3.0);
        $this->assertFalse($this->service->evaluateConditions($conditions, $selected3, $blueprintOptionIds));
    }

    /** @test */
    public function evaluates_in_rule_with_array_of_numeric_values(): void
    {
        $optionId = 'opt-cap';
        $selected = $this->makeSelectedByOptionId($optionId, 6.0, '6kg');

        $conditions = [
            'match_type' => 'all',
            'rules'      => [
                ['option_id' => $optionId, 'operator' => 'in', 'value' => [6, 9]],
            ],
        ];

        $blueprintOptionIds = [$optionId];

        $this->assertTrue($this->service->evaluateConditions($conditions, $selected, $blueprintOptionIds));

        $selected12 = $this->makeSelectedByOptionId($optionId, 12.0);
        $this->assertFalse($this->service->evaluateConditions($conditions, $selected12, $blueprintOptionIds));
    }

    /** @test */
    public function any_match_type_returns_true_when_at_least_one_rule_passes(): void
    {
        $optionId = 'opt-cap';
        $selected = $this->makeSelectedByOptionId($optionId, 6.0);

        $conditions = [
            'match_type' => 'any',
            'rules'      => [
                ['option_id' => $optionId, 'operator' => 'equals', 'value' => 999],   // fails
                ['option_id' => $optionId, 'operator' => 'equals', 'value' => 6.0],   // passes
            ],
        ];

        $blueprintOptionIds = [$optionId];

        $this->assertTrue($this->service->evaluateConditions($conditions, $selected, $blueprintOptionIds));
    }

    /** @test */
    public function evaluates_legacy_match_and_conditions_format(): void
    {
        $optionId = 'opt-agent';
        $selected = $this->makeSelectedByOptionId($optionId, 0.0, 'ABC_POWDER');
        $conditions = [
            'match' => 'all',
            'conditions' => [
                ['option_id' => $optionId, 'operator' => 'equals', 'value' => 'ABC_POWDER'],
            ],
        ];

        $this->assertTrue($this->service->evaluateConditions($conditions, $selected, [$optionId]));
    }

    // ────────────────────────────────────────────────────────────────────────
    // calculateConsumableQuantity
    // ────────────────────────────────────────────────────────────────────────

    /** @test */
    public function fixed_type_returns_fixed_quantity_directly(): void
    {
        $consumable = $this->makeConsumable('fixed', null, 'fixed', null, 3.5);
        $result = $this->service->calculateConsumableQuantity($consumable, collect(), []);

        $this->assertEquals(3.5, $result);
    }

    /** @test */
    public function fixed_type_returns_positive_fixed_quantity(): void
    {
        $consumable = $this->makeConsumable('fixed', null, 'fixed', null, 5.0);
        $result = $this->service->calculateConsumableQuantity($consumable, collect(), []);

        $this->assertEquals(5.0, $result);
    }

    /** @test */
    public function blueprint_option_multiply_returns_numeric_times_multiplier(): void
    {
        $optionId = 'opt-cap';
        $selected = $this->makeSelectedByOptionId($optionId, 6.0);

        $consumable = $this->makeConsumable(
            calcType: 'blueprint_option',
            sourceOptionId: $optionId,
            calcMethod: 'multiply',
            calcParam: 2.0,
            fixedQty: 0.0,
        );

        $blueprintOptionIds = [$optionId];

        // 6.0 * 2.0 = 12.0
        $this->assertEquals(12.0, $this->service->calculateConsumableQuantity($consumable, $selected, $blueprintOptionIds));
    }

    /** @test */
    public function blueprint_option_divide_uses_parameter_as_divisor(): void
    {
        $optionId = 'opt-cap';
        $selected = $this->makeSelectedByOptionId($optionId, 12.0);

        $consumable = $this->makeConsumable(
            calcType: 'blueprint_option',
            sourceOptionId: $optionId,
            calcMethod: 'divide',
            calcParam: 4.0,
            fixedQty: 0.0,
        );

        $blueprintOptionIds = [$optionId];

        // 12.0 / 4.0 = 3.0
        $this->assertEquals(3.0, $this->service->calculateConsumableQuantity($consumable, $selected, $blueprintOptionIds));
    }

    /** @test */
    public function blueprint_option_add_returns_numeric_plus_parameter(): void
    {
        $optionId = 'opt-pressure';
        $selected = $this->makeSelectedByOptionId($optionId, 14.0);

        $consumable = $this->makeConsumable(
            calcType: 'blueprint_option',
            sourceOptionId: $optionId,
            calcMethod: 'add',
            calcParam: 2.0,
            fixedQty: 0.0,
        );

        $blueprintOptionIds = [$optionId];

        // 14.0 + 2.0 = 16.0
        $this->assertEquals(16.0, $this->service->calculateConsumableQuantity($consumable, $selected, $blueprintOptionIds));
    }

    /** @test */
    public function blueprint_option_subtract_clamps_to_zero(): void
    {
        $optionId = 'opt-cap';
        $selected = $this->makeSelectedByOptionId($optionId, 3.0);

        $consumable = $this->makeConsumable(
            calcType: 'blueprint_option',
            sourceOptionId: $optionId,
            calcMethod: 'subtract',
            calcParam: 10.0,    // 3 - 10 would be negative
            fixedQty: 0.0,
        );

        $blueprintOptionIds = [$optionId];

        // max(0, 3.0 - 10.0) = 0.0
        $this->assertEquals(0.0, $this->service->calculateConsumableQuantity($consumable, $selected, $blueprintOptionIds));
    }

    /** @test */
    public function returns_zero_when_source_option_not_selected(): void
    {
        $selected = collect(); // nothing selected

        $consumable = $this->makeConsumable(
            calcType: 'blueprint_option',
            sourceOptionId: 'opt-cap',
            calcMethod: 'multiply',
            calcParam: 1.0,
            fixedQty: 0.0,
        );

        $blueprintOptionIds = ['opt-cap']; // the option exists in the blueprint but not selected

        $this->assertEquals(0.0, $this->service->calculateConsumableQuantity($consumable, $selected, $blueprintOptionIds));
    }

    /** @test */
    public function numeric_value_is_used_not_display_label_parsing(): void
    {
        // Regression guard: the service MUST use numeric_value, not eval() or
        // regex-parse "6 kilograms" or any display label.
        $optionId = 'opt-cap';
        $selected = $this->makeSelectedByOptionId($optionId, 6.0); // numeric_value=6.0, label irrelevant

        $consumable = $this->makeConsumable(
            calcType: 'blueprint_option',
            sourceOptionId: $optionId,
            calcMethod: 'multiply',
            calcParam: 1.0,
            fixedQty: 0.0,
        );

        $blueprintOptionIds = [$optionId];

        // 6.0 × 1.0 = 6.0 — driven by numeric_value, NOT by parsing any display string
        $this->assertEquals(6.0, $this->service->calculateConsumableQuantity($consumable, $selected, $blueprintOptionIds));
    }

    /** @test */
    public function divide_by_zero_returns_zero_safely(): void
    {
        $optionId = 'opt-cap';
        $selected = $this->makeSelectedByOptionId($optionId, 12.0);

        $consumable = $this->makeConsumable(
            calcType: 'blueprint_option',
            sourceOptionId: $optionId,
            calcMethod: 'divide',
            calcParam: 0.0,   // divide by zero!
            fixedQty: 0.0,
        );

        $blueprintOptionIds = [$optionId];

        $this->assertEquals(0.0, $this->service->calculateConsumableQuantity($consumable, $selected, $blueprintOptionIds));
    }

    // ────────────────────────────────────────────────────────────────────────
    // Architecture guardrails — verify obsolete patterns no longer work
    // ────────────────────────────────────────────────────────────────────────

    /** @test */
    public function legacy_multiply_by_capacity_method_is_not_supported(): void
    {
        // A consumable stored with the old method string must now fall through to fixed_quantity,
        // not silently re-activate the capacity multiply path.
        $optionId = 'opt-cap';
        $selected = $this->makeSelectedByOptionId($optionId, 9.0);

        $consumable = $this->makeConsumable(
            calcType: 'fixed',           // type is fixed, not blueprint_option
            sourceOptionId: $optionId,
            calcMethod: 'multiply',      // method is irrelevant when type=fixed
            calcParam: 1.0,
            fixedQty: 2.0,              // should return this, ignoring the option
        );

        $blueprintOptionIds = [$optionId];

        // Since type=fixed, result must be fixed_quantity=2.0, NOT 9.0
        $this->assertEquals(2.0, $this->service->calculateConsumableQuantity($consumable, $selected, $blueprintOptionIds));
    }

    /** @test */
    public function no_compatibility_fields_in_consumable_calculation(): void
    {
        // Regression: calculation should never involve compatible_* fields.
        // A consumable object without any compatible_* properties must work fine.
        $consumable = $this->makeConsumable('fixed', null, 'fixed', null, 7.0);

        $blueprintOptionIds = [];

        // Should not throw or read any compatibility properties
        $result = $this->service->calculateConsumableQuantity($consumable, collect(), $blueprintOptionIds);

        $this->assertEquals(7.0, $result);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Validation tests for blueprint membership
    // ────────────────────────────────────────────────────────────────────────

    /** @test */
    public function resolveSelectedOptions_throws_exception_when_value_not_in_blueprint(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Create two blueprints and an option for each
        $blueprint1 = EquipmentBlueprint::create([
            'id' => 'bp-1',
            'code' => 'BP-1',
            'name' => ['en' => 'Blueprint 1'],
            'status' => 'active',
        ]);
        $blueprint2 = EquipmentBlueprint::create([
            'id' => 'bp-2',
            'code' => 'BP-2',
            'name' => ['en' => 'Blueprint 2'],
            'status' => 'active',
        ]);

        $option1 = BlueprintOption::create([
            'blueprint_id' => $blueprint1->id,
            'id' => 'opt-1',
            'code' => 'OPT-1',
            'name' => ['en' => 'Option 1'],
            'data_type' => 'select',
            'sort_order' => 0,
        ]);
        $option2 = BlueprintOption::create([
            'blueprint_id' => $blueprint2->id,
            'id' => 'opt-2',
            'code' => 'OPT-2',
            'name' => ['en' => 'Option 2'],
            'data_type' => 'select',
            'sort_order' => 0,
        ]);

        // Create a value for option2 (the one that should not belong to blueprint1)
        $value2 = \App\Modules\Catalog\Models\BlueprintOptionValue::create([
            'option_id' => $option2->id,
            'value' => json_encode(['en' => 'Value 2']),
            'code' => 'VAL-2',
            'numeric_value' => null,
            'unit' => null,
            'sort_order' => 0,
        ]);

        $this->service->resolveSelectedOptions([$value2->id], [$option1->id]);
    }

    /** @test */
    public function evaluateConditions_throws_exception_when_condition_option_not_in_blueprint(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition references option foreign-opt which does not belong to the blueprint.");

        $selectedByOptionId = collect(); // no selected options
        $blueprintOptionIds = ['opt-1']; // only opt-1 is in the blueprint

        $conditions = [
            'rules' => [
                ['option_id' => 'foreign-opt', 'operator' => 'equals', 'value' => 'something'],
            ],
        ];

        $this->service->evaluateConditions($conditions, $selectedByOptionId, $blueprintOptionIds);
    }

    /** @test */
    public function calculateConsumableQuantity_throws_exception_when_source_option_not_in_blueprint(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Calculation source option foreign-opt does not belong to the blueprint.");

        $selectedByOptionId = collect(); // no selected options
        $blueprintOptionIds = ['opt-1']; // only opt-1 is in the blueprint

        $consumable = $this->makeConsumable(
            calcType: 'blueprint_option',
            sourceOptionId: 'foreign-opt', // not in blueprint
            calcMethod: 'multiply',
            calcParam: 1.0,
            fixedQty: 0.0,
        );

        $this->service->calculateConsumableQuantity($consumable, $selectedByOptionId, $blueprintOptionIds);
    }
}
