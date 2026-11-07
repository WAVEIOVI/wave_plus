<?php

namespace App\Modules\Procurement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Controllers\Concerns\AuthorizesProcurementRequests;
use App\Modules\Procurement\Models\GoodsReceiptNote;
use App\Modules\Procurement\Requests\StoreGoodsReceiptNoteRequest;
use App\Modules\Procurement\Requests\UpdateGoodsReceiptNoteRequest;
use App\Modules\Procurement\Resources\GoodsReceiptNoteResource;
use App\Modules\Procurement\Services\GrnWriteService;
use App\Modules\Procurement\Services\ProcurementQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GoodsReceiptNoteController extends Controller
{
    use AuthorizesProcurementRequests;

    public function __construct(
        private readonly GrnWriteService $writeService,
        private readonly ProcurementQueryService $queryService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', GoodsReceiptNote::class);

        $query = GoodsReceiptNote::with(['supplier', 'purchaseOrder', 'lines']);

        if ($q = $request->input('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('reference', 'like', "%{$q}%")
                    ->orWhere('supplier_delivery_note_ref', 'like', "%{$q}%");
            });
        }

        if ($status = $request->input('filter.status')) {
            $query->whereIn('status', explode(',', $status));
        }

        if ($supplierId = $request->input('filter.supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($poId = $request->input('filter.po_id')) {
            $query->where('po_id', $poId);
        }

        $perPage = $this->queryService->cappedPerPage($request);

        return GoodsReceiptNoteResource::collection($query->latest()->paginate($perPage));
    }

    public function show(GoodsReceiptNote $goodsReceiptNote)
    {
        $this->authorize('view', $goodsReceiptNote);
        $goodsReceiptNote->load(['supplier', 'purchaseOrder', 'lines.catalogItem.product', 'lines.catalogItem.consumable', 'lines.catalogItem.eqServicing', 'lines.catalogVariant', 'lines.storageLocation', 'lines.poLine']);

        return new GoodsReceiptNoteResource($goodsReceiptNote);
    }

    public function store(StoreGoodsReceiptNoteRequest $request)
    {
        $this->authorize('create', GoodsReceiptNote::class);

        $grn = $this->writeService->create($request->validated(), $request);

        return (new GoodsReceiptNoteResource($grn->load(['supplier', 'purchaseOrder', 'lines.catalogItem.product', 'lines.catalogItem.consumable', 'lines.catalogItem.eqServicing', 'lines.catalogVariant', 'lines.storageLocation', 'lines.poLine'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateGoodsReceiptNoteRequest $request, GoodsReceiptNote $goodsReceiptNote)
    {
        $this->authorize('update', $goodsReceiptNote);

        $grn = $this->writeService->update($goodsReceiptNote, $request->validated(), $request);

        return new GoodsReceiptNoteResource($grn->load(['supplier', 'purchaseOrder', 'lines.catalogItem', 'lines.catalogVariant', 'lines.storageLocation', 'lines.poLine']));
    }

    public function updateStatus(Request $request, GoodsReceiptNote $goodsReceiptNote)
    {
        $this->authorize('update', $goodsReceiptNote);

        $validated = $request->validate([
            'status' => 'required|string',
            'allow_over_receive' => 'nullable|boolean',
        ]);

        $grn = $this->writeService->transition(
            $goodsReceiptNote,
            $validated['status'],
            (bool) ($validated['allow_over_receive'] ?? false)
        );

        return new GoodsReceiptNoteResource($grn->load(['supplier', 'purchaseOrder', 'lines.catalogItem', 'lines.catalogVariant', 'lines.storageLocation', 'lines.poLine']));
    }

    public function downloadDeliveryNote(GoodsReceiptNote $goodsReceiptNote)
    {
        $this->authorize('view', $goodsReceiptNote);
        $media = $goodsReceiptNote->getFirstMedia('delivery_note');

        if ($media) {
            return $media->toResponse(request());
        }

        if ($goodsReceiptNote->supplier_delivery_note_path && Storage::disk('local')->exists($goodsReceiptNote->supplier_delivery_note_path)) {
            return Storage::disk('local')->download($goodsReceiptNote->supplier_delivery_note_path);
        }

        if ($goodsReceiptNote->supplier_delivery_note_path && Storage::disk('public')->exists($goodsReceiptNote->supplier_delivery_note_path)) {
            return Storage::disk('public')->download($goodsReceiptNote->supplier_delivery_note_path);
        }

        abort(404, 'No delivery note file attached.');
    }

    public function destroy(GoodsReceiptNote $goodsReceiptNote)
    {
        $this->authorize('delete', $goodsReceiptNote);
        $this->writeService->delete($goodsReceiptNote);

        return response()->noContent();
    }
}
