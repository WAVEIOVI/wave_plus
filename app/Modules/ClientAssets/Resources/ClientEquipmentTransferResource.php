<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\ClientAssets\Models\ClientEquipmentTransfer
 */
class ClientEquipmentTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipment_id' => $this->equipment_id,
            'from_site_id' => $this->from_site_id,
            'from_site_name' => $this->fromSite?->name,
            'from_area_id' => $this->from_area_id,
            'from_area_name' => $this->fromArea?->name,
            'from_area_path' => $this->fromArea?->hierarchy_path,
            'to_site_id' => $this->to_site_id,
            'to_site_name' => $this->toSite?->name,
            'to_area_id' => $this->to_area_id,
            'to_area_name' => $this->toArea?->name,
            'to_area_path' => $this->toArea?->hierarchy_path,
            'transferred_by' => $this->transferredByUser ? [
                'id' => $this->transferredByUser->id,
                'name' => $this->transferredByUser->name,
            ] : null,
            'transfer_date' => $this->transfer_date?->toISOString(),
            'reason' => $this->reason,
            'condition_at_transfer' => $this->condition_at_transfer,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
