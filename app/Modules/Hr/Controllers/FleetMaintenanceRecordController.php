<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleMaintenanceRecord;
use App\Modules\Hr\Resources\VehicleMaintenanceRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetMaintenanceRecordController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function index(Request $request, ?string $vehicle = null): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $query = VehicleMaintenanceRecord::query()->with(['vehicle', 'workshopProvider', 'workshopUnit']);

        if ($vehicle) {
            $query->where('vehicle_id', $vehicle);
        } elseif ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->string('vehicle_id')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('maintenance_type')) {
            $query->where('maintenance_type', $request->string('maintenance_type')->toString());
        }
        if ($request->boolean('upcoming_only')) {
            $query->upcoming((int) $request->input('days_ahead', 30));
        }

        $records = $query->orderByDesc('scheduled_date')->paginate(min(max((int) $request->input('per_page', 15), 1), 100));

        return response()->json([
            'data' => VehicleMaintenanceRecordResource::collection($records->items()),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function show(string $maintenanceRecord): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $record = VehicleMaintenanceRecord::with(['vehicle', 'workshopProvider', 'workshopUnit'])->findOrFail($maintenanceRecord);

        return response()->json(['data' => VehicleMaintenanceRecordResource::make($record)]);
    }

    public function store(Request $request, ?string $vehicle = null): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $vehicleId = $vehicle ?? $request->input('vehicle_id');
        $vehicleModel = Vehicle::findOrFail($vehicleId);

        $data = $request->validate([
            'maintenance_type' => ['required', 'in:preventive,corrective,inspection,recall,other'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'scheduled_date' => ['required', 'date'],
            'completed_date' => ['nullable', 'date'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'workshop_provider_id' => ['nullable', 'ulid', 'exists:service_providers,id'],
            'workshop_unit_id' => ['nullable', 'ulid', 'exists:workshop_units,id'],
            'workshop_name' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'work_items' => ['nullable', 'array'],
            'parts_used' => ['nullable', 'array'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'parts_cost' => ['nullable', 'numeric', 'min:0'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'next_service_date' => ['nullable', 'date'],
            'next_service_km' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:scheduled,in_progress,completed,cancelled'],
            'notes' => ['nullable', 'string'],
            'documents' => ['nullable', 'array'],
            'scheduled_by' => ['nullable', 'ulid', 'exists:users,id'],
            'performed_by' => ['nullable', 'ulid', 'exists:users,id'],
        ]);

        $data['vehicle_id'] = $vehicleModel->id;
        $data['status'] ??= 'scheduled';
        $data['currency'] ??= 'SAR';
        $data['created_by'] = $request->user()?->id;

        $record = VehicleMaintenanceRecord::create($data);

        return response()->json(['data' => VehicleMaintenanceRecordResource::make($record)], 201);
    }

    public function update(Request $request, string $maintenanceRecord): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');
        $record = VehicleMaintenanceRecord::findOrFail($maintenanceRecord);

        $data = $request->validate([
            'maintenance_type' => ['sometimes', 'in:preventive,corrective,inspection,recall,other'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'scheduled_date' => ['sometimes', 'date'],
            'completed_date' => ['nullable', 'date'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'workshop_provider_id' => ['nullable', 'ulid', 'exists:service_providers,id'],
            'workshop_unit_id' => ['nullable', 'ulid', 'exists:workshop_units,id'],
            'workshop_name' => ['nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'work_items' => ['nullable', 'array'],
            'parts_used' => ['nullable', 'array'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'parts_cost' => ['nullable', 'numeric', 'min:0'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['nullable', 'date'],
            'next_service_date' => ['nullable', 'date'],
            'next_service_km' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:scheduled,in_progress,completed,cancelled'],
            'notes' => ['nullable', 'string'],
            'documents' => ['nullable', 'array'],
            'scheduled_by' => ['nullable', 'ulid', 'exists:users,id'],
            'performed_by' => ['nullable', 'ulid', 'exists:users,id'],
        ]);

        $data['updated_by'] = $request->user()?->id;
        $record->update($data);

        // If completed, optionally update vehicle's last_service_date and next_service_date
        if (($data['status'] ?? null) === 'completed') {
            $vehicle = $record->vehicle;
            if ($vehicle) {
                $vehicleUpdates = [
                    'last_service_date' => $data['completed_date'] ?? now()->toDateString(),
                ];
                if (! empty($data['next_service_date'])) {
                    $vehicleUpdates['next_service_date'] = $data['next_service_date'];
                }
                $vehicle->update($vehicleUpdates);
            }
        }

        return response()->json(['data' => VehicleMaintenanceRecordResource::make($record->fresh())]);
    }

    public function destroy(string $maintenanceRecord): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.archive');
        $record = VehicleMaintenanceRecord::findOrFail($maintenanceRecord);
        $record->delete();

        return response()->json(['message' => 'Vehicle maintenance record deleted']);
    }
}
