<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

class ProfitabilityService
{
    /**
     * Profitability analysis for a specific Sales Order.
     */
    public function forSalesOrder(string $salesOrderId): array
    {
        $so = SalesOrder::with(['client'])->find($salesOrderId);

        if (!$so) {
            return [
                'error' => 'Sales Order not found',
            ];
        }

        // Invoiced revenue from Finance module
        $invoices = SalesInvoice::where(function ($q) use ($so) {
            $q->where('source_id', $so->id)
              ->orWhere('source_reference', $so->reference);
        })->posted()->get();

        $invoicedRevenueHt = (float) $invoices->sum('subtotal_ht');
        $invoicedRevenueTtc = (float) $invoices->sum('total_ttc');
        $bookedRevenueHt = (float) ($so->total_ht ?? 0);

        // Effective revenue to measure against
        $revenue = $invoicedRevenueHt > 0 ? $invoicedRevenueHt : $bookedRevenueHt;

        // Actual operational expenses assigned to this SO
        $expenses = Expense::posted()
            ->where('sales_order_id', $so->id)
            ->get();

        $costs = [
            'materials'       => 0.0,
            'labor'           => 0.0,
            'logistics'       => 0.0,
            'vehicle_fleet'   => 0.0,
            'subcontracting'  => 0.0,
            'other'           => 0.0,
        ];

        foreach ($expenses as $exp) {
            $cat = strtolower((string) $exp->category);
            $amount = (float) $exp->amount_ht;

            if (str_contains($cat, 'material') || str_contains($cat, 'part')) {
                $costs['materials'] += $amount;
            } elseif (str_contains($cat, 'labor') || str_contains($cat, 'salary')) {
                $costs['labor'] += $amount;
            } elseif (str_contains($cat, 'logistic') || str_contains($cat, 'delivery') || str_contains($cat, 'freight')) {
                $costs['logistics'] += $amount;
            } elseif (str_contains($cat, 'fuel') || str_contains($cat, 'vehicle') || str_contains($cat, 'fleet') || !empty($exp->vehicle_id)) {
                $costs['vehicle_fleet'] += $amount;
            } elseif (str_contains($cat, 'subcontract') || str_contains($cat, 'provider')) {
                $costs['subcontracting'] += $amount;
            } else {
                $costs['other'] += $amount;
            }
        }

        $totalCosts = array_sum($costs);
        $grossContribution = $revenue - $totalCosts;
        $marginPercentage = $revenue > 0 ? round(($grossContribution / $revenue) * 100, 2) : 0.0;

        return [
            'order' => [
                'id'         => $so->id,
                'reference'  => $so->reference,
                'client'     => $so->client?->commercial_name ?: $so->client?->legal_name ?: 'N/A',
                'status'     => $so->status,
                'currency'   => $so->currency ?? 'TND',
            ],
            'revenue' => [
                'booked_ht'     => round($bookedRevenueHt, 3),
                'invoiced_ht'   => round($invoicedRevenueHt, 3),
                'invoiced_ttc'  => round($invoicedRevenueTtc, 3),
                'effective_ht'  => round($revenue, 3),
                'invoice_count' => $invoices->count(),
            ],
            'costs' => array_map(fn($v) => round($v, 3), $costs),
            'total_cost'         => round($totalCosts, 3),
            'gross_contribution' => round($grossContribution, 3),
            'margin_percentage'  => $marginPercentage,
            'is_cost_complete'   => $expenses->isNotEmpty(),
        ];
    }

    /**
     * Profitability overview for multiple orders / missions with filters.
     */
    public function getOverview(array $filters = []): array
    {
        $soQuery = SalesOrder::query()->select('id', 'reference', 'client_id', 'total_ht', 'total_ttc', 'status', 'created_at');

        if (!empty($filters['client_id'])) {
            $soQuery->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['from_date'])) {
            $soQuery->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $soQuery->whereDate('created_at', '<=', $filters['to_date']);
        }

        $orders = $soQuery->orderByDesc('created_at')->limit(50)->get();

        $rows = [];
        $totalRevenue = 0.0;
        $totalCost = 0.0;

        foreach ($orders as $so) {
            $item = $this->forSalesOrder((string) $so->id);
            if (!isset($item['error'])) {
                $rows[] = $item;
                $totalRevenue += $item['revenue']['effective_ht'];
                $totalCost += $item['total_cost'];
            }
        }

        $grossContribution = $totalRevenue - $totalCost;
        $avgMargin = $totalRevenue > 0 ? round(($grossContribution / $totalRevenue) * 100, 2) : 0.0;

        return [
            'summary' => [
                'total_revenue'      => round($totalRevenue, 3),
                'total_cost'         => round($totalCost, 3),
                'gross_contribution' => round($grossContribution, 3),
                'margin_percentage'  => $avgMargin,
                'items_count'        => count($rows),
            ],
            'items' => $rows,
        ];
    }
}
