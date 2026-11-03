<?php

namespace App\Modules\Logistics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logistics\Models\DeliveryServiceProvider;
use App\Modules\Logistics\Services\DeliveryProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeliveryServiceProviderController extends Controller
{
    public function __construct(
        protected DeliveryProviderService $providerService
    ) {}

    /**
     * Get all delivery service providers
     */
    public function index(Request $request): JsonResponse
    {
        $query = DeliveryServiceProvider::query()
            ->with(['serviceProvider']);

        // Filters
        if ($request->has('operational_status')) {
            $query->where('operational_status', $request->operational_status);
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->boolean('accepting_orders_only')) {
            $query->acceptingOrders();
        }

        if ($request->has('service_area')) {
            $query->servesArea($request->service_area);
        }

        if ($request->boolean('high_performance_only')) {
            $minScore = $request->input('min_score', 80.0);
            $query->highPerformance($minScore);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('provider_code', 'like', "%{$search}%")
                    ->orWhere('operations_contact_name', 'like', "%{$search}%")
                    ->orWhereHas('serviceProvider', function ($sp) use ($search) {
                        $sp->where('legal_name', 'like', "%{$search}%")
                            ->orWhere('service_provider_id', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $providers = $request->boolean('paginate', true)
            ? $query->paginate($request->input('per_page', 15))
            : $query->get();

        return response()->json($providers);
    }

    /**
     * Get single delivery service provider
     */
    public function show(string $id): JsonResponse
    {
        $provider = DeliveryServiceProvider::with([
            'serviceProvider',
            'activityLog' => fn ($q) => $q->latest()->limit(20),
            'dwos' => fn ($q) => $q->latest()->limit(10),
        ])->findOrFail($id);

        return response()->json($provider);
    }

    /**
     * Create new delivery service provider
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_provider_id' => 'required|ulid|exists:service_providers,id|unique:delivery_service_providers,service_provider_id',
            'operational_status' => 'nullable|in:active,inactive,suspended,under_review,probation',
            'service_areas' => 'nullable|array',
            'delivery_modes' => 'nullable|array',
            'vehicle_types' => 'nullable|array',
            'can_handle_fragile' => 'nullable|boolean',
            'can_handle_hazmat' => 'nullable|boolean',
            'can_handle_refrigerated' => 'nullable|boolean',
            'max_weight_kg' => 'nullable|numeric|min:0',
            'max_volume_m3' => 'nullable|numeric|min:0',
            'contract_reference' => 'nullable|string|max:100',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after:contract_start_date',
            'pricing_model' => 'nullable|in:per_delivery,per_km,per_weight,monthly_flat,tiered,custom',
            'rate_card' => 'nullable|array',
            'currency' => 'nullable|string|size:3',
            'daily_capacity' => 'nullable|integer|min:0',
            'accepts_new_orders' => 'nullable|boolean',
            'operations_contact_name' => 'nullable|string|max:200',
            'operations_phone' => 'nullable|string|max:50',
            'operations_email' => 'nullable|email|max:200',
            'dispatch_phone' => 'nullable|string|max:50',
            'emergency_phone' => 'nullable|string|max:50',
            'insurance_details' => 'nullable|array',
            'certifications' => 'nullable|array',
            'sla_terms' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $validated['created_by'] = Auth::id();

        $provider = DeliveryServiceProvider::create($validated);

        return response()->json($provider->load('serviceProvider'), 201);
    }

    /**
     * Update delivery service provider
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $provider = DeliveryServiceProvider::findOrFail($id);

        $validated = $request->validate([
            'service_areas' => 'nullable|array',
            'delivery_modes' => 'nullable|array',
            'vehicle_types' => 'nullable|array',
            'can_handle_fragile' => 'nullable|boolean',
            'can_handle_hazmat' => 'nullable|boolean',
            'can_handle_refrigerated' => 'nullable|boolean',
            'max_weight_kg' => 'nullable|numeric|min:0',
            'max_volume_m3' => 'nullable|numeric|min:0',
            'contract_reference' => 'nullable|string|max:100',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'pricing_model' => 'nullable|in:per_delivery,per_km,per_weight,monthly_flat,tiered,custom',
            'rate_card' => 'nullable|array',
            'operations_contact_name' => 'nullable|string|max:200',
            'operations_phone' => 'nullable|string|max:50',
            'operations_email' => 'nullable|email|max:200',
            'dispatch_phone' => 'nullable|string|max:50',
            'emergency_phone' => 'nullable|string|max:50',
            'insurance_details' => 'nullable|array',
            'certifications' => 'nullable|array',
            'sla_terms' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $validated['updated_by'] = Auth::id();

        $provider->update($validated);

        return response()->json($provider->fresh());
    }

    /**
     * Update operational status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'operational_status' => 'required|in:active,inactive,suspended,under_review,probation',
            'reason' => 'nullable|string',
        ]);

        $provider = $this->providerService->updateOperationalStatus(
            $id,
            $validated['operational_status'],
            $validated['reason'] ?? null,
            Auth::id()
        );

        return response()->json($provider);
    }

    /**
     * Update capacity settings
     */
    public function updateCapacity(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'daily_capacity' => 'required|integer|min:0',
            'accepts_new_orders' => 'required|boolean',
        ]);

        $provider = $this->providerService->updateCapacity(
            $id,
            $validated['daily_capacity'],
            $validated['accepts_new_orders'],
            Auth::id()
        );

        return response()->json($provider);
    }

    /**
     * Reset daily load
     */
    public function resetDailyLoad(string $id): JsonResponse
    {
        $provider = $this->providerService->resetDailyLoad($id, Auth::id());

        return response()->json($provider);
    }

    /**
     * Record incident
     */
    public function recordIncident(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'incident_description' => 'required|string',
            'dwo_id' => 'nullable|ulid|exists:delivery_work_orders,id',
        ]);

        $activity = $this->providerService->recordIncident(
            $id,
            $validated['incident_description'],
            $validated['dwo_id'] ?? null,
            Auth::id()
        );

        return response()->json($activity, 201);
    }

    /**
     * Get provider activity log
     */
    public function activityLog(string $id): JsonResponse
    {
        $provider = DeliveryServiceProvider::findOrFail($id);

        $activities = $provider->activityLog()
            ->with('recordedBy')
            ->latest('event_date')
            ->paginate(50);

        return response()->json($activities);
    }

    /**
     * Get performance report
     */
    public function performanceReport(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $report = $this->providerService->getPerformanceReport(
            $id,
            \Carbon\Carbon::parse($validated['start_date']),
            \Carbon\Carbon::parse($validated['end_date'])
        );

        return response()->json($report);
    }

    /**
     * Get available providers for delivery
     */
    public function available(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city' => 'required|string',
            'weight' => 'nullable|numeric|min:0',
            'volume' => 'nullable|numeric|min:0',
            'fragile' => 'nullable|boolean',
            'hazmat' => 'nullable|boolean',
            'refrigerated' => 'nullable|boolean',
        ]);

        $requirements = [
            'fragile' => $validated['fragile'] ?? false,
            'hazmat' => $validated['hazmat'] ?? false,
            'refrigerated' => $validated['refrigerated'] ?? false,
        ];

        $providers = $this->providerService->getAvailableProviders(
            $validated['city'],
            $validated['weight'] ?? null,
            $validated['volume'] ?? null,
            $requirements
        );

        return response()->json($providers);
    }

    /**
     * Delete delivery service provider
     */
    public function destroy(string $id): JsonResponse
    {
        $provider = DeliveryServiceProvider::findOrFail($id);
        $provider->delete();

        return response()->json(['message' => 'Delivery service provider deleted successfully']);
    }
}
