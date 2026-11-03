<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\FinancialTransaction;

/**
 * Records financial events to the central financial_transactions log.
 * This is consumed by Dashboard and Reporting.
 */
class FinancialTransactionService
{
    public function __construct(
        private readonly SequenceService $sequenceService,
    ) {}

    public function record(array $data, string $createdBy): FinancialTransaction
    {
        return FinancialTransaction::create([
            'transaction_number'  => $this->sequenceService->next('FTX'),
            'transaction_date'    => $data['date'] instanceof \DateTimeInterface
                ? $data['date']->format('Y-m-d')
                : $data['date'],
            'financial_period_id' => $data['period_id'] ?? null,
            'type'                => $data['type'],
            'direction'           => $data['direction'],
            'currency'            => $data['currency'] ?? 'TND',
            'amount'              => $data['amount'],
            'source_type'         => $data['source_type'],
            'source_id'           => $data['source_id'],
            'party_type'          => $data['party_type'] ?? null,
            'party_id'            => $data['party_id'] ?? null,
            'party_name'          => $data['party_name'] ?? null,
            'cost_center_id'      => $data['cost_center_id'] ?? null,
            'reference'           => $data['reference'] ?? null,
            'description'         => $data['description'] ?? null,
            'status'              => 'posted',
            'created_by'          => $createdBy,
        ]);
    }
}
