<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Budget;
use App\Modules\Finance\Models\BudgetLine;
use App\Modules\Finance\Models\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Budget::with(['costCenter', 'lines'])
            ->orderByDesc('year')
            ->orderByDesc('id');

        if (!empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['cost_center_id'])) {
            $query->where('cost_center_id', $filters['cost_center_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function find(string $id): Budget
    {
        return Budget::with(['costCenter', 'lines', 'creator'])->findOrFail($id);
    }

    public function create(array $data, string $createdBy): Budget
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $budget = Budget::create([
                'name'            => $data['name'],
                'type'            => $data['type'] ?? 'annual',
                'year'            => (int) ($data['year'] ?? now()->year),
                'month'           => isset($data['month']) ? (int) $data['month'] : null,
                'quarter'         => isset($data['quarter']) ? (int) $data['quarter'] : null,
                'cost_center_id'  => $data['cost_center_id'] ?? null,
                'total_budget'    => 0,
                'total_spent'     => 0,
                'total_remaining' => 0,
                'status'          => $data['status'] ?? 'draft',
                'notes'           => $data['notes'] ?? null,
                'created_by'      => $createdBy,
                'updated_by'      => $createdBy,
            ]);

            $totalBudget = 0.0;
            if (!empty($data['lines']) && is_array($data['lines'])) {
                foreach ($data['lines'] as $line) {
                    $amount = (float) ($line['budgeted_amount'] ?? 0);
                    BudgetLine::create([
                        'budget_id'       => $budget->id,
                        'category'        => $line['category'],
                        'description'     => $line['description'] ?? null,
                        'budgeted_amount' => $amount,
                        'actual_amount'   => 0,
                        'variance'        => $amount,
                    ]);
                    $totalBudget += $amount;
                }
            }

            $budget->update([
                'total_budget'    => $totalBudget,
                'total_remaining' => $totalBudget,
            ]);

            return $budget->fresh(['lines', 'costCenter']);
        });
    }

    /**
     * Recalculate actual spending from real posted Expenses against budget lines.
     */
    public function recalculateActuals(Budget $budget): Budget
    {
        return DB::transaction(function () use ($budget) {
            $totalSpent = 0.0;

            foreach ($budget->lines as $line) {
                $actual = (float) Expense::posted()
                    ->when($budget->cost_center_id, fn($q) => $q->where('cost_center_id', $budget->cost_center_id))
                    ->whereYear('expense_date', $budget->year)
                    ->when($budget->month, fn($q) => $q->whereMonth('expense_date', $budget->month))
                    ->where('category', $line->category)
                    ->sum('amount_ht');

                $variance = (float) $line->budgeted_amount - $actual;

                $line->update([
                    'actual_amount' => $actual,
                    'variance'      => $variance,
                ]);

                $totalSpent += $actual;
            }

            $budget->update([
                'total_spent'     => $totalSpent,
                'total_remaining' => (float) $budget->total_budget - $totalSpent,
            ]);

            return $budget->fresh(['lines', 'costCenter']);
        });
    }
}
