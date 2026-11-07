<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Services;

use App\Models\User;
use App\Modules\Catalog\Services\CatalogLineSnapshotService;
use App\Modules\Procurement\Models\InternalPurchaseRequest;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\RfqHeader;
use Illuminate\Support\Facades\DB;

class PurchaseOrderWriteService
{
    public function __construct(
        private readonly ProcurementStateMachine $stateMachine,
        private readonly CatalogLineSnapshotService $snapshots,
    ) {}

    public function create(array $validated): PurchaseOrder
    {
        return DB::transaction(function () use ($validated) {
            $poData = collect($validated)->except('lines')->toArray();
            $poData['status'] = $poData['status'] ?? 'draft';

            if ($poData['status'] !== 'draft') {
                abort(422, 'Purchase orders must be created in draft and then follow the approval workflow.');
            }

            $this->assertSourceDocuments($poData);

            $po = PurchaseOrder::create($poData);
            $this->syncLines($po, $validated['lines']);
            $this->relinkSources($po);

            return $po->fresh();
        });
    }

    public function update(PurchaseOrder $po, array $validated): PurchaseOrder
    {
        $this->stateMachine->assertEditable('po', $po->status);

        return DB::transaction(function () use ($po, $validated) {
            $poData = collect($validated)->except(['lines', 'status'])->toArray();
            $po->update($poData);

            if (isset($validated['lines'])) {
                $this->syncLines($po, $validated['lines'], true);
            }

            $this->relinkSources($po->fresh());

            return $po->fresh();
        });
    }

    public function transition(PurchaseOrder $po, array $payload, User $user): PurchaseOrder
    {
        $to = (string) $payload['status'];
        $this->stateMachine->assertUserTransition('po', $po->status, $to, [
            'approval_required' => (bool) $po->approval_required,
            'has_receipts' => $po->goodsReceiptNotes()->exists(),
        ]);

        $updates = ['status' => $to];

        if ($to === 'approved') {
            if ($po->lines()->count() === 0 || ! $po->supplier_id) {
                abort(422, 'Cannot approve a purchase order without a supplier and lines.');
            }
            $updates['approved_by'] = $user->id;
            $updates['approved_at'] = now();
        }

        if ($to === 'sent') {
            $updates['sent_at'] = $po->sent_at ?? now();
        }

        if ($to === 'acknowledged') {
            $updates['acknowledged_at'] = $po->acknowledged_at ?? now();
        }

        $po->update($updates);

        return $po->fresh();
    }

    public function delete(PurchaseOrder $po): void
    {
        $this->stateMachine->assertDeletable('po', $po->status, [
            'has_receipts' => $po->goodsReceiptNotes()->exists(),
        ]);
        $po->delete();
    }

    public function applyReceiptStatus(PurchaseOrder $po): void
    {
        $po->load('lines');
        $allPending = true;
        $allFullyReceived = true;

        foreach ($po->lines as $line) {
            if ($line->fulfillment_status !== 'pending') {
                $allPending = false;
            }
            if (! in_array($line->fulfillment_status, ['fully_received', 'over_received'], true)) {
                $allFullyReceived = false;
            }
        }

        if ($allFullyReceived) {
            $po->status = 'fully_received';
        } elseif (! $allPending) {
            $po->status = 'partially_received';
        } elseif (in_array($po->status, ['partially_received', 'fully_received'], true)) {
            $po->status = 'in_delivery';
        }

        $po->save();
    }

    private function assertSourceDocuments(array $poData): void
    {
        if (! empty($poData['ipr_ids'])) {
            $iprs = InternalPurchaseRequest::query()->whereIn('id', $poData['ipr_ids'])->get();
            foreach ($iprs as $ipr) {
                if ($ipr->status !== 'approved' && $ipr->status !== 'converted_to_po') {
                    abort(422, "IPR {$ipr->reference} must be approved before conversion to a purchase order.");
                }
            }
        }

        if (! empty($poData['rfq_id'])) {
            $rfq = RfqHeader::query()->find($poData['rfq_id']);
            if ($rfq && $rfq->status !== 'awarded') {
                abort(422, 'A purchase order can only be created from an awarded RFQ.');
            }
        }
    }

    private function syncLines(PurchaseOrder $po, array $lines, bool $updating = false): void
    {
        $keepIds = [];
        $totalHt = $totalTax = $totalTtc = 0.0;

        foreach ($lines as $lineData) {
            $snapshot = $this->snapshots->forLine($lineData);
            $lineHt = (float) $lineData['qty_ordered'] * (float) $lineData['unit_price_ht'];
            $lineTax = $lineHt * ((float) $lineData['tax_rate_pct'] / 100);
            $lineTtc = $lineHt + $lineTax;

            $payload = [
                'catalog_item_id' => $lineData['catalog_item_id'] ?? null,
                'variant_id' => $lineData['variant_id'] ?? null,
                'is_ad_hoc' => $lineData['is_ad_hoc'] ?? false,
                'ad_hoc_name' => $lineData['ad_hoc_name'] ?? null,
                'ad_hoc_sku' => $lineData['ad_hoc_sku'] ?? null,
                'ipr_line_id' => $lineData['ipr_line_id'] ?? null,
                'supplier_reference' => $lineData['supplier_reference'] ?? null,
                'description_snapshot' => $snapshot['description_snapshot'] ?? $lineData['description_snapshot'] ?? null,
                'qty_ordered' => $lineData['qty_ordered'],
                'unit_price_ht' => $lineData['unit_price_ht'],
                'tax_rate_pct' => $lineData['tax_rate_pct'],
                'total_ht' => $lineHt,
                'total_tax' => $lineTax,
                'total_ttc' => $lineTtc,
                'expected_delivery_date' => $lineData['expected_delivery_date'] ?? null,
            ];

            if ($updating && ! empty($lineData['id'])) {
                $po->lines()->where('id', $lineData['id'])->update($payload);
                $keepIds[] = $lineData['id'];
            } else {
                $keepIds[] = $po->lines()->create($payload)->id;
            }

            $totalHt += $lineHt;
            $totalTax += $lineTax;
            $totalTtc += $lineTtc;
        }

        if ($updating) {
            $po->lines()->whereNotIn('id', $keepIds)->delete();
        }

        $po->update([
            'total_ht' => $totalHt,
            'total_tax' => $totalTax,
            'total_ttc' => $totalTtc,
        ]);
    }

    private function relinkSources(PurchaseOrder $po): void
    {
        InternalPurchaseRequest::query()->where('converted_to_po_id', $po->id)
            ->update(['converted_to_po_id' => null]);

        if (! empty($po->ipr_ids)) {
            InternalPurchaseRequest::query()->whereIn('id', $po->ipr_ids)->update([
                'converted_to_po_id' => $po->id,
                'status' => 'converted_to_po',
            ]);
        }

        RfqHeader::query()->where('converted_to_po_id', $po->id)
            ->update(['converted_to_po_id' => null]);

        if (! empty($po->rfq_id)) {
            RfqHeader::query()->where('id', $po->rfq_id)->update(['converted_to_po_id' => $po->id]);
        }
    }
}
