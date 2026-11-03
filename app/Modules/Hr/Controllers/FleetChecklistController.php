<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleChecklist;
use App\Modules\Hr\Resources\VehicleChecklistResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetChecklistController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function index(Request $request, ?string $vehicle = null): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $query = VehicleChecklist::query()->with(['vehicle', 'checklistTemplate', 'completedByEmployee', 'driver']);

        if ($vehicle) {
            $query->where('vehicle_id', $vehicle);
        } elseif ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->string('vehicle_id')->toString());
        }

        if ($request->filled('checklist_type')) {
            $query->where('checklist_type', $request->string('checklist_type')->toString());
        }
        if ($request->filled('overall_status')) {
            $query->where('overall_status', $request->string('overall_status')->toString());
        }
        if ($request->boolean('with_issues_only')) {
            $query->where('has_issues', true);
        }

        $records = $query->orderByDesc('completed_at')->paginate(min(max((int) $request->input('per_page', 15), 1), 100));

        return response()->json([
            'data' => VehicleChecklistResource::collection($records->items()),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function show(string $checklist): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $record = VehicleChecklist::with(['vehicle', 'checklistTemplate', 'completedByEmployee', 'driver'])->findOrFail($checklist);

        return response()->json(['data' => VehicleChecklistResource::make($record)]);
    }

    public function store(Request $request, ?string $vehicle = null): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $vehicleId = $vehicle ?? $request->input('vehicle_id');
        $vehicleModel = Vehicle::findOrFail($vehicleId);

        $data = $request->validate([
            'checklist_template_id' => ['nullable', 'ulid', 'exists:checklist_templates,id'],
            'checklist_type' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'completed_at' => ['nullable', 'date'],
            'completed_by_employee_id' => ['nullable', 'ulid', 'exists:users,id'],
            'driver_id' => ['nullable', 'ulid', 'exists:users,id'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'checklist_items' => ['nullable', 'array'],
            'overall_status' => ['required', 'in:passed,failed,passed_with_issues,pending'],
            'items_passed' => ['nullable', 'integer', 'min:0'],
            'items_failed' => ['nullable', 'integer', 'min:0'],
            'items_total' => ['nullable', 'integer', 'min:0'],
            'has_issues' => ['nullable', 'boolean'],
            'issues_found' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
            'requires_maintenance' => ['nullable', 'boolean'],
            'maintenance_record_id' => ['nullable', 'ulid', 'exists:vehicle_maintenance_records,id'],
            'mission_id' => ['nullable', 'ulid', 'exists:missions,id'],
            'dwo_id' => ['nullable', 'ulid', 'exists:delivery_work_orders,id'],
            'swo_id' => ['nullable', 'ulid', 'exists:service_work_orders,id'],
        ]);

        $data['vehicle_id'] = $vehicleModel->id;
        $data['completed_at'] ??= now();
        $data['checklist_items'] ??= [];
        $data['created_by'] = $request->user()?->id;

        $record = VehicleChecklist::create($data);

        return response()->json(['data' => VehicleChecklistResource::make($record)], 201);
    }

    public function update(Request $request, string $checklist): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');
        $record = VehicleChecklist::findOrFail($checklist);

        $data = $request->validate([
            'checklist_template_id' => ['nullable', 'ulid', 'exists:checklist_templates,id'],
            'checklist_type' => ['sometimes', 'string', 'max:50'],
            'title' => ['sometimes', 'string', 'max:255'],
            'completed_at' => ['nullable', 'date'],
            'completed_by_employee_id' => ['nullable', 'ulid', 'exists:users,id'],
            'driver_id' => ['nullable', 'ulid', 'exists:users,id'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'checklist_items' => ['nullable', 'array'],
            'overall_status' => ['sometimes', 'in:passed,failed,passed_with_issues,pending'],
            'items_passed' => ['nullable', 'integer', 'min:0'],
            'items_failed' => ['nullable', 'integer', 'min:0'],
            'items_total' => ['nullable', 'integer', 'min:0'],
            'has_issues' => ['nullable', 'boolean'],
            'issues_found' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
            'requires_maintenance' => ['nullable', 'boolean'],
            'maintenance_record_id' => ['nullable', 'ulid', 'exists:vehicle_maintenance_records,id'],
        ]);

        $data['updated_by'] = $request->user()?->id;
        $record->update($data);

        return response()->json(['data' => VehicleChecklistResource::make($record->fresh())]);
    }

    public function destroy(string $checklist): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.archive');
        $record = VehicleChecklist::findOrFail($checklist);
        $record->delete();

        return response()->json(['message' => 'Vehicle checklist deleted']);
    }
}
