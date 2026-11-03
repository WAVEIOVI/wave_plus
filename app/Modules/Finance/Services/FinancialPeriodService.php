<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinancialPeriod;
use Illuminate\Validation\ValidationException;

/**
 * Guards all financial write operations against closed periods.
 * Call assertPeriodOpen() before any create/update on financial records.
 */
class FinancialPeriodService
{
    /**
     * Find (or auto-create) the open period for today's date.
     */
    public function currentPeriod(): FinancialPeriod
    {
        return FinancialPeriod::findOrCreateForDate(now());
    }

    /**
     * Find (or auto-create) period for a given date.
     */
    public function periodForDate(\DateTimeInterface $date): FinancialPeriod
    {
        return FinancialPeriod::findOrCreateForDate($date);
    }

    /**
     * Assert that the period for a given date is still open.
     *
     * @throws ValidationException
     */
    public function assertPeriodOpen(\DateTimeInterface $date): FinancialPeriod
    {
        $period = $this->periodForDate($date);

        if ($period->is_closed) {
            throw ValidationException::withMessages([
                'financial_period' => [
                    "The financial period '{$period->name}' is {$period->status}. "
                    . 'You cannot modify financial transactions in a closed period.',
                ],
            ]);
        }

        return $period;
    }

    /**
     * Close a period — prevents further posting to it.
     */
    public function closePeriod(FinancialPeriod $period, string $closedBy): FinancialPeriod
    {
        if ($period->is_closed) {
            throw ValidationException::withMessages([
                'financial_period' => ["Period '{$period->name}' is already {$period->status}."],
            ]);
        }

        $period->update([
            'status'    => 'closed',
            'closed_at' => now(),
            'closed_by' => $closedBy,
        ]);

        return $period->fresh();
    }

    /**
     * Re-open a closed period (restricted action).
     */
    public function reopenPeriod(FinancialPeriod $period): FinancialPeriod
    {
        $period->update([
            'status'    => 'open',
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $period->fresh();
    }

    /**
     * List periods, most recent first.
     */
    public function list(array $filters = [])
    {
        $query = FinancialPeriod::orderByDesc('year')->orderByDesc('month');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 24);
    }
}
