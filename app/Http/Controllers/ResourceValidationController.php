<?php

namespace App\Http\Controllers;

use App\Services\ResourceValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceValidationController extends Controller
{
    public function __construct(
        protected ResourceValidationService $validationService
    ) {}

    /**
     * Validate DWO resources
     */
    public function validateDwo(Request $request): JsonResponse
    {
        // Require authentication for resource validation
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
            'approved_driver_id' => 'nullable|ulid|exists:hr_approved_drivers,id',
            'mobile_warehouse_id' => 'nullable|ulid|exists:warehouse_sites,id',
            'delivery_provider_id' => 'nullable|ulid|exists:delivery_service_providers,id',
            'scheduled_date' => 'nullable|date',
        ]);

        $scheduledDate = isset($validated['scheduled_date'])
            ? \Carbon\Carbon::parse($validated['scheduled_date'])
            : null;

        $result = $this->validationService->validateDwoResources($validated, $scheduledDate);

        return response()->json($result);
    }

    /**
     * Validate SWO resources
     */
    public function validateSwo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
            'approved_driver_id' => 'nullable|ulid|exists:hr_approved_drivers,id',
            'workshop_unit_id' => 'nullable|ulid|exists:workshop_units,id',
            'mobile_warehouse_id' => 'nullable|ulid|exists:warehouse_sites,id',
            'scheduled_date' => 'nullable|date',
        ]);

        $scheduledDate = isset($validated['scheduled_date'])
            ? \Carbon\Carbon::parse($validated['scheduled_date'])
            : null;

        $result = $this->validationService->validateSwoResources($validated, $scheduledDate);

        return response()->json($result);
    }

    /**
     * Get available drivers for a vehicle
     */
    public function availableDriversForVehicle(Request $request, string $vehicleId): JsonResponse
    {
        $date = $request->has('date')
            ? \Carbon\Carbon::parse($request->date)
            : null;

        $drivers = $this->validationService->getAvailableDriversForVehicle($vehicleId, $date);

        return response()->json($drivers);
    }

    /**
     * Get available vehicles for a driver
     */
    public function availableVehiclesForDriver(Request $request, string $driverId): JsonResponse
    {
        $date = $request->has('date')
            ? \Carbon\Carbon::parse($request->date)
            : null;

        $vehicles = $this->validationService->getAvailableVehiclesForDriver($driverId, $date);

        return response()->json($vehicles);
    }

    /**
     * Validate vehicle availability
     */
    public function validateVehicle(Request $request, string $vehicleId): JsonResponse
    {
        $date = $request->has('date')
            ? \Carbon\Carbon::parse($request->date)
            : now();

        $errors = $this->validationService->validateVehicleAvailability($vehicleId, $date);

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * Validate driver authorization
     */
    public function validateDriver(Request $request, string $driverId): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
            'date' => 'nullable|date',
        ]);

        $date = isset($validated['date'])
            ? \Carbon\Carbon::parse($validated['date'])
            : now();

        $errors = $this->validationService->validateDriverAuthorization(
            $driverId,
            $validated['vehicle_id'] ?? null,
            $date
        );

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * Validate vehicle-driver compatibility
     */
    public function validateCompatibility(string $vehicleId, string $driverId): JsonResponse
    {
        $errors = $this->validationService->validateVehicleDriverCompatibility($vehicleId, $driverId);

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }
}
