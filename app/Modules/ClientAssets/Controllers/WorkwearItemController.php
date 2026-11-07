<?php

namespace App\Modules\ClientAssets\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ClientAssets\Controllers\Concerns\AuthorizesClientAssetsRequests;
use App\Modules\ClientAssets\Models\WorkwearItem;
use App\Modules\ClientAssets\Models\WorkwearModel;
use App\Modules\ClientAssets\Requests\AssignWorkwearItemRequest;
use App\Modules\ClientAssets\Requests\BatchStoreWorkwearItemRequest;
use App\Modules\ClientAssets\Requests\ReturnWorkwearItemRequest;
use App\Modules\ClientAssets\Requests\StoreWorkwearItemRequest;
use App\Modules\ClientAssets\Requests\UpdateWorkwearItemRequest;
use App\Modules\ClientAssets\Resources\WorkwearAssignmentResource;
use App\Modules\ClientAssets\Resources\WorkwearItemResource;
use App\Modules\ClientAssets\Services\ClientAssetsQueryService;
use App\Modules\ClientAssets\Services\WorkwearService;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Logistics\Models\DeliveryNote;
use App\Modules\Shared\Services\MediaValidationService;
use App\Modules\Shared\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkwearItemController extends Controller
{
    use AuthorizesClientAssetsRequests;

    private const ALLOWED_SORT_COLUMNS = ['created_at', 'item_code', 'sku', 'status', 'garment_type', 'size', 'color', 'unit_cost'];

    private const ITEM_RELATIONS = [
        'workwearModel.supplier', 'workwearModel.catalogItem.options.values', 'workwearModel.options.values', 'workwearModel.placements',
        'client', 'supplier', 'media', 'salesOrder', 'deliveryNote',
        'itemOptionValues.option', 'itemOptionValues.optionValue',
    ];

    public function __construct(
        private readonly ClientAssetsQueryService $queryService,
        private readonly WorkwearService $workwearService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkwearItem::class);

        $query = WorkwearItem::with([
            'workwearModel.supplier', 'workwearModel.catalogItem',
            'client', 'supplier', 'media', 'salesOrder', 'deliveryNote',
            'currentAssignment.employee', 'itemOptionValues.option', 'itemOptionValues.optionValue',
        ]);
        if ($s = $request->input('q')) {
            $query->where(fn ($q) => $q->where('item_code', 'like', "%{$s}%")
                ->orWhere('size', 'like', "%{$s}%")
                ->orWhere('color', 'like', "%{$s}%")
                ->orWhere('uuid', 'like', "%{$s}%"));
        }
        if ($c = $request->input('filter.client_id')) {
            $query->where('client_id', $c);
        }
        if ($m = $request->input('filter.model_id')) {
            $query->where('model_id', $m);
        }
        if ($sup = $request->input('filter.supplier_id')) {
            $query->where('supplier_id', $sup);
        }
        if ($size = $request->input('filter.size')) {
            $query->where('size', $size);
        }
        if ($color = $request->input('filter.color')) {
            $query->where('color', $color);
        }
        if ($st = $request->input('filter.status')) {
            $query->where('status', $st);
        }
        if ($cond = $request->input('filter.item_condition')) {
            $query->where('item_condition', $cond);
        }
        if ($request->input('filter.is_stolen')) {
            $query->where('is_stolen', true);
        }
        $query->orderBy(
            $this->queryService->safeSort($request, self::ALLOWED_SORT_COLUMNS, 'created_at'),
            $this->queryService->sortDirection($request)
        );
        $p = $query->paginate($this->queryService->cappedPerPage($request));

        return response()->json(['data' => WorkwearItemResource::collection($p->items()), 'total' => $p->total()]);
    }

    /**
     * Optional Sales Order / Delivery Note picker data, scoped to one client.
     */
    public function documentOptions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkwearItem::class);

        $v = $request->validate([
            'client_id' => 'required|ulid|exists:clients,id',
            'q' => 'nullable|string|max:100',
        ]);
        $q = $v['q'] ?? null;

        $salesOrders = SalesOrder::query()
            ->where('client_id', $v['client_id'])
            ->when($q, fn ($qq) => $qq->where('reference', 'like', "%{$q}%"))
            ->orderByDesc('created_at')->limit(25)
            ->get(['id', 'reference'])
            ->map(fn ($so) => ['id' => $so->id, 'label' => $so->reference]);

        $deliveryNotes = DeliveryNote::query()
            ->where('client_id', $v['client_id'])
            ->when($q, fn ($qq) => $qq->where('dn_number', 'like', "%{$q}%"))
            ->orderByDesc('created_at')->limit(25)
            ->get(['id', 'dn_number'])
            ->map(fn ($dn) => ['id' => $dn->id, 'label' => $dn->dn_number]);

        return response()->json(['data' => [
            'sales_orders' => $salesOrders,
            'delivery_notes' => $deliveryNotes,
        ]]);
    }

    public function kpis(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkwearItem::class);
        $clientId = $request->input('filter.client_id') ?? $request->input('client_id');
        $kpis = $this->workwearService->getKpis($clientId);

        return response()->json(['data' => $kpis]);
    }

    public function show(string $id): JsonResponse
    {
        $i = WorkwearItem::with([
            'workwearModel.supplier', 'workwearModel.catalogItem.options.values', 'workwearModel.options.values', 'workwearModel.placements',
            'client', 'supplier', 'media',
            'currentAssignment.employee', 'currentAssignment.assignedByUser',
            'assignments.employee', 'assignments.assignedByUser', 'assignments.returnedByUser',
            'itemOptionValues.option', 'itemOptionValues.optionValue', 'salesOrder', 'deliveryNote',
        ])->findOrFail($id);
        $this->authorize('view', $i);

        return response()->json(['data' => new WorkwearItemResource($i)]);
    }

    public function store(StoreWorkwearItemRequest $r): JsonResponse
    {
        $this->authorize('create', WorkwearItem::class);
        $validated = $r->validated();
        $optionValueIds = $validated['option_value_ids'] ?? null;
        unset($validated['option_value_ids']);

        // Automatically inherit supplier_id and garment_type from WorkwearModel if not specified
        if (! empty($validated['model_id'])) {
            $model = WorkwearModel::with('catalogItem')->find($validated['model_id']);
            if ($model) {
                $validated['client_id'] = $model->client_id;
                $validated['supplier_id'] = $validated['supplier_id'] ?? $model->supplier_id ?? $model->catalogItem?->preferred_supplier_id;
                $validated['garment_type'] = $validated['garment_type'] ?? $model->silhouette_item ?? $model->silhouette ?? ($model->garment_types[0] ?? null);
            }
        }

        $i = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $optionValueIds) {
            $item = WorkwearItem::create($validated);
            if (! empty($optionValueIds)) {
                $this->workwearService->syncItemOptionValues($item, $optionValueIds);
            }

            return $item;
        });

        $i->load(self::ITEM_RELATIONS);

        return response()->json(['message' => 'Workwear item created.', 'data' => new WorkwearItemResource($i)], 201);
    }

    public function batchStore(BatchStoreWorkwearItemRequest $r): JsonResponse
    {
        $this->authorize('create', WorkwearItem::class);

        $result = $this->workwearService->batchCreateItems($r->validated());

        return response()->json([
            'message' => count($result['created']).' item(s) created.',
            'data' => WorkwearItemResource::collection($result['created']),
            'errors' => $result['errors'],
        ], count($result['errors']) > 0 ? 207 : 201);
    }

    public function update(UpdateWorkwearItemRequest $r, string $id): JsonResponse
    {
        $i = WorkwearItem::findOrFail($id);
        $this->authorize('update', $i);
        $validated = $r->validated();
        $optionValueIds = $validated['option_value_ids'] ?? null;
        unset($validated['option_value_ids']);

        \Illuminate\Support\Facades\DB::transaction(function () use ($i, $validated, $optionValueIds) {
            $modelChanged = isset($validated['model_id']) && $validated['model_id'] !== $i->model_id;
            $i->update($validated);

            if ($optionValueIds !== null) {
                $this->workwearService->syncItemOptionValues($i, $optionValueIds);
            } elseif ($modelChanged) {
                $i->itemOptionValues()->delete();
            }
        });

        $i->load(self::ITEM_RELATIONS);

        return response()->json(['message' => 'Workwear item updated.', 'data' => new WorkwearItemResource($i)]);
    }

    public function destroy(string $id): JsonResponse
    {
        $i = WorkwearItem::findOrFail($id);
        $this->authorize('delete', $i);

        // Prevent deletion if currently assigned
        if ($i->currentAssignment) {
            return response()->json(['message' => 'Cannot delete: item is currently assigned.'], 422);
        }

        $i->delete();

        return response()->json(['message' => 'Workwear item deleted.']);
    }

    // ─── Stock availability (read-only) ─────────────────────────

    public function stock(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WorkwearItem::class);

        $v = $request->validate([
            'filter.client_id' => 'nullable|ulid',
            'filter.model_id' => 'nullable|ulid',
            'filter.supplier_id' => 'nullable|ulid',
        ]);

        return response()->json(['data' => $this->workwearService->stockSummary(
            $v['filter']['client_id'] ?? null,
            $v['filter']['model_id'] ?? null,
            $v['filter']['supplier_id'] ?? null,
        )]);
    }

    // ─── Product media (photos) ─────────────────────────────────

    public function storeMedia(Request $request, string $id): JsonResponse
    {
        $item = WorkwearItem::findOrFail($id);
        $this->authorize('update', $item);

        $request->validate(['file' => 'required|file', 'title' => 'nullable|string|max:255']);

        $fileMeta = MediaValidationService::validateAndProcess($request->file('file'), 'photos', 'file');

        $item->addMediaFromRequest('file')
            ->usingFileName($fileMeta['original_name'])
            ->withCustomProperties([
                'original_name' => $fileMeta['original_name'],
                'uploaded_by' => $fileMeta['uploaded_by'],
                'uploaded_by_id' => $fileMeta['uploaded_by_id'],
                'title' => $request->input('title', $fileMeta['original_name']),
            ])
            ->toMediaCollection('photos');

        $item->load(self::ITEM_RELATIONS);

        return response()->json(['message' => 'Photo uploaded.', 'data' => new WorkwearItemResource($item)], 201);
    }

    public function destroyMedia(string $id, string $mediaId): JsonResponse
    {
        $item = WorkwearItem::findOrFail($id);
        $this->authorize('update', $item);

        $item->media()->findOrFail($mediaId)->delete();

        return response()->json(['message' => 'Photo deleted.']);
    }

    // ─── Flag Stolen ───────────────────────────────────────────

    public function flagStolen(Request $request, string $id): JsonResponse
    {
        $item = WorkwearItem::findOrFail($id);
        $this->authorize('update', $item);
        $v = $request->validate(['report_ref' => 'nullable|string|max:100', 'notes' => 'nullable|string']);
        $item->update([
            'is_stolen' => true,
            'status' => 'stolen',
            'stolen_data' => ['reported_at' => now()->toISOString(), 'report_ref' => $v['report_ref'] ?? null, 'notes' => $v['notes'] ?? null, 'reported_by' => Auth::id()],
        ]);

        return response()->json(['message' => 'Item flagged as stolen.', 'data' => new WorkwearItemResource($item)]);
    }

    // ─── Assignment ────────────────────────────────────────────

    public function assign(AssignWorkwearItemRequest $request): JsonResponse
    {
        $this->authorize('create', WorkwearItem::class);

        $result = $this->workwearService->assignItems($request->validated());

        $statusCode = count($result['errors']) > 0 ? 207 : 200;

        return response()->json([
            'message' => count($result['assignments']).' item(s) assigned.',
            'data' => WorkwearAssignmentResource::collection($result['assignments']),
            'errors' => $result['errors'],
        ], $statusCode);
    }

    /**
     * Legacy single-item assign endpoint for backward compatibility.
     */
    public function assignSingle(Request $request, string $id): JsonResponse
    {
        $item = WorkwearItem::findOrFail($id);
        $this->authorize('update', $item);
        $v = $request->validate([
            'employee_id' => 'required|ulid|exists:workwear_employees,id',
            'condition_at_assign' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $result = $this->workwearService->assignItems([
            'employee_id' => $v['employee_id'],
            'item_ids' => [$item->id],
            'condition_at_assign' => $v['condition_at_assign'] ?? null,
            'notes' => $v['notes'] ?? null,
        ]);

        if (count($result['errors']) > 0) {
            return response()->json(['message' => $result['errors'][0]['message']], 422);
        }

        $item->load(['currentAssignment.employee']);

        return response()->json(['message' => 'Item assigned.', 'data' => new WorkwearItemResource($item)]);
    }

    // ─── Return ────────────────────────────────────────────────

    public function returnAssignment(ReturnWorkwearItemRequest $request, string $assignmentId): JsonResponse
    {
        $a = \App\Modules\ClientAssets\Models\WorkwearAssignment::findOrFail($assignmentId);
        $this->authorize('update', $a->item);

        $assignment = $this->workwearService->returnAssignment($assignmentId, $request->validated());

        return response()->json([
            'message' => 'Item returned.',
            'data' => new WorkwearAssignmentResource($assignment),
        ]);
    }

    // ─── QR Code ───────────────────────────────────────────────

    public function qrDownload(Request $request, string $id): mixed
    {
        $i = WorkwearItem::findOrFail($id);
        $this->authorize('view', $i);
        $url = url("/ca/scan/ww/{$i->uuid}");

        $format = strtolower($request->query('format', 'png'));

        if ($format === 'svg') {
            $svg = QrCodeService::generateSvg($url);

            return response($svg, 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => "attachment; filename=\"qr-{$i->item_code}.svg\"",
            ]);
        }

        $qr = QrCodeService::generatePng($url, 300);

        return response($qr, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => "attachment; filename=\"qr-{$i->item_code}.png\"",
        ]);
    }

    public function publicScan(string $uuid): JsonResponse
    {
        $i = WorkwearItem::with('workwearModel')->where('uuid', $uuid)->firstOrFail();

        // Only expose non-sensitive data
        $data = [
            'item_code' => $i->item_code,
            'garment_type' => $i->garment_type,
            'size' => $i->size,
            'status' => is_object($i->status) ? $i->status->value : $i->status,
            'is_stolen' => $i->is_stolen,
            'model' => $i->workwearModel ? [
                'name' => $i->workwearModel->name,
                'model_ref' => $i->workwearModel->model_ref,
            ] : null,
        ];

        if ($i->is_stolen) {
            $data['stolen_warning'] = 'This item has been reported as stolen.';
        }

        return response()->json(['data' => $data]);
    }
}
