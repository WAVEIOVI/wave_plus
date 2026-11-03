<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Hr\Models\Vehicle
 */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        // Normalize relationships with typed resources when loaded
        if ($this->relationLoaded('currentAssignment')) {
            $data['current_assignment'] = $this->currentAssignment
                ? VehicleAssignmentResource::make($this->currentAssignment)
                : null;
            $data['currentAssignment'] = $data['current_assignment'];
        }

        if ($this->relationLoaded('assignments')) {
            $data['assignments'] = VehicleAssignmentResource::collection($this->assignments);
        }

        if ($this->relationLoaded('documents')) {
            $data['documents'] = VehicleDocumentResource::collection($this->documents);
        }

        if ($this->relationLoaded('fuelRecords')) {
            $data['fuel_records'] = VehicleFuelRecordResource::collection($this->fuelRecords);
            $data['fuelRecords'] = $data['fuel_records'];
        }

        if ($this->relationLoaded('maintenanceRecords')) {
            $data['maintenance_records'] = VehicleMaintenanceRecordResource::collection($this->maintenanceRecords);
            $data['maintenanceRecords'] = $data['maintenance_records'];
        }

        if ($this->relationLoaded('checklists')) {
            $data['checklists'] = VehicleChecklistResource::collection($this->checklists);
        }

        if ($this->relationLoaded('activityLog')) {
            $data['activity_log'] = VehicleActivityLogResource::collection($this->activityLog);
            $data['activityLog'] = $data['activity_log'];
        }

        return $data;
    }
}
