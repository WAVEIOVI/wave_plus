<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\CostCenter;
use App\Modules\Finance\Models\Expense;
use Illuminate\Database\Eloquent\Collection;

class CostCenterService
{
    public function list(array $filters = []): Collection
    {
        $query = CostCenter::with(['parent', 'children'])
            ->orderBy('code');

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->get();
    }

    public function create(array $data, string $createdBy): CostCenter
    {
        return CostCenter::create([
            'code'        => $data['code'],
            'name'        => $data['name'],
            'parent_id'   => $data['parent_id'] ?? null,
            'manager_id'  => $data['manager_id'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? true,
            'created_by'  => $createdBy,
            'updated_by'  => $createdBy,
        ]);
    }

    public function update(CostCenter $costCenter, array $data, string $updatedBy): CostCenter
    {
        $costCenter->update(array_merge($data, [
            'updated_by' => $updatedBy,
        ]));

        return $costCenter->fresh();
    }

    public function getPerformance(string $costCenterId): array
    {
        $costCenter = CostCenter::findOrFail($costCenterId);

        $expenses = Expense::posted()
            ->where('cost_center_id', $costCenterId)
            ->get();

        $totalExpenses = (float) $expenses->sum('amount_ht');
        $byCategory = $expenses->groupBy('category')->map(fn($group) => round((float) $group->sum('amount_ht'), 3));

        return [
            'cost_center'    => $costCenter,
            'total_expenses' => round($totalExpenses, 3),
            'by_category'    => $byCategory,
            'expense_count'  => $expenses->count(),
        ];
    }
}
