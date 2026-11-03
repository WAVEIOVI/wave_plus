<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Services;

use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Inventory\Events\GrnConfirmed;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseZone;
use App\Modules\Procurement\Models\GoodsReceiptNote;
use App\Modules\Procurement\Models\PoLine;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Shared\Services\MediaValidationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GrnWriteService
{
    public function __construct(
        private readonly ProcurementStateMachine $stateMachine,
        private readonly PurchaseOrderWriteService $purchaseOrders,
    ) {}

    public function create(array $validated, Request $request): GoodsReceiptNote
    {
        $po = PurchaseOrder::query()->findOrFail($validated['po_id']);
        $this->stateMachine->assertPoReceivable($po->status);

        if ($po->supplier_id !== $validated['supplier_id']) {
            abort(422, 'GRN supplier must match the purchase order supplier.');
        }

        $allowOver = (bool) ($validated['allow_over_receive'] ?? false);
        unset($validated['allow_over_receive']);

        // Auto-calculate delivery_on_time and days_late
        $validated = $this->calculateDeliveryMetrics($validated, $po);

        $grn = DB::transaction(function () use ($validated, $request, $allowOver, $po) {
            $validated['status'] = 'draft';
            $grnData = collect($validated)->except(['lines', 'delivery_note_file'])->toArray();
            $grn = GoodsReceiptNote::create($grnData);

            if ($request->hasFile('delivery_note_file')) {
                $fileMeta = MediaValidationService::validateAndProcess(
                    $request->file('delivery_note_file'),
                    MediaValidationService::CATEGORY_DOCUMENT,
                    'delivery_note_file'
                );
                $grn->addMediaFromRequest('delivery_note_file')
                    ->usingFileName($fileMeta['original_name'])
                    ->withCustomProperties([
                        'uploaded_by' => $fileMeta['uploaded_by'],
                        'uploaded_by_id' => $fileMeta['uploaded_by_id'],
                    ])
                    ->toMediaCollection('delivery_note');
            }

            // Get default zone/location from PO site for stockable items
            $defaultZone = null;
            $defaultLocation = null;
            if ($po->site_id) {
                $defaultZone = WarehouseZone::query()
                    ->where('site_id', $po->site_id)
                    ->where('status', 'active')
                    ->orderBy('code')
                    ->first();

                if ($defaultZone) {
                    $defaultLocation = WarehouseLocation::query()
                        ->where('zone_id', $defaultZone->id)
                        ->where('is_active', true)
                        ->orderBy('barcode')
                        ->first();
                }
            }

            foreach ($validated['lines'] as $lineData) {
                $this->assertLineBelongsToPo($grn->po_id, $lineData['po_line_id']);
                $this->assertNotOverReceived($lineData, $allowOver, []);

                // Auto-set default zone/location for stockable items
                if (empty($lineData['storage_location_id']) && $defaultLocation) {
                    $poLine = PoLine::query()->find($lineData['po_line_id']);
                    $catalogItem = $poLine?->catalogItem;
                    if ($catalogItem && $catalogItem->is_stockable !== false) {
                        $lineData['storage_location_id'] = $defaultLocation->id;
                    }
                }

                $grn->lines()->create($lineData);
            }

            return $grn;
        });

        return $grn->fresh();
    }

    public function update(GoodsReceiptNote $grn, array $validated, Request $request): GoodsReceiptNote
    {
        $this->stateMachine->assertEditable('grn', $grn->status);
        unset($validated['status'], $validated['allow_over_receive']);

        // Auto-calculate delivery_on_time and days_late (if received_at changed)
        $po = $grn->purchaseOrder;
        if (isset($validated['received_at']) && $po) {
            $validated = $this->calculateDeliveryMetrics($validated, $po);
        }

        DB::transaction(function () use ($grn, $validated, $request) {
            $grnData = collect($validated)->except(['lines', 'delivery_note_file'])->toArray();
            $grn->update($grnData);

            if ($request->hasFile('delivery_note_file')) {
                $fileMeta = MediaValidationService::validateAndProcess(
                    $request->file('delivery_note_file'),
                    MediaValidationService::CATEGORY_DOCUMENT,
                    'delivery_note_file'
                );
                $grn->clearMediaCollection('delivery_note');
                $grn->addMediaFromRequest('delivery_note_file')
                    ->usingFileName($fileMeta['original_name'])
                    ->withCustomProperties([
                        'uploaded_by' => $fileMeta['uploaded_by'],
                        'uploaded_by_id' => $fileMeta['uploaded_by_id'],
                    ])
                    ->toMediaCollection('delivery_note');
            }

            if (isset($validated['lines'])) {
                $existingLineIds = collect($validated['lines'])->filter(fn ($line) => isset($line['id']))->pluck('id')->all();
                $grn->lines()->whereNotIn('id', $existingLineIds)->delete();

                foreach ($validated['lines'] as $lineData) {
                    $this->assertLineBelongsToPo($grn->po_id, $lineData['po_line_id']);
                    if (! empty($lineData['id'])) {
                        $grn->lines()->where('id', $lineData['id'])->update($lineData);
                    } else {
                        $grn->lines()->create($lineData);
                    }
                }
            }
        });

        return $grn->fresh();
    }

    public function transition(GoodsReceiptNote $grn, string $status, bool $allowOverReceive = false): GoodsReceiptNote
    {
        $previous = $grn->status;
        $this->stateMachine->assertUserTransition('grn', $previous, $status);

        $po = PurchaseOrder::query()->findOrFail($grn->po_id);
        if (in_array($status, ['confirmed'], true)) {
            $this->stateMachine->assertPoReceivable($po->status);
        }

        DB::transaction(function () use ($grn, $status, $allowOverReceive) {
            $appliesQty = $status === 'confirmed';

            if ($appliesQty) {
                foreach ($grn->lines as $line) {
                    $this->assertStorageLocation($line);
                    $this->assertNotOverReceived([
                        'po_line_id' => $line->po_line_id,
                        'qty_accepted' => $line->qty_accepted,
                        'qty_received' => $line->qty_received,
                    ], $allowOverReceive, [$grn->id]);
                }
            }

            $grn->update(['status' => $status]);

            if ($appliesQty) {
                foreach ($grn->lines as $line) {
                    $this->applyPoLineQty($line->po_line_id, (float) ($line->qty_accepted ?? $line->qty_received));
                }
                $this->purchaseOrders->applyReceiptStatus($grn->purchaseOrder()->first());
            }
        });

        $grn->refresh();

        if ($grn->status === 'confirmed' && $previous !== 'confirmed') {
            GrnConfirmed::dispatch($grn, Auth::id());
        }

        return $grn->fresh();
    }

    public function delete(GoodsReceiptNote $grn): void
    {
        $this->stateMachine->assertDeletable('grn', $grn->status);
        $grn->delete();
    }

    private function assertLineBelongsToPo(string $poId, string $poLineId): void
    {
        $belongs = PoLine::query()->where('id', $poLineId)->where('po_id', $poId)->exists();
        if (! $belongs) {
            abort(422, 'GRN lines must belong to the selected purchase order.');
        }
    }

    private function assertStorageLocation($line): void
    {
        $item = $line->catalogItem ?: ($line->catalog_item_id ? CatalogItem::find($line->catalog_item_id) : null);
        if ($item && $item->is_stockable === false) {
            return;
        }
        if (empty($line->storage_location_id)) {
            abort(422, 'Cannot confirm receipt: storage_location_id is required for stockable lines.');
        }
    }

    /**
     * @param  array<int, string>  $ignoreGrnIds
     */
    private function assertNotOverReceived(array $lineData, bool $allowOver, array $ignoreGrnIds): void
    {
        $poLine = PoLine::query()->find($lineData['po_line_id']);
        if (! $poLine) {
            return;
        }

        $incoming = (float) ($lineData['qty_accepted'] ?? $lineData['qty_received'] ?? 0);
        $already = (float) $poLine->qty_received;
        if ($already + $incoming <= (float) $poLine->qty_ordered + 0.0001) {
            return;
        }

        if (! $allowOver) {
            abort(422, "Cannot receive more than ordered for PO line {$poLine->id} without allow_over_receive.");
        }
    }

    private function applyPoLineQty(string $poLineId, float $qtyChange): void
    {
        $poLine = PoLine::query()->find($poLineId);
        if (! $poLine) {
            return;
        }

        $poLine->increment('qty_received', $qtyChange);
        $poLine->refresh();

        if ((float) $poLine->qty_received <= 0) {
            $poLine->qty_received = 0;
            $poLine->fulfillment_status = 'pending';
        } elseif ((float) $poLine->qty_received < (float) $poLine->qty_ordered) {
            $poLine->fulfillment_status = 'partially_received';
        } elseif ((float) $poLine->qty_received == (float) $poLine->qty_ordered) {
            $poLine->fulfillment_status = 'fully_received';
        } else {
            $poLine->fulfillment_status = 'over_received';
        }

        $poLine->save();
    }

    /**
     * Calculate delivery_on_time and days_late based on received_at vs PO committed_delivery_date.
     */
    private function calculateDeliveryMetrics(array $validated, PurchaseOrder $po): array
    {
        if (empty($validated['received_at']) || ! $po->committed_delivery_date) {
            $validated['delivery_on_time'] = true;
            $validated['days_late'] = 0;

            return $validated;
        }

        $receivedAt = Carbon::parse($validated['received_at'])->startOfDay();
        $committedDate = Carbon::parse($po->committed_delivery_date)->startOfDay();

        $validated['delivery_on_time'] = $receivedAt->lte($committedDate);
        $validated['days_late'] = $receivedAt->gt($committedDate)
            ? $receivedAt->diffInDays($committedDate)
            : 0;

        return $validated;
    }
}
