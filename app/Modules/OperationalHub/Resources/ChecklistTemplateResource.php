<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'code'            => $this->code,
            'name'            => $this->getTranslations('name'),
            'description'     => $this->getTranslations('description'),
            'applicable_mode' => $this->applicable_mode,
            'category'        => $this->category,
            'is_active'       => $this->is_active,
            'sort_order'      => $this->sort_order,
            'items'           => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id'          => $item->id,
                'title'       => $item->getTranslations('title'),
                'description' => $item->getTranslations('description'),
                'answer_type' => $item->answer_type,
                'is_required' => $item->is_required,
                'is_active'   => $item->is_active,
                'sort_order'  => $item->sort_order,
            ])),
            'items_count'     => $this->relationLoaded('items') ? $this->items->count() : $this->items()->count(),
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
