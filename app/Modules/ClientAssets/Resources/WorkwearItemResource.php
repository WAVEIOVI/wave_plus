<?php

namespace App\Modules\ClientAssets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkwearItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusEnum = $this->status;
        $conditionEnum = $this->item_condition;

        return [
            'id' => $this->id,
            'item_code' => $this->item_code,
            'uuid' => $this->uuid,
            'model_id' => $this->model_id,
            'client_id' => $this->client_id,
            'garment_type' => $this->garment_type,
            'sku' => $this->sku,
            'size' => $this->size,
            'color' => $this->color,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? ['id' => $this->supplier->id, 'legal_name' => $this->supplier->legal_name] : null),
            'photos' => $this->when($this->relationLoaded('media'), fn () => $this->getMedia('photos')->map(fn ($m) => [
                'id' => $m->id,
                'url' => $m->getUrl(),
                'thumb' => $m->hasGeneratedConversion('thumb') ? $m->getUrl('thumb') : $m->getUrl(),
                'name' => $m->file_name,
            ])->values()),
            'status' => is_object($statusEnum) ? $statusEnum->value : $statusEnum,
            'status_label' => is_object($statusEnum) ? $statusEnum->label() : ucfirst(str_replace('_', ' ', $statusEnum ?? '')),
            'item_condition' => is_object($conditionEnum) ? $conditionEnum->value : $conditionEnum,
            'condition_label' => is_object($conditionEnum) ? $conditionEnum->label() : ucfirst($conditionEnum ?? ''),
            'is_stolen' => $this->is_stolen,
            'stolen_data' => $this->stolen_data,
            'retired_data' => $this->retired_data,
            'unit_cost' => $this->unit_cost,
            'notes' => $this->notes,
            'option_values' => $this->option_values,
            'sales_order_id' => $this->sales_order_id,
            'delivery_note_id' => $this->delivery_note_id,
            'sales_order' => $this->whenLoaded('salesOrder', fn () => $this->salesOrder ? [
                'id' => $this->salesOrder->id,
                'reference' => $this->salesOrder->reference,
            ] : null),
            'delivery_note' => $this->whenLoaded('deliveryNote', fn () => $this->deliveryNote ? [
                'id' => $this->deliveryNote->id,
                'dn_number' => $this->deliveryNote->dn_number,
            ] : null),

            'workwear_model' => $this->whenLoaded('workwearModel', fn () => [
                'id' => $this->workwearModel->id, 'model_ref' => $this->workwearModel->model_ref,
                'name' => $this->workwearModel->name,
                'garment_types' => $this->workwearModel->garment_types,
            ]),
            'client' => $this->whenLoaded('client', fn () => ['id' => $this->client->id, 'legal_name' => $this->client->legal_name]),
            'current_assignment' => new WorkwearAssignmentResource($this->whenLoaded('currentAssignment')),
            'assignments' => WorkwearAssignmentResource::collection($this->whenLoaded('assignments')),
            'item_option_values' => $this->whenLoaded('itemOptionValues', fn () => $this->itemOptionValues->map(fn ($iov) => [
                'id' => $iov->id,
                'option_id' => $iov->option_id,
                'option_name' => $iov->option?->name,
                'value_id' => $iov->value_id,
                'value' => $iov->optionValue?->value,
            ])),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
