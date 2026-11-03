<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipmentBlueprintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bpId = $this->route('equipmentBlueprint');

        return [
            'code' => "nullable|string|max:60|unique:equipment_blueprints,code,{$bpId}",
            'name' => 'nullable|array',
            'name.*' => 'nullable|string|max:255',
            'description' => 'nullable|array',
            'description.*' => 'nullable|string',
            'status' => 'nullable|in:active,draft,inactive',

            // Technical specs
            'equipment_class_id' => 'nullable|ulid|exists:equipment_classes,id',
            'working_pressure_bar' => 'nullable|numeric|min:0',
            'fire_class_rating' => 'nullable|string|max:40',
            'shelf_life_years' => 'nullable|integer|min:0',

            // Guidelines
            'guidelines' => 'nullable|array',
            'guidelines.*' => 'exists:guidelines,id',

            // ── Intervention Cycles ─────────────────────────────────
            'intervention_cycles' => 'nullable|array',
            'intervention_cycles.*.intervention_type' => 'required|in:inspection,refill,hydro_test,maintenance,calibration,replacement',
            'intervention_cycles.*.first_interval_months' => 'required|integer|min:1',
            'intervention_cycles.*.recurring_interval_months' => 'nullable|integer|min:1',
            'intervention_cycles.*.notes' => 'nullable|string',

            // ── Blueprint Options ───────────────────────────────────
            'blueprint_options' => 'nullable|array',
            'blueprint_options.*.id' => 'nullable|string',
            'blueprint_options.*.code' => 'nullable|string|max:50',
            'blueprint_options.*.name' => 'required|array',
            'blueprint_options.*.name.*' => 'required|string',
            'blueprint_options.*.data_type' => 'nullable|string|in:select,radio,numeric,text',
            'blueprint_options.*.sort_order' => 'nullable|integer',
            'blueprint_options.*.values' => 'nullable|array',
            'blueprint_options.*.values.*.id' => 'nullable|string',
            'blueprint_options.*.values.*.code' => 'nullable|string|max:50',
            'blueprint_options.*.values.*.value' => 'required|array',
            'blueprint_options.*.values.*.value.*' => 'required|string',
            'blueprint_options.*.values.*.numeric_value' => 'nullable|numeric',
            'blueprint_options.*.values.*.unit' => 'nullable|string|max:20',
            'blueprint_options.*.values.*.sort_order' => 'nullable|integer',

            // ── Servicing Items (Level 1) with nested Consumables (Level 2) ──
            'servicing_items' => 'nullable|array',
            'servicing_items.*.id' => 'nullable|string',
            'servicing_items.*.servicing_item_id' => 'required|exists:catalog_items,id',
            'servicing_items.*.applicability_conditions' => 'nullable|array',
            'servicing_items.*.consumables' => 'nullable|array',
            'servicing_items.*.consumables.*.id' => 'nullable|string',
            'servicing_items.*.consumables.*.consumable_item_id' => 'required|exists:catalog_items,id',
            'servicing_items.*.consumables.*.calculation_type' => 'nullable|string|in:fixed,blueprint_option',
            'servicing_items.*.consumables.*.calculation_source_option_id' => 'nullable|string',
            'servicing_items.*.consumables.*.calculation_method' => 'nullable|string|in:fixed,multiply,add,subtract,divide',
            'servicing_items.*.consumables.*.calculation_parameter' => 'nullable|numeric',
            'servicing_items.*.consumables.*.fixed_quantity' => 'nullable|numeric|min:0',
            'servicing_items.*.consumables.*.applicability_conditions' => 'nullable|array',
        ];
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        // 1. Sanitize translatable name
        if (isset($input['name'])) {
            if (is_string($input['name'])) {
                $input['name'] = ['en' => trim($input['name'])];
            } elseif (is_array($input['name'])) {
                $input['name'] = array_filter($input['name'], fn ($v) => $v !== null && trim((string) $v) !== '');
            }
        }

        // 2. Sanitize translatable description
        if (isset($input['description'])) {
            if (is_string($input['description'])) {
                $input['description'] = ['en' => trim($input['description'])];
            } elseif (is_array($input['description'])) {
                $input['description'] = array_filter($input['description'], fn ($v) => $v !== null && trim((string) $v) !== '');
            }
        }

        // 3. Filter empty intervention cycles
        if (isset($input['intervention_cycles']) && is_array($input['intervention_cycles'])) {
            $input['intervention_cycles'] = array_values(array_filter($input['intervention_cycles'], function ($cycle) {
                return ! empty($cycle['intervention_type']) && ! empty($cycle['first_interval_months']);
            }));
        }

        // 4. Filter empty servicing items & consumables
        if (isset($input['servicing_items']) && is_array($input['servicing_items'])) {
            $filteredServicings = [];
            foreach ($input['servicing_items'] as $svc) {
                if (empty($svc['servicing_item_id'])) {
                    continue;
                }

                if (isset($svc['consumables']) && is_array($svc['consumables'])) {
                    $svc['consumables'] = array_values(array_filter($svc['consumables'], function ($con) {
                        return ! empty($con['consumable_item_id']);
                    }));
                }

                $filteredServicings[] = $svc;
            }
            $input['servicing_items'] = $filteredServicings;
        }

        // 5. Sanitize options and values
        if (isset($input['blueprint_options']) && is_array($input['blueprint_options'])) {
            $filteredOptions = [];
            foreach ($input['blueprint_options'] as $opt) {
                if (is_string($opt['name'] ?? null)) {
                    $opt['name'] = ['en' => trim($opt['name'])];
                } elseif (is_array($opt['name'] ?? null)) {
                    $opt['name'] = array_filter($opt['name'], fn ($v) => $v !== null && trim((string) $v) !== '');
                }

                if (empty($opt['name']) && empty($opt['code'])) {
                    continue;
                }
                if (empty($opt['name'])) {
                    $opt['name'] = ['en' => $opt['code'] ?? 'Option'];
                }

                if (isset($opt['values']) && is_array($opt['values'])) {
                    $filteredValues = [];
                    foreach ($opt['values'] as $val) {
                        if (is_string($val['value'] ?? null)) {
                            $val['value'] = ['en' => trim($val['value'])];
                        } elseif (is_array($val['value'] ?? null)) {
                            $val['value'] = array_filter($val['value'], fn ($v) => $v !== null && trim((string) $v) !== '');
                        }

                        if (empty($val['value']) && empty($val['code'])) {
                            continue;
                        }
                        if (empty($val['value'])) {
                            $val['value'] = ['en' => $val['code'] ?? 'Value'];
                        }

                        $filteredValues[] = $val;
                    }
                    $opt['values'] = $filteredValues;
                }

                $filteredOptions[] = $opt;
            }
            $input['blueprint_options'] = $filteredOptions;
        }

        $this->replace($input);
    }
}
