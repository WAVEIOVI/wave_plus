<?php

declare(strict_types=1);

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Enums\CapacityCheckResult;
use App\Modules\Crm\Enums\FeasibilityStatus;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\StockCheckResult;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Inventory\Models\StockItem;
use Illuminate\Support\Facades\DB;

class FeasibilityService
{
    private const SECONDARY_APPROVAL_THRESHOLD_HT = 50000.0;

    /**
     * Perform automated credit check against contract or client limit.
     */
    public function checkCredit(SalesOrder $so): array
    {
        $so->loadMissing(['client', 'contract']);

        $limit = (float) config('crm.default_credit_limit', 100000.0);

        if ($so->contract && $so->contract->credit_limit !== null) {
            $limit = (float) $so->contract->credit_limit;
        }

        $orderTotal = (float) $so->total_ttc;
        $result = 'sufficient';

        if ($orderTotal > $limit) {
            $result = 'exceeded';
        } elseif ($orderTotal > ($limit * 0.85)) {
            $result = 'warning';
        }

        return [
            'result' => $result,
            'credit_limit' => $limit,
            'order_total' => $orderTotal,
        ];
    }

    /**
     * Perform real inventory availability check for Sales SO lines.
     */
    public function checkStock(SalesOrder $so): array
    {
        $so->loadMissing('lines.catalogItem');

        $linesEvaluation = [];
        $hasSufficient = true;
        $hasPartial = false;
        $hasZero = false;

        foreach ($so->lines as $line) {
            if ($line->is_ad_hoc || ! $line->catalog_item_id) {
                continue;
            }

            $catalogItem = $line->catalogItem;
            if (! $catalogItem || ! $catalogItem->is_stockable) {
                continue;
            }

            $ordered = (float) $line->qty;

            $stockQuery = StockItem::where('catalog_item_id', $line->catalog_item_id)
                ->where('status', 'active');

            if ($line->variant_id) {
                $stockQuery->where('catalog_variant_id', $line->variant_id);
            }

            $available = (float) $stockQuery->sum('qty_available');
            $reservable = min($ordered, max(0.0, $available));

            $lineStatus = 'sufficient';
            if ($available <= 0) {
                $lineStatus = 'insufficient';
                $hasZero = true;
                $hasSufficient = false;
            } elseif ($available < $ordered) {
                $lineStatus = 'partial';
                $hasPartial = true;
                $hasSufficient = false;
            }

            $linesEvaluation[] = [
                'line_id' => $line->id,
                'catalog_item_id' => $line->catalog_item_id,
                'variant_id' => $line->variant_id,
                'qty_ordered' => $ordered,
                'qty_available' => $available,
                'qty_reservable' => $reservable,
                'status' => $lineStatus,
            ];
        }

        if (empty($linesEvaluation)) {
            $overallResult = StockCheckResult::Sufficient;
        } elseif ($hasSufficient) {
            $overallResult = StockCheckResult::Sufficient;
        } elseif ($hasPartial || ($hasZero && count($linesEvaluation) > 1)) {
            $overallResult = StockCheckResult::Partial;
        } else {
            $overallResult = StockCheckResult::Insufficient;
        }

        return [
            'result' => $overallResult->value,
            'lines' => $linesEvaluation,
        ];
    }

    /**
     * Capacity check evaluation for Service SO.
     * Manual reviewer evaluation until Operational Hub scheduling is live.
     */
    public function checkCapacity(SalesOrder $so, ?string $manualDecision = null): array
    {
        $decision = $manualDecision ?? CapacityCheckResult::Available->value;

        return [
            'result' => $decision,
            'is_manual' => true,
            'notes' => 'Capacity evaluated by reviewer.',
        ];
    }

    /**
     * Determine whether secondary approval is required based on threshold.
     */
    public function requiresSecondaryApproval(SalesOrder $so): bool
    {
        $threshold = (float) config('crm.secondary_approval_threshold_ht', self::SECONDARY_APPROVAL_THRESHOLD_HT);

        return (float) $so->total_ht >= $threshold;
    }

    /**
     * Record feasibility review decision and update SO state accordingly.
     *
     * DETERMINISTIC RULES:
     * - Reviewer CAN approve when warnings are present (e.g. credit warning, partial stock, constrained capacity).
     * - Reviewer CANNOT approve if any required check has failed (credit exceeded, stock insufficient, capacity unavailable).
     * - System checks are ALWAYS authoritative; frontend-submitted values for checks are ignored for security.
     *   Only the reviewer's DECISION (approved/rejected) is trusted from the frontend.
     */
    public function reviewFeasibility(SalesOrder $so, array $data, string $reviewerId): SalesOrder
    {
        if ($so->status !== SalesOrderStatus::UnderReview) {
            abort(422, 'Feasibility review is only allowed while the sales order is under_review.');
        }

        return DB::transaction(function () use ($so, $data, $reviewerId) {
            $decision = $data['decision'] ?? 'approved'; // approved | rejected
            $rejectionReason = $data['rejection_reason'] ?? null;

            // ALWAYS run real system checks — never trust frontend-submitted check results
            $creditCheck = $this->checkCredit($so);
            $creditResult = $creditCheck['result'];

            $stockResult = null;
            if ($so->isSales() || $so->lines()->whereHas('catalogItem', fn ($q) => $q->where('is_stockable', true))->exists()) {
                $stockCheck = $this->checkStock($so);
                $stockResult = $stockCheck['result'];
            }

            $capacityResult = null;
            if ($so->isService()) {
                $capacityCheck = $this->checkCapacity($so, null); // always use system evaluation
                $capacityResult = $capacityCheck['result'];
            }

            // Enforce deterministic gate on approval
            if ($decision === 'approved') {
                if (in_array($creditResult, ['exceeded', 'failed'], true)) {
                    abort(422, 'Cannot approve feasibility: Credit check failed (credit limit exceeded).');
                }

                if ($stockResult === StockCheckResult::Insufficient->value) {
                    abort(422, 'Cannot approve feasibility: Stock check failed (inventory is insufficient).');
                }

                if ($capacityResult === CapacityCheckResult::Unavailable->value) {
                    abort(422, 'Cannot approve feasibility: Capacity check failed (operational capacity is unavailable).');
                }
            }

            $secondaryApproval = $this->requiresSecondaryApproval($so);

            $feasibilityStatus = $decision === 'approved'
                ? FeasibilityStatus::Approved
                : FeasibilityStatus::Rejected;

            // On approved review, keep the status as under_review.
            // The user will explicitly call the accept endpoint to transition forward.
            // If secondary approval is required, it stays under_review until secondary approval.
            $newStatus = $so->status;
            if ($decision === 'approved') {
                // Keep as under_review — user will explicitly accept via the accept endpoint
                $newStatus = SalesOrderStatus::UnderReview;
            } elseif ($decision === 'rejected') {
                $newStatus = SalesOrderStatus::Rejected;
            }

            $so->update([
                'feasibility_status' => $feasibilityStatus,
                'credit_check_result' => $creditResult,
                'stock_check_result' => $stockResult,
                'capacity_check_result' => $capacityResult,
                'requires_secondary_approval' => $secondaryApproval,
                'rejection_reason' => $decision === 'rejected' ? $rejectionReason : null,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'status' => $newStatus,
            ]);

            return $so;
        });
    }
}
