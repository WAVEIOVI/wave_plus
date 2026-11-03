<?php

namespace App\Modules\ClientAssets\Requests;

use App\Modules\ClientAssets\Requests\Concerns\WorkwearModelStudioRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkwearModelRequest extends FormRequest
{
    use WorkwearModelStudioRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|ulid|exists:clients,id',
            'name' => 'required|string|max:255',
            'garment_types' => 'nullable|array',
            'fabric' => 'nullable|string|max:150',
            'colors' => 'nullable|array',
            'custom_type' => 'nullable|string|max:100',
            'print_text' => 'nullable|array',
            'print_type' => 'nullable|string|max:80',
            'logo_placement' => 'nullable|string|max:100',
            'logo_ged_ref' => 'nullable|string|max:255',
            'en_standards' => 'nullable|string|max:255',
            'fr_rating' => 'nullable|string|max:100',
            'waterproofing' => 'nullable|string|max:100',
            'supplier_id' => 'nullable|ulid|exists:suppliers,id',
            'status' => 'nullable|in:draft,active,discontinued',
            'options' => 'nullable|array',
            'options.*.name' => 'required|string|max:100',
            'options.*.ui_control' => 'nullable|string|max:50',
            'options.*.sort_order' => 'nullable|integer',
            'options.*.values' => 'nullable|array',
            'options.*.values.*.value' => 'required|string|max:100',
            'options.*.values.*.sort_order' => 'nullable|integer',
            'notes' => 'nullable|string|max:5000',
        ] + $this->studioRules();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isEmpty()) {
                $this->validateCatalogItem($validator);
                // A new model has no uploaded artwork yet, so placements cannot reference media.
                $this->validatePlacementMedia($validator, null);
                $this->validateStudioPlacements($validator, null);
            }
        });
    }
}
