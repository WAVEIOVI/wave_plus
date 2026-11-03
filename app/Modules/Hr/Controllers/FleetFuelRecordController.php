<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleFuelRecord;
use App\Modules\Hr\Resources\VehicleFuelRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetFuelRecordController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function index(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $vehicleModel = Vehicle::findOrFail($vehicle);
        $query = $vehicleModel->fuelRecords()->with(['filledByEmployee', 'driver']);

        if ($request->filled('fuel_type')) {
            $query->where('fuel_type', $request->string('fuel_type')->toString());
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('fill_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        $records = $query->orderByDesc('fill_date')->paginate(min(max((int) $request->input('per_page', 15), 1), 100));

        return response()->json([
            'data' => VehicleFuelRecordResource::collection($records->items()),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function show(string $fuelRecord): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $record = VehicleFuelRecord::with(['filledByEmployee', 'driver'])->findOrFail($fuelRecord);

        return response()->json(['data' => VehicleFuelRecordResource::make($record)]);
    }

    public function store(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');
        $vehicleModel = Vehicle::findOrFail($vehicle);

        $data = $request->validate([
            'fill_date' => ['required', 'date'],
            'fill_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'fuel_type' => ['nullable', 'in:petrol,diesel,electric,hybrid,lpg,cng,hydrogen'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['nullable', 'string', 'max:20'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'total_cost' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'station' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'card_last_four' => ['nullable', 'string', 'max:10'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'filled_by_employee_id' => ['nullable', 'ulid', 'exists:users,id'],
            'driver_id' => ['nullable', 'ulid', 'exists:users,id'],
            'is_full_tank' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['vehicle_id'] = $vehicleModel->id;
        $data['fuel_type'] ??= $vehicleModel->fuel_type ?? 'diesel';
        $data['unit'] ??= 'L';
        $data['currency'] ??= 'SAR';
        $data['created_by'] = $request->user()?->id;

        $record = VehicleFuelRecord::create($data);

        // Auto update vehicle odometer if odometer_km is greater
        if (! empty($data['odometer_km']) && $data['odometer_km'] > ($vehicleModel->odometer_km ?? 0)) {
            $vehicleModel->update([
                'odometer_km' => $data['odometer_km'],
                'odometer_last_updated' => $data['fill_date'],
            ]);
        }

        return response()->json(['data' => VehicleFuelRecordResource::make($record)], 201);
    }

    public function update(Request $request, string $fuelRecord): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');
        $record = VehicleFuelRecord::findOrFail($fuelRecord);

        $data = $request->validate([
            'fill_date' => ['sometimes', 'date'],
            'fill_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'fuel_type' => ['nullable', 'in:petrol,diesel,electric,hybrid,lpg,cng,hydrogen'],
            'quantity' => ['sometimes', 'numeric', 'min:0.001'],
            'unit' => ['nullable', 'string', 'max:20'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'total_cost' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'station' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'card_last_four' => ['nullable', 'string', 'max:10'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'filled_by_employee_id' => ['nullable', 'ulid', 'exists:users,id'],
            'driver_id' => ['nullable', 'ulid', 'exists:users,id'],
            'is_full_tank' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['updated_by'] = $request->user()?->id;
        $record->update($data);

        return response()->json(['data' => VehicleFuelRecordResource::make($record->fresh())]);
    }

    public function destroy(string $fuelRecord): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.archive');
        $record = VehicleFuelRecord::findOrFail($fuelRecord);
        $record->delete();

        return response()->json(['message' => 'Vehicle fuel record deleted']);
    }
}
