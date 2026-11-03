<?php

namespace App\Modules\Catalog\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Catalog\Models\BlueprintOption
 */
class BlueprintOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->getTranslations('name'),
            'data_type' => $this->data_type ?? 'select',
            'sort_order' => $this->sort_order,
            'values' => $this->whenLoaded('values', function () {
                return $this->values->map(function (\App\Modules\Catalog\Models\BlueprintOptionValue $v) {
                    return [
                        'id' => $v->id,
                        'code' => $v->code,
                        'value' => $v->getTranslations('value'),
                        'numeric_value' => $v->numeric_value !== null ? (float) $v->numeric_value : null,
                        'unit' => $v->unit,
                        'sort_order' => $v->sort_order,
                    ];
                });
            }),
        ];
    }
}
