<?php

namespace App\Modules\ClientAssets\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\ClientAssets\Controllers\Concerns\AuthorizesClientAssetsRequests;
use App\Modules\ClientAssets\Enums\WorkwearItemStatus;
use App\Modules\ClientAssets\Models\WorkwearFabric;
use App\Modules\ClientAssets\Models\WorkwearItemOptionValue;
use App\Modules\ClientAssets\Models\WorkwearModel;
use App\Modules\ClientAssets\Requests\StoreWorkwearModelRequest;
use App\Modules\ClientAssets\Requests\UpdateWorkwearModelRequest;
use App\Modules\ClientAssets\Resources\WorkwearModelOptionResource;
use App\Modules\ClientAssets\Resources\WorkwearModelOptionValueResource;
use App\Modules\ClientAssets\Resources\WorkwearModelResource;
use App\Modules\ClientAssets\Resources\WorkwearModelVersionResource;
use App\Modules\ClientAssets\Services\ClientAssetsQueryService;
use App\Modules\ClientAssets\Services\WorkwearService;
use App\Modules\Shared\Services\MediaValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkwearModelController extends Controller
{
    use AuthorizesClientAssetsRequests;

    private const ALLOWED_SORT_COLUMNS = ['created_at', 'name', 'model_ref', 'status'];

    public function __construct(
        private readonly ClientAssetsQueryService $queryService,
        private readonly WorkwearService $workwearService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkwearModel::class);

        $query = WorkwearModel::with([
            'client',
            'supplier',
            'workwearFabric',
            'catalogItem.category',
            'catalogItem.brand',
            'catalogItem.preferredSupplier',
            'options.values',
            'placements',
        ])
            ->withCount([
                'items',
                'items as available_items_count' => fn ($q) => $q->whereIn('status', WorkwearItemStatus::assignableStatuses()),
            ]);

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('model_ref', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }
        if ($clientId = $request->input('filter.client_id')) {
            $query->where('client_id', $clientId);
        }
        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        $query->orderBy(
            $this->queryService->safeSort($request, self::ALLOWED_SORT_COLUMNS, 'created_at'),
            $this->queryService->sortDirection($request)
        );

        $p = $query->paginate($this->queryService->cappedPerPage($request));

        return response()->json(['data' => WorkwearModelResource::collection($p->items()), 'total' => $p->total()]);
    }

    public function show(string $id): JsonResponse
    {
        $m = WorkwearModel::with([
            'client',
            'supplier',
            'workwearFabric',
            'catalogItem.category',
            'catalogItem.brand',
            'catalogItem.preferredSupplier',
            'catalogItem.suppliers',
            'catalogItem.options.values',
            'catalogItem.variants.optionValues',
            'versions.creator',
            'options.values',
            'placements',
            'media',
            'items' => fn ($q) => $q->with('currentAssignment.employee')->latest()->limit(50),
        ])
            ->withCount([
                'items',
                'items as available_items_count' => fn ($q) => $q->whereIn('status', WorkwearItemStatus::assignableStatuses()),
            ])
            ->findOrFail($id);
        $this->authorize('view', $m);

        return response()->json(['data' => new WorkwearModelResource($m)]);
    }

    public function store(StoreWorkwearModelRequest $request): JsonResponse
    {
        $this->authorize('create', WorkwearModel::class);

        $validated = $this->enrichModelPayload($request->validated());
        $optionsData = $validated['options'] ?? null;
        $placements = $validated['placements'] ?? null;
        $changeSummary = $validated['change_summary'] ?? null;
        unset($validated['options'], $validated['placements'], $validated['change_summary']);

        $model = DB::transaction(function () use ($validated, $optionsData, $placements) {
            $model = WorkwearModel::create($validated);

            if (! empty($placements)) {
                $this->workwearService->syncModelPlacements($model, $placements);
            }

            if (! empty($optionsData)) {
                foreach ($optionsData as $optIdx => $opt) {
                    $option = $model->options()->create([
                        'name' => $opt['name'],
                        'ui_control' => $opt['ui_control'] ?? 'select',
                        'sort_order' => $opt['sort_order'] ?? $optIdx,
                    ]);

                    if (! empty($opt['values'])) {
                        foreach ($opt['values'] as $valIdx => $val) {
                            $option->values()->create([
                                'value' => is_array($val) ? $val['value'] : $val,
                                'sort_order' => is_array($val) ? ($val['sort_order'] ?? $valIdx) : $valIdx,
                            ]);
                        }
                    }
                }
            }

            return $model;
        });

        $model->load([
            'client',
            'supplier',
            'workwearFabric',
            'catalogItem.category',
            'catalogItem.brand',
            'catalogItem.preferredSupplier',
            'catalogItem.suppliers',
            'catalogItem.options.values',
            'catalogItem.variants.optionValues',
            'options.values',
            'placements',
        ]);

        return response()->json(['message' => 'Workwear model created.', 'data' => new WorkwearModelResource($model)], 201);
    }

    public function update(UpdateWorkwearModelRequest $request, string $id): JsonResponse
    {
        $model = WorkwearModel::findOrFail($id);
        $this->authorize('update', $model);

        $validated = $this->enrichModelPayload($request->validated(), $model);
        $optionsData = $validated['options'] ?? null;
        $placements = $validated['placements'] ?? null;
        $changeSummary = $validated['change_summary'] ?? null;
        unset($validated['options'], $validated['placements'], $validated['change_summary']);

        DB::transaction(function () use ($model, $validated, $optionsData, $placements, $changeSummary) {
            $model->update($validated);

            if ($placements !== null) {
                $this->workwearService->syncModelPlacements($model, $placements);
            }

            if ($optionsData !== null) {
                // If options array provided, handle option sync/update
                foreach ($optionsData as $optIdx => $opt) {
                    if (! empty($opt['id'])) {
                        $option = $model->options()->find($opt['id']);
                        if ($option) {
                            $option->update([
                                'name' => $opt['name'],
                                'ui_control' => $opt['ui_control'] ?? $option->ui_control,
                                'sort_order' => $opt['sort_order'] ?? $optIdx,
                            ]);
                        }
                    } else {
                        $option = $model->options()->create([
                            'name' => $opt['name'],
                            'ui_control' => $opt['ui_control'] ?? 'select',
                            'sort_order' => $opt['sort_order'] ?? $optIdx,
                        ]);
                    }

                    if ($option && ! empty($opt['values'])) {
                        foreach ($opt['values'] as $valIdx => $val) {
                            $valValue = is_array($val) ? $val['value'] : $val;
                            $valId = is_array($val) ? ($val['id'] ?? null) : null;
                            $valSort = is_array($val) ? ($val['sort_order'] ?? $valIdx) : $valIdx;

                            if ($valId) {
                                $existingVal = $option->values()->find($valId);
                                if ($existingVal) {
                                    $existingVal->update(['value' => $valValue, 'sort_order' => $valSort]);

                                    continue;
                                }
                            }
                            $option->values()->create(['value' => $valValue, 'sort_order' => $valSort]);
                        }
                    }
                }
            }
            if (filled($changeSummary)) {
                $this->workwearService->recordModelVersion($model, $changeSummary);
            }
        });

        $model->load([
            'client',
            'supplier',
            'workwearFabric',
            'catalogItem.category',
            'catalogItem.brand',
            'catalogItem.preferredSupplier',
            'catalogItem.suppliers',
            'catalogItem.options.values',
            'catalogItem.variants.optionValues',
            'options.values',
            'placements',
            'media',
            'versions.creator',
        ]);

        return response()->json(['message' => 'Workwear model updated.', 'data' => new WorkwearModelResource($model)]);
    }

    /**
     * Synchronize denormalized fabric name and preferred supplier from linked records.
     */
    private function enrichModelPayload(array $validated, ?WorkwearModel $existing = null): array
    {
        if (! empty($validated['fabric_id'])) {
            $fabricName = WorkwearFabric::whereKey($validated['fabric_id'])->value('name');
            if ($fabricName) {
                $validated['fabric'] = $fabricName;
            }
        }

        if (! empty($validated['catalog_item_id']) && empty($validated['supplier_id']) && empty($existing?->supplier_id)) {
            $preferredSupplierId = CatalogItem::whereKey($validated['catalog_item_id'])->value('preferred_supplier_id');
            if ($preferredSupplierId) {
                $validated['supplier_id'] = $preferredSupplierId;
            }
        }

        return $validated;
    }

    public function destroy(string $id): JsonResponse
    {
        $m = WorkwearModel::findOrFail($id);
        $this->authorize('delete', $m);
        if ($m->items()->exists()) {
            return response()->json(['message' => 'Cannot delete: model has linked items.'], 422);
        }
        $m->delete();

        return response()->json(['message' => 'Workwear model deleted.']);
    }

    // ─── Options Management ─────────────────────────────────────

    public function storeOption(Request $request, string $id): JsonResponse
    {
        $model = WorkwearModel::findOrFail($id);
        $this->authorize('update', $model);

        $v = $request->validate([
            'name' => 'required|string|max:100',
            'ui_control' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
            'values' => 'nullable|array',
            'values.*' => 'required|string|max:100',
        ]);

        $option = DB::transaction(function () use ($model, $v) {
            $opt = $model->options()->create([
                'name' => $v['name'],
                'ui_control' => $v['ui_control'] ?? 'select',
                'sort_order' => $v['sort_order'] ?? 0,
            ]);

            if (! empty($v['values'])) {
                foreach ($v['values'] as $idx => $val) {
                    $opt->values()->create([
                        'value' => $val,
                        'sort_order' => $idx,
                    ]);
                }
            }

            return $opt->fresh('values');
        });

        return response()->json([
            'message' => 'Option added.',
            'data' => new WorkwearModelOptionResource($option),
        ], 201);
    }

    public function updateOption(Request $request, string $id, string $optionId): JsonResponse
    {
        $model = WorkwearModel::findOrFail($id);
        $this->authorize('update', $model);

        $option = $model->options()->findOrFail($optionId);
        $v = $request->validate([
            'name' => 'sometimes|string|max:100',
            'ui_control' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
        ]);

        $option->update($v);

        return response()->json([
            'message' => 'Option updated.',
            'data' => new WorkwearModelOptionResource($option->fresh('values')),
        ]);
    }

    public function destroyOption(string $id, string $optionId): JsonResponse
    {
        $model = WorkwearModel::findOrFail($id);
        $this->authorize('update', $model);

        $option = $model->options()->findOrFail($optionId);

        // Guard: check if referenced by existing workwear items
        if (WorkwearItemOptionValue::where('option_id', $option->id)->exists()) {
            return response()->json([
                'message' => 'Cannot delete option: it is referenced by existing workwear items.',
            ], 422);
        }

        $option->values()->delete();
        $option->delete();

        return response()->json(['message' => 'Option deleted.']);
    }

    public function storeOptionValue(Request $request, string $id, string $optionId): JsonResponse
    {
        $model = WorkwearModel::findOrFail($id);
        $this->authorize('update', $model);

        $option = $model->options()->findOrFail($optionId);
        $v = $request->validate([
            'value' => 'required|string|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        $value = $option->values()->create([
            'value' => $v['value'],
            'sort_order' => $v['sort_order'] ?? ($option->values()->max('sort_order') + 1),
        ]);

        return response()->json([
            'message' => 'Option value added.',
            'data' => new WorkwearModelOptionValueResource($value),
        ], 201);
    }

    public function destroyOptionValue(string $id, string $optionId, string $valueId): JsonResponse
    {
        $model = WorkwearModel::findOrFail($id);
        $this->authorize('update', $model);

        $option = $model->options()->findOrFail($optionId);
        $value = $option->values()->findOrFail($valueId);

        // Guard: check if referenced by existing workwear items
        if (WorkwearItemOptionValue::where('value_id', $value->id)->exists()) {
            return response()->json([
                'message' => 'Cannot delete option value: it is referenced by existing workwear items.',
            ], 422);
        }

        $value->delete();

        return response()->json(['message' => 'Option value deleted.']);
    }

    // ─── Media Management ───────────────────────────────────────

    public function storeMedia(Request $request, string $id): JsonResponse
    {
        $model = WorkwearModel::findOrFail($id);
        $this->authorize('update', $model);

        $request->validate([
            'file' => 'required|file',
            'collection' => 'nullable|in:logos,design_files',
            'title' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'type' => 'nullable|string|max:100',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $collection = $request->input('collection', 'logos');
        $fileMeta = MediaValidationService::validateAndProcess(
            $request->file('file'),
            $collection,
            'file'
        );

        $resolvedCategory = $request->input('category') ?: $request->input('type') ?: ($collection === 'design_files' ? 'design_spec' : 'logo');

        $customProperties = [
            'original_name' => $fileMeta['original_name'],
            'uploaded_by' => $fileMeta['uploaded_by'],
            'uploaded_by_id' => $fileMeta['uploaded_by_id'],
            'width' => $fileMeta['width'],
            'height' => $fileMeta['height'],
            'title' => $request->input('title', $fileMeta['original_name']),
            'category' => $resolvedCategory,
            'type' => $resolvedCategory,
            'reference' => $request->input('reference'),
            'notes' => $request->input('notes'),
        ];

        $model->addMediaFromRequest('file')
            ->usingFileName($fileMeta['original_name'])
            ->withCustomProperties($customProperties)
            ->toMediaCollection($collection);

        $model->load([
            'client',
            'supplier',
            'workwearFabric',
            'catalogItem.category',
            'catalogItem.brand',
            'catalogItem.preferredSupplier',
            'catalogItem.suppliers',
            'catalogItem.options.values',
            'catalogItem.variants.optionValues',
            'options.values',
            'placements',
            'media',
            'versions.creator',
        ]);

        return response()->json([
            'message' => 'Media uploaded.',
            'data' => new WorkwearModelResource($model),
        ], 201);
    }

    public function downloadMedia(string $id, Media $media)
    {
        $model = WorkwearModel::findOrFail($id);
        $this->authorize('view', $model);

        if ($media->model_id !== $model->id || $media->model_type !== WorkwearModel::class) {
            abort(403, 'Unauthorized file access.');
        }

        return $media->toResponse(request());
    }

    public function destroyMedia(string $id, string $mediaId): JsonResponse
    {
        $model = WorkwearModel::findOrFail($id);
        $this->authorize('update', $model);

        $media = $model->media()->findOrFail($mediaId);

        // Placements referencing this artwork fall back to a plain (artwork-less) placement.
        $model->placements()->where('media_id', $media->id)->update(['media_id' => null]);

        $media->delete();

        return response()->json(['message' => 'Media deleted.']);
    }

    // ─── Version Snapshot ───────────────────────────────────────

    public function createVersion(Request $request, string $id): JsonResponse
    {
        $model = WorkwearModel::findOrFail($id);
        $this->authorize('update', $model);
        $validated = $request->validate(['change_summary' => 'nullable|string|max:500']);

        $version = DB::transaction(
            fn () => $this->workwearService->recordModelVersion($model, $validated['change_summary'] ?? null)
        );

        return response()->json(['message' => 'Version snapshot created.', 'data' => new WorkwearModelVersionResource($version)], 201);
    }
}
