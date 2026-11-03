<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Inventory\Models\StockMovement
 */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'movement_type' => $this->movement_type,
            'quantity' => (float) $this->quantity,
            'unit_cost' => $this->unit_cost ? (float) $this->unit_cost : null,
            'total_cost' => $this->total_cost ? (float) $this->total_cost : null,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'source_document_type' => $this->source_document_type,
            'source_document_id' => $this->source_document_id,

            // Relations
            'catalog_variant' => $this->whenLoaded('catalogVariant', fn () => [
                'id' => $this->catalogVariant->id,
                'sku' => $this->catalogVariant->sku,
                'name' => $this->catalogVariant->name,
            ]),

            'catalog_item' => $this->whenLoaded('catalogItem', fn () => [
                'id' => $this->catalogItem->id,
                'code' => $this->catalogItem->code,
                'sku' => $this->catalogItem->sku,
                'name' => $this->catalogItem->getTranslations('name'),
            ]),

            'stock_lot' => $this->whenLoaded('stockLot', fn () => [
                'id' => $this->stockLot?->id,
                'lot_number' => $this->stockLot?->lot_number,
            ]),

            'from_location' => $this->whenLoaded('fromLocation', fn () => [
                'id' => $this->fromLocation?->id,
                'barcode' => $this->fromLocation?->barcode,
                'full_path' => $this->fromLocation?->full_path,
            ]),

            'to_location' => $this->whenLoaded('toLocation', fn () => [
                'id' => $this->toLocation?->id,
                'barcode' => $this->toLocation?->barcode,
                'full_path' => $this->toLocation?->full_path,
            ]),

            'performer' => $this->whenLoaded('performer', fn () => [
                'id' => $this->performer?->id,
                'name' => $this->performer?->name,
            ]),

            'performed_at' => $this->performed_at->toISOString(),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
