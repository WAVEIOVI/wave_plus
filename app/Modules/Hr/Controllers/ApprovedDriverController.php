<?php

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Resources\ApprovedDriverResource;
use App\Modules\Hr\Services\DriverAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApprovedDriverController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function __construct(
        protected DriverAuthorizationService $driverService
    ) {}

    /**
     * Get all approved drivers
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $query = ApprovedDriver::query()
            ->with(['employee.user']);

        // Filters
        if ($request->has('authorization_status')) {
            $query->where('authorization_status', $request->authorization_status);
        }

        if ($request->filled('driver_level')) {
            $query->where('driver_level', $request->input('driver_level'));
        }

        if ($request->filled('license_category')) {
            $query->where('license_category', $request->input('license_category'));
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->boolean('suspended_only')) {
            $query->suspended();
        }

        if ($request->boolean('expired_only')) {
            $query->expired();
        }

        if ($request->boolean('expiring_soon')) {
            $days = $request->input('days_ahead', 30);
            $query->expiringSoon($days);
        }

        if ($request->has('vehicle_type')) {
            $query->canDriveVehicleType($request->vehicle_type);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('driver_authorization_number', 'like', "%{$search}%")
                    ->orWhere('license_number', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($eq) use ($search) {
                        $eq->where('first_name->'.app()->getLocale(), 'like', "%{$search}%")
                            ->orWhere('last_name->'.app()->getLocale(), 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $allowedSorts = ['created_at', 'authorization_date', 'expiry_date', 'license_number', 'driver_score', 'authorization_status', 'driver_level'];
        $query->orderBy(in_array($sortBy, $allowedSorts, true) ? $sortBy : 'created_at', $request->input('sort_order') === 'asc' ? 'asc' : 'desc');

        if ($request->boolean('paginate', true)) {
            $paginated = $query->paginate($request->input('per_page', 15));

            return response()->json([
                'data' => ApprovedDriverResource::collection($paginated->items()),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ]);
        }

        return response()->json([
            'data' => ApprovedDriverResource::collection($query->get()),
        ]);
    }

    /**
     * Get single approved driver
     */
    public function show(string $id): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $driver = ApprovedDriver::with([
            'employee.user',
            'employee.department',
            'employee.position',
            'incidents.vehicle',
            'activityLog' => fn ($q) => $q->latest()->limit(20),
        ])->findOrFail($id);

        return response()->json(['data' => ApprovedDriverResource::make($driver)]);
    }

    /**
     * Approve a driver
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $validated = $request->validate([
            'employee_id' => 'required|ulid|exists:hr_employees,id',
            'authorization_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:authorization_date',
            'review_date' => 'nullable|date',
            'license_number' => 'required|string|max:100',
            'license_category' => 'nullable|string|max:50',
            'license_categories' => 'nullable|array',
            'license_issue_date' => 'nullable|date',
            'license_expiry_date' => 'nullable|date',
            'license_issuer' => 'nullable|string|max:100',
            'permitted_vehicle_types' => 'nullable|array',
            'permitted_vehicle_classes' => 'nullable|array',
            'can_drive_rental' => 'nullable|boolean',
            'can_drive_client_vehicles' => 'nullable|boolean',
            'restrictions' => 'nullable|array',
            'max_passengers' => 'nullable|integer|min:1',
            'max_cargo_kg' => 'nullable|integer|min:0',
            'restriction_notes' => 'nullable|string',
            'driver_level' => 'nullable|in:trainee,junior,standard,senior,expert',
            'notes' => 'nullable|string',
            'documents' => 'nullable|array',
        ]);

        $driver = $this->driverService->approveDriver(
            $validated['employee_id'],
            $validated,
            Auth::id()
        );

        return response()->json(['data' => ApprovedDriverResource::make($driver->load(['employee.department', 'employee.position']))], 201);
    }

    /**
     * Update driver authorization
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $driver = ApprovedDriver::findOrFail($id);

        $validated = $request->validate([
            'expiry_date' => 'nullable|date',
            'review_date' => 'nullable|date',
            'license_expiry_date' => 'nullable|date',
            'permitted_vehicle_types' => 'nullable|array',
            'permitted_vehicle_classes' => 'nullable|array',
            'can_drive_rental' => 'nullable|boolean',
            'can_drive_client_vehicles' => 'nullable|boolean',
            'restrictions' => 'nullable|array',
            'max_passengers' => 'nullable|integer|min:1',
            'max_cargo_kg' => 'nullable|integer|min:0',
            'restriction_notes' => 'nullable|string',
            'notes' => 'nullable|string',
            'documents' => 'nullable|array',
        ]);

        $validated['updated_by'] = Auth::id();

        $driver->update($validated);

        return response()->json(['data' => ApprovedDriverResource::make($driver->fresh(['employee.department', 'employee.position']))]);
    }

    /**
     * Suspend driver
     */
    public function suspend(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $validated = $request->validate([
            'suspended_until' => 'required|date|after:today',
            'suspension_reason' => 'required|string',
        ]);

        $driver = $this->driverService->suspendDriver(
            $id,
            \Carbon\Carbon::parse($validated['suspended_until']),
            $validated['suspension_reason'],
            Auth::id()
        );

        return response()->json(['data' => ApprovedDriverResource::make($driver->load(['employee.department', 'employee.position']))]);
    }

    /**
     * Reinstate driver
     */
    public function reinstate(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $driver = $this->driverService->reinstateDriver(
            $id,
            $validated['notes'] ?? null,
            Auth::id()
        );

        return response()->json(['data' => ApprovedDriverResource::make($driver->load(['employee.department', 'employee.position']))]);
    }

    public function revoke(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $driver = $this->driverService->revokeDriver($id, $data['reason'], Auth::id());

        return response()->json(['data' => ApprovedDriverResource::make($driver->load(['employee.department', 'employee.position']))]);
    }

    /**
     * Renew authorization
     */
    public function renew(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $validated = $request->validate([
            'expiry_date' => 'nullable|date|after:today',
            'review_date' => 'nullable|date',
            'license_expiry_date' => 'nullable|date',
        ]);

        $driver = $this->driverService->renewAuthorization(
            $id,
            $validated,
            Auth::id()
        );

        return response()->json(['data' => ApprovedDriverResource::make($driver->load(['employee.department', 'employee.position']))]);
    }

    /**
     * Record incident
     */
    public function recordIncident(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $validated = $request->validate([
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
            'incident_type' => 'required|in:accident,traffic_violation,parking_violation,speeding,equipment_damage,safety_violation,policy_violation,near_miss,customer_complaint,other',
            'severity' => 'required|in:minor,moderate,major,critical',
            'incident_date' => 'required|date',
            'location' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'property_damage' => 'nullable|boolean',
            'vehicle_damage' => 'nullable|boolean',
            'personal_injury' => 'nullable|boolean',
            'third_party_involved' => 'nullable|boolean',
            'damage_description' => 'nullable|string',
            'estimated_cost' => 'nullable|numeric|min:0',
            'police_report_filed' => 'nullable|boolean',
            'police_report_number' => 'nullable|string|max:100',
            'driver_at_fault' => 'nullable|in:yes,no,partial,under_investigation,unknown',
            'driver_statement' => 'nullable|string',
            'witnesses' => 'nullable|array',
            'documents' => 'nullable|array',
        ]);

        $incident = $this->driverService->recordIncident(
            $id,
            $validated,
            Auth::id()
        );

        return response()->json($incident->load('driver', 'vehicle'), 201);
    }

    /**
     * Get driver activity log
     */
    public function activityLog(string $id): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $driver = ApprovedDriver::findOrFail($id);

        $activities = $driver->activityLog()
            ->with('recordedBy')
            ->latest('event_date')
            ->paginate(50);

        return response()->json($activities);
    }

    /**
     * Calculate driver score
     */
    public function calculateScore(string $id): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $score = $this->driverService->calculateDriverScore($id);

        return response()->json([
            'driver_id' => $id,
            'score' => $score,
        ]);
    }

    /**
     * Adjust driver score manually
     */
    public function adjustScore(Request $request, string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');

        $validated = $request->validate([
            'score_change' => 'required|numeric|min:-100|max:100',
        ]);

        $driver = $this->driverService->adjustDriverScore(
            $id,
            $validated['score_change'],
            Auth::id()
        );

        return response()->json(['data' => ApprovedDriverResource::make($driver->load(['employee.department', 'employee.position']))]);
    }

    /**
     * Get drivers requiring license renewal
     */
    public function requiresLicenseRenewal(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $daysAhead = $request->input('days_ahead', 30);

        $drivers = $this->driverService->getDriversRequiringLicenseRenewal($daysAhead);

        return response()->json($drivers);
    }

    /**
     * Get drivers requiring review
     */
    public function requiresReview(Request $request): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $daysAhead = $request->input('days_ahead', 30);

        $drivers = $this->driverService->getDriversRequiringReview($daysAhead);

        return response()->json($drivers);
    }

    /**
     * Delete driver authorization
     */
    public function destroy(string $id): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.archive');
        $driver = ApprovedDriver::findOrFail($id);
        $driver->delete();

        return response()->json(['message' => 'Driver authorization deleted successfully']);
    }
}
