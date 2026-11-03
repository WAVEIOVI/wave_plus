<?php

namespace App\Modules\ClientAssets\Requests;

use App\Modules\ClientAssets\Models\WorkwearModel;
use App\Modules\ClientAssets\Requests\Concerns\WorkwearModelStudioRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkwearModelRequest extends FormRequest
{
    use WorkwearModelStudioRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'sometimes|ulid|exists:clients,id',
            'name' => 'sometimes|string|max:255',
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
            'status' => 'sometimes|in:draft,active,discontinued',
            'options' => 'nullable|array',
            'options.*.id' => 'nullable|ulid',
            'options.*.name' => 'required|string|max:100',
            'options.*.ui_control' => 'nullable|string|max:50',
            'options.*.sort_order' => 'nullable|integer',
            'options.*.values' => 'nullable|array',
            'options.*.values.*.id' => 'nullable|ulid',
            'options.*.values.*.value' => 'required|string|max:100',
            'options.*.values.*.sort_order' => 'nullable|integer',
            'notes' => 'nullable|string|max:5000',
        ] + $this->studioRules();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $routeModel = $this->route('model');
            $model = $routeModel instanceof WorkwearModel
                ? $routeModel
                : WorkwearModel::query()->find((string) $routeModel);

            if ($this->filled('client_id')
                && $model
                && $model->client_id !== $this->input('client_id')
                && $model->items()->withTrashed()->exists()) {
                $validator->errors()->add('client_id', 'The client cannot be changed because this model already has items.');
            }

            $this->validateCatalogItem($validator);
            $this->validatePlacementMedia($validator, $model);
            $this->validateStudioPlacements($validator, $model);
        });
    }
}
