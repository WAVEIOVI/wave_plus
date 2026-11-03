<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\VehicleAssignment;
use App\Modules\Hr\Resources\VehicleAssignmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetAssignmentController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function index(Request $request, ?string $vehicle = null): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $query = VehicleAssignment::query()->with(['vehicle', 'responsibleEmployee', 'site']);

        if ($vehicle) {
            $query->where('vehicle_id', $vehicle);
        } elseif ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->string('vehicle_id')->toString());
        }

        if ($request->filled('employee_id')) {
            $query->where('responsible_employee_id', $request->string('employee_id')->toString());
        }
        if ($request->boolean('current_only')) {
            $query->current();
        }

        $records = $query->orderByDesc('start_date')->paginate(min(max((int) $request->input('per_page', 15), 1), 100));

        return response()->json([
            'data' => VehicleAssignmentResource::collection($records->items()),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function show(string $assignment): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $record = VehicleAssignment::with(['vehicle', 'responsibleEmployee', 'site'])->findOrFail($assignment);

        return response()->json(['data' => VehicleAssignmentResource::make($record)]);
    }
}
