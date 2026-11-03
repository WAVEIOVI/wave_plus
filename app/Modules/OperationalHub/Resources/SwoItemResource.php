<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SwoItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'swo_id'          => $this->swo_id,
            'so_line_id'      => $this->so_line_id,
            'catalog_item_id' => $this->catalog_item_id,
            'catalog_item'    => $this->whenLoaded('catalogItem', fn () => [
                'id'          => $this->catalogItem->id,
                'name'        => $this->catalogItem->name,
                'sku'         => $this->catalogItem->sku,
                'type'        => $this->catalogItem->type,
            ]),
            'qty_ordered'     => (float) $this->qty_ordered,
            'qty_completed'   => (float) $this->qty_completed,
            'status'          => $this->status,
            'notes'           => $this->notes,
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}
