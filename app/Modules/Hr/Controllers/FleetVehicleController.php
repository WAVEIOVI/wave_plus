<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Resources\VehicleResource;
use App\Modules\Hr\Services\FleetManagementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Fleet master-data API. Logistics and Operations consume these records. */
class FleetVehicleController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function __construct(private readonly FleetManagementService $fleet) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $query = Vehicle::query()->with([
            'currentAssignment.responsibleEmployee', 'currentSite', 'defaultDriver',
        ]);

        foreach (['status', 'ownership_type', 'type'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->string($filter)->toString());
            }
        }
        if ($request->boolean('requires_maintenance')) {
            $query->requiresMaintenance();
        }
        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q->where('fleet_code', 'like', "%{$search}%")
                ->orWhere('plate_number', 'like', "%{$search}%")
                ->orWhere('label', 'like', "%{$search}%")
                ->orWhere('make', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%"));
        }

        $sort = $request->input('sort_by', 'created_at');
        $allowed = ['fleet_code', 'plate_number', 'label', 'make', 'model', 'type', 'ownership_type', 'status', 'odometer_km', 'created_at'];
        $query->orderBy(in_array($sort, $allowed, true) ? $sort : 'created_at', $request->input('sort_order') === 'asc' ? 'asc' : 'desc');

        $vehicles = $query->paginate(min(max((int) $request->input('per_page', 15), 1), 100));

        return response()->json([
            'data' => VehicleResource::collection($vehicles->items()),
            'meta' => [
                'current_page' => $vehicles->currentPage(),
                'per_page' => $vehicles->perPage(),
                'total' => $vehicles->total(),
                'last_page' => $vehicles->lastPage(),
            ],
        ]);
    }

    public function show(string $vehicle): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $vehicleModel = Vehicle::with([
            'currentAssignment.responsibleEmployee', 'currentSite', 'defaultDriver',
            'assignments.responsibleEmployee', 'documents', 'maintenanceRecords', 'fuelRecords',
            'checklists', 'activityLog', 'rentalProvider', 'client', 'mobileWarehouses', 'workshopUnits',
        ])->findOrFail($vehicle);

        return response()->json(['data' => VehicleResource::make($vehicleModel)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $data = $request->validate($this->vehicleRules());
        $data['created_by'] = $request->user()?->id;
        $data['status'] ??= Vehicle::STATUS_AVAILABLE;
        $vehicleModel = Vehicle::create($data);

        return response()->json(['data' => VehicleResource::make($vehicleModel)], 201);
    }

    public function update(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');
        $record = Vehicle::findOrFail($vehicle);
        $data = $request->validate($this->vehicleRules($record->id, false));
        $data['updated_by'] = $request->user()?->id;
        $record->update($data);

        return response()->json(['data' => VehicleResource::make($record->fresh())]);
    }

    public function destroy(string $vehicle): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.archive');
        $record = Vehicle::findOrFail($vehicle);
        abort_if($record->assignments()->exists(), 422, 'A vehicle with assignment history cannot be deleted. Retire it instead.');
        $record->delete();

        return response()->json(['message' => 'Vehicle deleted']);
    }

    public function assign(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $data = $request->validate([
            'responsible_employee_id' => ['nullable', 'ulid', 'exists:users,id'],
            'custody_type' => ['required', 'in:company_site,employee_home,mission,maintenance,rental,client,storage,other'],
            'site_id' => ['nullable', 'ulid', 'exists:warehouse_sites,id'], 'location_notes' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'], 'odometer_at_start' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json(['data' => $this->fleet->assignVehicle($vehicle, $data, $request->user()?->id)->load('responsibleEmployee')]);
    }

    public function returnVehicle(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');
        $record = Vehicle::findOrFail($vehicle);
        $currentAssignment = $record->currentAssignment;
        abort_unless($currentAssignment instanceof \App\Modules\Hr\Models\VehicleAssignment, 422, 'Vehicle has no active assignment.');
        $data = $request->validate(['end_date' => ['nullable', 'date'], 'odometer_at_end' => ['nullable', 'integer', 'min:0'], 'vehicle_status' => ['nullable', 'in:available,maintenance,out_of_service']]);

        return response()->json(['data' => $this->fleet->endAssignment($currentAssignment->id, $request->user()?->id, $data)]);
    }

    public function updateStatus(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $statuses = implode(',', Vehicle::STATUSES);
        $data = $request->validate(['status' => ['required', "in:{$statuses}"], 'reason' => ['nullable', 'string']]);

        return response()->json(['data' => VehicleResource::make($this->fleet->updateStatus($vehicle, $data['status'], $data['reason'] ?? null, $request->user()?->id))]);
    }

    public function updateOdometer(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $data = $request->validate(['odometer_km' => ['required', 'integer', 'min:0']]);

        return response()->json(['data' => VehicleResource::make($this->fleet->updateOdometer($vehicle, $data['odometer_km'], $request->user()?->id))]);
    }

    public function startRental(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $data = $request->validate(['rental_provider_id' => ['required', 'ulid', 'exists:service_providers,id'], 'rental_end_date' => ['required', 'date'], 'rental_start_date' => ['nullable', 'date'], 'rental_contract_reference' => ['nullable', 'string', 'max:100'], 'rental_daily_rate' => ['nullable', 'numeric', 'min:0'], 'rental_currency' => ['nullable', 'string', 'size:3']]);

        return response()->json(['data' => VehicleResource::make($this->fleet->startRentalUsage($vehicle, $data, $request->user()?->id))]);
    }

    public function endRental(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $data = $request->validate(['rental_return_date' => ['nullable', 'date']]);

        return response()->json(['data' => VehicleResource::make($this->fleet->endRentalUsage($vehicle, $data, $request->user()?->id))]);
    }

    public function startClientUsage(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $data = $request->validate(['client_id' => ['required', 'ulid', 'exists:clients,id'], 'client_supplies_driver' => ['nullable', 'boolean'], 'client_usage_start' => ['nullable', 'date'], 'client_usage_end' => ['nullable', 'date']]);

        return response()->json(['data' => VehicleResource::make($this->fleet->startClientVehicleUsage($vehicle, $data, $request->user()?->id))]);
    }

    public function endClientUsage(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        return response()->json(['data' => VehicleResource::make($this->fleet->endClientVehicleUsage($vehicle, $request->user()?->id))]);
    }

    public function activityLog(string $vehicle): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $logs = Vehicle::findOrFail($vehicle)->activityLog()->with('actorEmployee')->paginate(50);

        return response()->json([
            'data' => \App\Modules\Hr\Resources\VehicleActivityLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        return response()->json(['data' => [
            'maintenance' => $this->fleet->getVehiclesRequiringMaintenance((int) $request->input('days_ahead', 7)),
            'documents' => $this->fleet->getVehiclesWithExpiringDocuments((int) $request->input('days_ahead', 30)),
        ]]);
    }

    public function utilization(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $data = $request->validate(['start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after:start_date']]);

        return response()->json(['data' => $this->fleet->calculateVehicleUtilization($vehicle, Carbon::parse($data['start_date']), Carbon::parse($data['end_date']))]);
    }

    private function vehicleRules(?string $id = null, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $statuses = implode(',', Vehicle::STATUSES);

        return [
            // fleet_code is auto-generated by the model when omitted.
            'fleet_code' => ['nullable', 'string', 'max:50', 'unique:vehicles,fleet_code'.($id ? ",{$id}" : '')],
            'plate_number' => [$required, 'string', 'max:50', 'unique:vehicles,plate_number'.($id ? ",{$id}" : '')],
            'label' => [$required, 'string', 'max:255'], 'make' => ['nullable', 'string', 'max:100'], 'model' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)], 'vin' => ['nullable', 'string', 'max:50'],
            'registration_number' => ['nullable', 'string', 'max:50'], 'registration_date' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'max:50'],
            'type' => [$required, 'string', 'max:50'], 'vehicle_class' => ['nullable', 'string', 'max:50'],
            'capacity_kg' => ['nullable', 'numeric', 'min:0'], 'capacity_m3' => ['nullable', 'numeric', 'min:0'], 'seats' => ['nullable', 'integer', 'min:1'],
            'fuel_type' => ['nullable', 'in:petrol,diesel,electric,hybrid,lpg,cng,hydrogen'], 'ownership_type' => [$required, 'in:company,rental,client'],
            'fuel_tank_capacity' => ['nullable', 'numeric', 'min:0'], 'battery_capacity_kwh' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', "in:{$statuses}"],
            'custody_type' => ['nullable', 'in:company_site,employee_home,mission,maintenance,rental,client,storage,other'],
            'current_site_id' => ['nullable', 'ulid', 'exists:warehouse_sites,id'],
            'current_location_notes' => ['nullable', 'string'],
            'odometer_km' => ['nullable', 'integer', 'min:0'], 'next_service_date' => ['nullable', 'date'], 'next_inspection_date' => ['nullable', 'date'],
            'insurance_expiry_date' => ['nullable', 'date'], 'registration_expiry_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string'],
            'default_driver_id' => ['nullable', 'ulid', 'exists:users,id'],
            'last_inspection_date' => ['nullable', 'date'], 'last_service_date' => ['nullable', 'date'],
        ];
    }
}
