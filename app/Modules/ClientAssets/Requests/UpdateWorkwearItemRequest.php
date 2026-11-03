<?php

namespace App\Modules\ClientAssets\Requests;

use App\Modules\ClientAssets\Enums\WorkwearItemCondition;
use App\Modules\ClientAssets\Enums\WorkwearItemStatus;
use App\Modules\ClientAssets\Models\WorkwearItem;
use App\Modules\ClientAssets\Services\WorkwearService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkwearItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model_id' => 'sometimes|ulid|exists:workwear_models,id',
            'client_id' => 'sometimes|ulid|exists:clients,id',
            'garment_type' => 'nullable|string|max:80',
            'size' => 'nullable|string|max:20',
            'sku' => 'nullable|string|max:80',
            'color' => 'nullable|string|max:50',
            'supplier_id' => 'nullable|ulid|exists:suppliers,id',
            'status' => ['sometimes', Rule::in(WorkwearItemStatus::values())],
            'item_condition' => ['sometimes', Rule::in(WorkwearItemCondition::values())],
            'unit_cost' => 'nullable|numeric|min:0',
            'sales_order_id' => 'nullable|ulid|exists:sales_orders,id',
            'delivery_note_id' => 'nullable|ulid|exists:delivery_notes,id',
            'notes' => 'nullable|string|max:2000',
            'option_values' => 'nullable|array',
            'option_value_ids' => 'nullable|array',
            'option_value_ids.*' => 'required|ulid|exists:workwear_model_option_values,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $routeItem = $this->route('item');
            $item = $routeItem instanceof WorkwearItem
                ? $routeItem
                : WorkwearItem::query()->find((string) $routeItem);

            $clientId = $item?->client_id ?? $this->input('client_id');
            $modelId = $this->input('model_id', $item?->model_id);

            if ($item && $this->filled('client_id') && $this->input('client_id') !== $item->client_id) {
                $validator->errors()->add('client_id', 'An item cannot be moved to a different client.');

                return;
            }

            foreach (app(WorkwearService::class)->referenceErrors(
                $clientId,
                $modelId,
                $this->input('sales_order_id', $item?->sales_order_id),
                $this->input('delivery_note_id', $item?->delivery_note_id),
                (array) $this->input('option_value_ids', []),
            ) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });
    }
}
