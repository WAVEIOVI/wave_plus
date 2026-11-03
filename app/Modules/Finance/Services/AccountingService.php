<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public function __construct(
        private readonly FinancialPeriodService $periodService,
        private readonly SequenceService $sequenceService,
    ) {}

    public function getChartOfAccounts(): Collection
    {
        return ChartOfAccount::with(['children'])
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();
    }

    public function listAccounts(): Collection
    {
        return ChartOfAccount::orderBy('code')->get();
    }

    public function createAccount(array $data, string $createdBy): ChartOfAccount
    {
        return ChartOfAccount::create([
            'code'              => $data['code'],
            'name'              => $data['name'],
            'account_type'      => $data['account_type'], // asset | liability | equity | revenue | expense
            'parent_id'         => $data['parent_id'] ?? null,
            'is_reconciliation' => $data['is_reconciliation'] ?? false,
            'is_active'         => $data['is_active'] ?? true,
            'description'       => $data['description'] ?? null,
            'created_by'        => $createdBy,
            'updated_by'        => $createdBy,
        ]);
    }

    public function listJournalEntries(array $filters = []): LengthAwarePaginator
    {
        $query = JournalEntry::with(['lines.account', 'period'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('entry_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('entry_date', '<=', $filters['to_date']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function createJournalEntry(array $data, string $createdBy): JournalEntry
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $date = Carbon::parse($data['entry_date'] ?? now());
            $period = $this->periodService->assertPeriodOpen($date);

            $lines = $data['lines'] ?? [];
            if (count($lines) < 2) {
                throw ValidationException::withMessages([
                    'lines' => ['A journal entry must contain at least 2 lines (double-entry accounting).'],
                ]);
            }

            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($lines as $line) {
                $totalDebit += (float) ($line['debit'] ?? 0);
                $totalCredit += (float) ($line['credit'] ?? 0);
            }

            // Balanced entry check
            if (abs($totalDebit - $totalCredit) > 0.001) {
                throw ValidationException::withMessages([
                    'balance' => [
                        "Journal entry is not balanced. Total Debits: {$totalDebit}, Total Credits: {$totalCredit}.",
                    ],
                ]);
            }

            $entryNumber = $this->sequenceService->next('JE');

            $entry = JournalEntry::create([
                'entry_number'        => $entryNumber,
                'entry_date'          => $date->toDateString(),
                'financial_period_id' => $period->id,
                'status'              => $data['status'] ?? 'draft',
                'reference_type'      => $data['reference_type'] ?? null,
                'reference_id'        => $data['reference_id'] ?? null,
                'notes'               => $data['notes'] ?? null,
                'total_debit'         => $totalDebit,
                'total_credit'        => $totalCredit,
                'created_by'          => $createdBy,
                'updated_by'          => $createdBy,
            ]);

            foreach ($lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'cost_center_id'   => $line['cost_center_id'] ?? null,
                    'description'      => $line['description'] ?? null,
                    'debit'            => (float) ($line['debit'] ?? 0),
                    'credit'           => (float) ($line['credit'] ?? 0),
                ]);
            }

            if (($data['status'] ?? null) === 'posted') {
                $entry->update([
                    'posted_at' => now(),
                    'posted_by' => $createdBy,
                ]);
            }

            return $entry->fresh(['lines.account', 'period']);
        });
    }
}
