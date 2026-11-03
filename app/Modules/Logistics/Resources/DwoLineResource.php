<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DwoLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'dwo_id'                 => $this->dwo_id,
            'so_item_id'             => $this->so_item_id,
            'catalog_item_id'        => $this->catalog_item_id,
            'catalog_variant_id'     => $this->catalog_variant_id,
            'description_snapshot'   => $this->description_snapshot,
            'variant_label_snapshot' => $this->variant_label_snapshot,
            'uom_code_snapshot'      => $this->uom_code_snapshot,
            'qty_ordered'            => (float) $this->qty_ordered,
            'qty_allocated'          => (float) $this->qty_allocated,
            'qty_reserved'           => (float) ($this->qty_reserved ?? 0),
            'qty_shortage'           => (float) ($this->qty_shortage ?? 0),
            'qty_picked'             => (float) ($this->qty_picked ?? 0),
            'qty_loaded'             => (float) ($this->qty_loaded ?? 0),
            'qty_delivered'          => (float) ($this->qty_delivered ?? 0),
            'qty_returned'           => (float) ($this->qty_returned ?? 0),
            'qty_remaining'          => $this->qty_remaining,
            'commercial_catalog_item_id'    => $this->commercial_catalog_item_id,
            'commercial_catalog_variant_id' => $this->commercial_catalog_variant_id,
            'substitution_status'    => $this->substitution_status,
            'substitution_reason'    => $this->substitution_reason,
            'lot_number'             => $this->lot_number,
            'serial_number'          => $this->serial_number,
            'warehouse_location_id'  => $this->warehouse_location_id,
            'warehouse_location'     => $this->warehouse_location ?? $this->warehouseLocation?->barcode,
            'item_status'            => [
                'value' => $this->item_status?->value ?? (string) $this->item_status,
                'label' => $this->item_status?->label() ?? ucfirst((string) $this->item_status),
            ],
            'condition_on_delivery'  => $this->condition_on_delivery,
            'driver_notes'           => $this->driver_notes,
            'catalog_item'           => $this->whenLoaded('catalogItem', fn () => [
                'id'   => $this->catalogItem->id,
                'code' => $this->catalogItem->code,
                'name' => $this->catalogItem->name,
                'sku'  => $this->catalogItem->sku,
            ]),
            'catalog_variant'        => $this->whenLoaded('catalogVariant', fn () => [
                'id'   => $this->catalogVariant->id,
                'name' => $this->catalogVariant->name,
                'sku'  => $this->catalogVariant->sku,
            ]),
        ];
    }
}
