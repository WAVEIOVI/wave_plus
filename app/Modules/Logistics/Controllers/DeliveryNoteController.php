<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\StockItem;
use App\Modules\Logistics\Controllers\Concerns\AuthorizesLogisticsRequests;
use App\Modules\Logistics\Models\DeliveryNote;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\DwoLine;
use App\Modules\Logistics\Services\DeliveryNoteStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeliveryNoteController extends Controller
{
    use AuthorizesLogisticsRequests;

    public function __construct(
        private readonly DeliveryNoteStockService $stockService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DeliveryNote::class);

        $notes = DeliveryNote::with(['client', 'deliveryWorkOrder'])->paginate();

        return response()->json($notes);
    }

    public function show(DeliveryNote $deliveryNote): JsonResponse
    {
        $this->authorize('view', $deliveryNote);

        $deliveryNote->load(['client', 'deliveryWorkOrder.lines']);

        return response()->json($deliveryNote);
    }

    public function sign(Request $request, DeliveryNote $deliveryNote): JsonResponse
    {
        $this->authorize('sign', $deliveryNote);

        $validated = $request->validate([
            'signature_data'    => 'required|string',
            'recipient_name'    => 'required|string',
            'recipient_title'   => 'nullable|string',
            'condition_overall' => 'nullable|string',
        ]);

        $deliveryNote->update([
            'signature_data'    => $validated['signature_data'],
            'recipient_name'    => $validated['recipient_name'],
            'recipient_title'   => $validated['recipient_title'] ?? null,
            'condition_overall' => $validated['condition_overall'] ?? null,
            'signature_at'      => now(),
            'updated_by'        => $request->user()->id,
        ]);

        $this->stockService->confirmAndPost($deliveryNote->fresh(), $request->user()->id, []);

        return response()->json($deliveryNote->fresh());
    }

    /**
     * Confirm physical delivery and post inventory movements (idempotent).
     * Accepts actual delivered quantities per line to ensure stock matches physical delivery.
     */
    public function confirm(Request $request, DeliveryNote $deliveryNote): JsonResponse
    {
        $this->authorize('sign', $deliveryNote);

        $validated = $request->validate([
            'lines' => 'sometimes|array',
            'lines.*.dwo_line_id' => 'required_with:lines|string|exists:dwo_lines,id',
            'lines.*.qty_delivered' => 'required_with:lines|numeric|min:0',
        ]);

        // Validate stock availability before posting
        $this->validateStockAvailability($deliveryNote, $validated['lines'] ?? []);

        $movements = $this->stockService->confirmAndPost($deliveryNote, $request->user()->id, $validated['lines'] ?? []);

        return response()->json([
            'message'          => 'Delivery Note confirmed and stock posted.',
            'delivery_note'    => $deliveryNote->fresh(),
            'movements_count'  => count($movements),
        ]);
    }

    private function validateStockAvailability(DeliveryNote $note, array $deliveredLines): void
    {
        $dwo = DeliveryWorkOrder::with(['lines.catalogItem'])->findOrFail($note->dwo_id);

        $deliveredQtyMap = [];
        foreach ($deliveredLines as $dl) {
            $deliveredQtyMap[$dl['dwo_line_id']] = (float) $dl['qty_delivered'];
        }

        foreach ($dwo->lines as $line) {
            $catalogItem = $line->catalogItem;
            if (! $catalogItem || ! $catalogItem->is_stockable) {
                continue;
            }

            $qtyToIssue = $deliveredQtyMap[$line->id] ?? (float) (
                $line->qty_loaded > 0
                    ? $line->qty_loaded
                    : ($line->qty_picked > 0 ? $line->qty_picked : $line->qty_allocated)
            );

            if ($qtyToIssue <= 0) {
                continue;
            }

            // Calculate total available stock (reserved + on_hand) for this item
            $totalAvailable = StockItem::where('catalog_item_id', $line->catalog_item_id)
                ->when($line->catalog_variant_id, fn ($q) => $q->where('catalog_variant_id', $line->catalog_variant_id))
                ->where('status', 'active')
                ->sum('qty_on_hand');

            // Also check reservations for this DWO line
            $reservedQty = \App\Modules\Inventory\Models\StockReservation::where('reservable_type', DeliveryWorkOrder::class)
                ->where('reservable_id', $dwo->id)
                ->where('dwo_line_id', $line->id)
                ->where('status', 'active')
                ->sum('qty_reserved');

            $totalForDelivery = $reservedQty + $totalAvailable;

            if ($qtyToIssue > $totalForDelivery + 0.0001) {
                throw ValidationException::withMessages([
                    'lines' => [
                        "Insufficient stock for item {$line->description_snapshot}. Requested: {$qtyToIssue}, Available: {$totalForDelivery}",
                    ],
                ]);
            }
        }
    }
}
