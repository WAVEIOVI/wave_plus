<?php

namespace App\Modules\ClientAssets\Requests;

use App\Modules\ClientAssets\Services\WorkwearService;
use Illuminate\Foundation\Http\FormRequest;

class BatchStoreWorkwearItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model_id' => 'required|ulid|exists:workwear_models,id',
            'client_id' => 'required|ulid|exists:clients,id',
            'sales_order_id' => 'nullable|ulid|exists:sales_orders,id',
            'delivery_note_id' => 'nullable|ulid|exists:delivery_notes,id',
            'supplier_id' => 'nullable|ulid|exists:suppliers,id',
            'items' => 'required|array|min:1|max:500',
            'items.*.sku' => 'nullable|string|max:80',
            'items.*.color' => 'nullable|string|max:50',
            'items.*.supplier_id' => 'nullable|ulid|exists:suppliers,id',
            'items.*.garment_type' => 'nullable|string|max:80',
            'items.*.size' => 'nullable|string|max:20',
            'items.*.item_condition' => 'nullable|in:new,good,fair,worn,damaged',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:2000',
            'items.*.option_values' => 'nullable|array',
            'items.*.option_value_ids' => 'nullable|array',
            'items.*.option_value_ids.*' => 'required|ulid|exists:workwear_model_option_values,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $service = app(WorkwearService::class);

            foreach ($service->referenceErrors(
                $this->input('client_id'),
                $this->input('model_id'),
                $this->input('sales_order_id'),
                $this->input('delivery_note_id'),
            ) as $field => $message) {
                $validator->errors()->add($field, $message);
            }

            foreach ((array) $this->input('items', []) as $idx => $row) {
                foreach ($service->referenceErrors(
                    $this->input('client_id'),
                    $this->input('model_id'),
                    null,
                    null,
                    (array) ($row['option_value_ids'] ?? []),
                ) as $field => $message) {
                    if ($field === 'option_value_ids') {
                        $validator->errors()->add("items.{$idx}.option_value_ids", $message);
                    }
                }
            }
        });
    }
}
