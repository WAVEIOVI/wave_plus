<?php

namespace App\Modules\Procurement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Controllers\Concerns\AuthorizesProcurementRequests;
use App\Modules\Procurement\Models\GoodsReceiptNote;
use App\Modules\Procurement\Models\InternalPurchaseRequest;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\RfqHeader;
use App\Modules\Procurement\Models\RfqResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProcurementDashboardController extends Controller
{
    use AuthorizesProcurementRequests;

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        // ── IPR Metrics ───────────────────────────────────────────────────────
        $iprBase = InternalPurchaseRequest::query();
        $iprMetrics = [
            'total' => (clone $iprBase)->count(),
            'pending_approval' => (clone $iprBase)->where('status', 'pending_approval')->count(),
            'approved' => (clone $iprBase)->where('status', 'approved')->count(),
            'converted_to_po' => (clone $iprBase)->where('status', 'converted_to_po')->count(),
            'urgent' => (clone $iprBase)->whereIn('urgency', ['urgent', 'critical'])->count(),
        ];

        // ── RFQ Metrics ───────────────────────────────────────────────────────
        $rfqBase = RfqHeader::query();
        $rfqMetrics = [
            'total' => (clone $rfqBase)->count(),
            'draft' => (clone $rfqBase)->where('status', 'draft')->count(),
            'awaiting_responses' => (clone $rfqBase)->where('status', 'awaiting_responses')->count(),
            'under_evaluation' => (clone $rfqBase)->where('status', 'under_evaluation')->count(),
            'awarded' => (clone $rfqBase)->where('status', 'awarded')->count(),
            'cancelled' => (clone $rfqBase)->where('status', 'cancelled')->count(),
        ];

        // ── PO Metrics ────────────────────────────────────────────────────────
        $poBase = PurchaseOrder::query();
        $poMetrics = [
            'total' => (clone $poBase)->count(),
            'pending_approval' => (clone $poBase)->where('status', 'pending_approval')->count(),
            'approved' => (clone $poBase)->where('status', 'approved')->count(),
            'sent' => (clone $poBase)->where('status', 'sent')->count(),
            'acknowledged' => (clone $poBase)->where('status', 'acknowledged')->count(),
            'in_delivery' => (clone $poBase)->where('status', 'in_delivery')->count(),
            'partially_received' => (clone $poBase)->where('status', 'partially_received')->count(),
            'fully_received' => (clone $poBase)->where('status', 'fully_received')->count(),
            'closed' => (clone $poBase)->where('status', 'closed')->count(),
            'total_value_ht' => (clone $poBase)->sum('total_ht'),
            'open_pos_value_ht' => (clone $poBase)
                ->whereIn('status', ['approved', 'sent', 'acknowledged', 'in_delivery', 'partially_received'])
                ->sum('total_ht'),
        ];

        // ── GRN Metrics ───────────────────────────────────────────────────────
        $grnBase = GoodsReceiptNote::query();
        $grnMetrics = [
            'total' => (clone $grnBase)->count(),
            'draft' => (clone $grnBase)->where('status', 'draft')->count(),
            'under_qc' => (clone $grnBase)->where('status', 'under_qc')->count(),
            'confirmed' => (clone $grnBase)->where('status', 'confirmed')->count(),
            'partially_rejected' => (clone $grnBase)->where('status', 'partially_rejected')->count(),
            'fully_rejected' => (clone $grnBase)->where('status', 'fully_rejected')->count(),
            'stocked' => (clone $grnBase)->where('status', 'stocked')->count(),
            'partial_deliveries' => (clone $grnBase)->where('is_partial_delivery', true)->count(),
            'late_deliveries' => (clone $grnBase)->where('delivery_on_time', false)->count(),
        ];

        // ── Supplier Metrics ──────────────────────────────────────────────────
        $supplierMetrics = [
            'active' => \App\Modules\BusinessPartners\Models\Supplier::where('status', 'active')->count(),
            'total' => \App\Modules\BusinessPartners\Models\Supplier::count(),
        ];

        // ── Monthly Trends (last 6 months) ────────────────────────────────────
        $monthlyTrends = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();
            $monthLabel = $month->format('M Y');

            $monthlyTrends[] = [
                'month' => $monthLabel,
                'iprs' => (clone $iprBase)->whereBetween('created_at', [$month, $monthEnd])->count(),
                'rfqs' => (clone $rfqBase)->whereBetween('created_at', [$month, $monthEnd])->count(),
                'pos' => (clone $poBase)->whereBetween('created_at', [$month, $monthEnd])->count(),
                'grns' => (clone $grnBase)->whereBetween('created_at', [$month, $monthEnd])->count(),
                'po_value' => (clone $poBase)->whereBetween('created_at', [$month, $monthEnd])->sum('total_ht'),
            ];
        }

        // ── Supplier Performance ──────────────────────────────────────────────
        $supplierPerformance = DB::table('purchase_orders')
            ->select([
                'purchase_orders.supplier_id',
                DB::raw('COUNT(purchase_orders.id) as po_count'),
                DB::raw('SUM(purchase_orders.total_ht) as total_value_ht'),
                DB::raw('SUM(CASE WHEN purchase_orders.status = "fully_received" THEN 1 ELSE 0 END) as fulfilled_count'),
                DB::raw('SUM(CASE WHEN purchase_orders.status = "partially_received" THEN 1 ELSE 0 END) as partial_count'),
                DB::raw('SUM(CASE WHEN purchase_orders.status = "closed" THEN 1 ELSE 0 END) as closed_count'),
            ])
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->whereIn('purchase_orders.status', ['approved', 'sent', 'acknowledged', 'in_delivery', 'partially_received', 'fully_received', 'closed'])
            ->groupBy('purchase_orders.supplier_id')
            ->orderByDesc('total_value_ht')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $total = $row->po_count;
                $onTimeRate = $total > 0 ? round((($row->closed_count + $row->fulfilled_count) / $total) * 100, 1) : 0;
                $partialRate = $total > 0 ? round(($row->partial_count / $total) * 100, 1) : 0;

                return [
                    'supplier_id' => $row->supplier_id,
                    'supplier_name' => \App\Modules\BusinessPartners\Models\Supplier::find($row->supplier_id)?->legal_name ?? 'Unknown',
                    'po_count' => $row->po_count,
                    'total_value_ht' => (float) $row->total_value_ht,
                    'fulfillment_rate' => $onTimeRate,
                    'partial_rate' => $partialRate,
                    'avg_days_late' => 0, // Would need GRN data to calculate
                ];
            });

        // ── Attention Items ───────────────────────────────────────────────────
        $attentionItems = [
            'iprs_awaiting_approval' => $iprMetrics['pending_approval'],
            'rfqs_awaiting_responses' => $rfqMetrics['awaiting_responses'],
            'rfqs_under_evaluation' => $rfqMetrics['under_evaluation'],
            'pos_pending_approval' => $poMetrics['pending_approval'],
            'pos_in_delivery' => $poMetrics['in_delivery'],
            'pos_partially_received' => $poMetrics['partially_received'],
            'grns_under_qc' => $grnMetrics['under_qc'],
            'grns_partial_deliveries' => $grnMetrics['partial_deliveries'],
            'grns_late_deliveries' => $grnMetrics['late_deliveries'],
        ];

        return response()->json([
            'data' => [
                'ipr' => $iprMetrics,
                'rfq' => $rfqMetrics,
                'po' => $poMetrics,
                'grn' => $grnMetrics,
                'supplier' => $supplierMetrics,
                'monthly_trends' => $monthlyTrends,
                'supplier_performance' => $supplierPerformance,
                'attention_items' => $attentionItems,
            ],
        ]);
    }
}