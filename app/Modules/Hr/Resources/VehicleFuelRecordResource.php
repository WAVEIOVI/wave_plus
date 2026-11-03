<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Hr\Models\VehicleFuelRecord
 */
class VehicleFuelRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'fill_date' => $this->fill_date ? \Illuminate\Support\Carbon::parse($this->fill_date)->format('Y-m-d') : null,
            'fill_time' => $this->fill_time,
            'fuel_type' => $this->fuel_type,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'odometer_km' => $this->odometer_km,
            'unit_price' => (float) $this->unit_price,
            'total_cost' => (float) $this->total_cost,
            'currency' => $this->currency,
            'supplier' => $this->supplier,
            'station' => $this->station,
            'city' => $this->city,
            'receipt_number' => $this->receipt_number,
            'card_last_four' => $this->card_last_four,
            'payment_method' => $this->payment_method,
            'is_full_tank' => (bool) $this->is_full_tank,
            'notes' => $this->notes,
            'cost_per_unit' => $this->cost_per_unit,
            'filled_by' => $this->whenLoaded('filledByEmployee', fn () => $this->filledByEmployee ? [
                'id' => $this->filledByEmployee->getKey(),
                'name' => $this->filledByEmployee->getAttribute('name'),
            ] : null),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id' => $this->driver->getKey(),
                'name' => $this->driver->getAttribute('name'),
            ] : null),
            'created_at' => $this->created_at ? \Illuminate\Support\Carbon::parse($this->created_at)->toISOString() : null,
            'updated_at' => $this->updated_at ? \Illuminate\Support\Carbon::parse($this->updated_at)->toISOString() : null,
        ];
    }
}
