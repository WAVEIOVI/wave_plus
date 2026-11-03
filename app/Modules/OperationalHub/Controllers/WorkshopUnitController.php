<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OperationalHub\Models\WorkshopUnit;
use App\Modules\OperationalHub\Resources\WorkshopUnitResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkshopUnitController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WorkshopUnit::class);

        $query = WorkshopUnit::query()
            ->with(['vehicle'])
            ->withCount([
                'swos',
                'missions',
                'receptions',
                'activeReceptions as active_receptions_count',
            ]);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Backwards-compatible boolean filter used by the frontend selectors.
        if ($request->has('is_active')) {
            $request->boolean('is_active')
                ? $query->where('status', 'active')
                : $query->where('status', '!=', 'active');
        }

        $q = $request->input('q') ?? $request->input('search');
        if (! empty($q)) {
            $query->where(function ($sub) use ($q) {
                $sub->where('code', 'LIKE', "%{$q}%")
                    ->orWhere('location', 'LIKE', "%{$q}%")
                    ->orWhere('name->en', 'LIKE', "%{$q}%")
                    ->orWhere('name->fr', 'LIKE', "%{$q}%");
            });
        }

        $perPage = (int) $request->input('itemsPerPage', $request->input('per_page', 15));
        $units = $query->latest()->paginate($perPage);

        return WorkshopUnitResource::collection($units);
    }

    public function show(WorkshopUnit $workshopUnit): JsonResponse
    {
        $this->authorize('view', $workshopUnit);

        $workshopUnit->load(['vehicle']);
        $workshopUnit->loadCount([
            'swos',
            'missions',
            'receptions',
            'activeReceptions as active_receptions_count',
        ]);

        return response()->json([
            'data' => new WorkshopUnitResource($workshopUnit),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', WorkshopUnit::class);

        $validated = $request->validate([
            'code' => 'required|string|unique:workshop_units,code',
            'name' => 'required|array',
            'description' => 'nullable|array',
            'type' => 'required|in:fixed,mobile',
            'status' => 'nullable|in:active,inactive,maintenance',
            'location' => 'nullable|string',
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
            'capacity' => 'nullable|integer|min:1|max:999',
            'capacity_notes' => 'nullable|string',
        ]);

        $unit = WorkshopUnit::create(array_merge($validated, [
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]));

        return response()->json([
            'message' => 'Workshop unit created successfully.',
            'data' => new WorkshopUnitResource($unit),
        ], 201);
    }

    public function update(Request $request, WorkshopUnit $workshopUnit): JsonResponse
    {
        $this->authorize('update', $workshopUnit);

        $validated = $request->validate([
            'code' => 'sometimes|string|unique:workshop_units,code,'.$workshopUnit->id,
            'name' => 'sometimes|array',
            'description' => 'nullable|array',
            'type' => 'sometimes|in:fixed,mobile',
            'status' => 'sometimes|in:active,inactive,maintenance',
            'location' => 'nullable|string',
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
            'capacity' => 'nullable|integer|min:1|max:999',
            'capacity_notes' => 'nullable|string',
        ]);

        $workshopUnit->update(array_merge($validated, [
            'updated_by' => $request->user()?->id,
        ]));

        return response()->json([
            'message' => 'Workshop unit updated successfully.',
            'data' => new WorkshopUnitResource($workshopUnit->fresh(['vehicle'])),
        ]);
    }

    public function destroy(WorkshopUnit $workshopUnit): JsonResponse
    {
        $this->authorize('delete', $workshopUnit);

        $workshopUnit->delete();

        return response()->json([
            'message' => 'Workshop unit deleted successfully.',
        ]);
    }
}
