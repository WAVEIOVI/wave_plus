<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Requests;

use App\Modules\Catalog\Models\BlueprintOption;
use App\Modules\Catalog\Models\BlueprintOptionValue;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\ClientAssets\Models\ClientSiteArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreClientEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|ulid|exists:clients,id',
            'site_id' => 'nullable|ulid|exists:client_sites,id',
            'area_id' => 'nullable|ulid|exists:client_site_areas,id',
            'equipment_class_id' => 'nullable|ulid|exists:equipment_classes,id',
            'blueprint_id' => 'nullable|ulid|exists:equipment_blueprints,id',
            'catalog_item_id' => 'nullable|ulid|exists:catalog_items,id',
            'catalog_variant_id' => 'nullable|ulid|exists:catalog_variants,id',
            'brand_id' => 'nullable|ulid|exists:catalog_brands,id',
            'equipment_code' => 'nullable|string|max:30|unique:client_equipment,equipment_code',
            'brand' => 'nullable|string|max:100',
            'model_name' => 'nullable|string|max:150',
            'serial_number' => 'nullable|string|max:100',
            'internal_number' => 'nullable|string|max:100',
            'manufacturer_part_number' => 'nullable|string|max:100',
            'asset_tag' => 'nullable|string|max:100',
            'qr_code' => 'nullable|string|max:100|unique:equipment_qr_codes,qr_code',
            'manufacture_date' => 'nullable|date',
            'install_date' => 'nullable|date',
            'commissioning_date' => 'nullable|date',
            'decommissioning_date' => 'nullable|date',
            'warranty_start_date' => 'nullable|date',
            'warranty_end_date' => 'nullable|date',
            'last_inspection_date' => 'nullable|date',
            'next_inspection_date' => 'nullable|date',
            'inspection_freq_months' => 'nullable|integer|min:1|max:120',
            'status' => 'nullable|string|max:50',
            'lifecycle_status' => 'nullable|string|in:draft,operational,under_maintenance,stored,decommissioned,scrapped,disposed',
            'condition_status' => 'nullable|string|in:excellent,good,fair,poor,critical',
            'criticality' => 'nullable|string|in:low,medium,high,critical',
            'description' => 'nullable|string',
            'specs' => 'nullable|array',
            'certifications' => 'nullable|array',
            'notes' => 'nullable|string',

            // Options snapshot
            'options' => 'nullable|array',
            'options.*.blueprint_option_id' => 'required_with:options|exists:blueprint_options,id',
            'options.*.option_value_id' => 'nullable|exists:blueprint_option_values,id',
            'options.*.option_name' => 'nullable|string|max:255',
            'options.*.value_text' => 'nullable|string',
            'options.*.value_number' => 'nullable|numeric',
            'options.*.value_boolean' => 'nullable|boolean',
            'options.*.value_date' => 'nullable|date',

            // Intervention cycles snapshot
            'intervention_cycles' => 'nullable|array',
            'intervention_cycles.*.source_blueprint_cycle_id' => 'nullable|exists:blueprint_intervention_cycles,id',
            'intervention_cycles.*.cycle_type' => 'required_with:intervention_cycles|string|max:50',
            'intervention_cycles.*.interval_value' => 'nullable|integer|min:1',
            'intervention_cycles.*.interval_unit' => 'nullable|string|in:days,months,years',
            'intervention_cycles.*.last_intervention_date' => 'nullable|date',
            'intervention_cycles.*.next_due_date' => 'nullable|date',
            'intervention_cycles.*.enabled' => 'nullable|boolean',
            'intervention_cycles.*.notes' => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $clientId = $this->input('client_id');
            $siteId = $this->input('site_id');
            $areaId = $this->input('area_id');
            $classId = $this->input('equipment_class_id');
            $blueprintId = $this->input('blueprint_id');

            // Validate site belongs to client
            if ($clientId && $siteId) {
                $site = ClientSite::find($siteId);
                if ($site && $site->client_id !== $clientId) {
                    $validator->errors()->add('site_id', 'The selected site does not belong to the selected client.');
                }
            }

            // Validate area belongs to site
            if ($siteId && $areaId) {
                $area = ClientSiteArea::find($areaId);
                if ($area && $area->site_id !== $siteId) {
                    $validator->errors()->add('area_id', 'The selected area does not belong to the selected site.');
                }
            } elseif ($areaId && ! $siteId) {
                $validator->errors()->add('area_id', 'Cannot assign an area without selecting a site.');
            }

            // Validate blueprint belongs to class
            if ($classId && $blueprintId) {
                $blueprint = EquipmentBlueprint::find($blueprintId);
                if ($blueprint && $blueprint->equipment_class_id !== $classId) {
                    $validator->errors()->add('blueprint_id', 'The selected blueprint does not belong to the selected equipment class.');
                }
            }

            // Validate option values belong to option
            $options = $this->input('options', []);
            if (is_array($options)) {
                foreach ($options as $index => $opt) {
                    $optId = $opt['blueprint_option_id'] ?? null;
                    $valId = $opt['option_value_id'] ?? null;
                    if ($optId && $blueprintId) {
                        $optionModel = BlueprintOption::find($optId);
                        if ($optionModel && $optionModel->blueprint_id !== $blueprintId) {
                            $validator->errors()->add("options.{$index}.blueprint_option_id", 'The option does not belong to the selected blueprint.');
                        }
                    }
                    if ($optId && $valId) {
                        $valueModel = BlueprintOptionValue::find($valId);
                        $valueOptionId = $valueModel?->option_id ?? $valueModel?->blueprint_option_id ?? null;
                        if ($valueModel && $valueOptionId && (string) $valueOptionId !== (string) $optId) {
                            $validator->errors()->add("options.{$index}.option_value_id", 'The option value does not belong to the specified option.');
                        }
                    }
                }
            }
        });
    }
}
