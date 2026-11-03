<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\SalesInvoice;
use App\Modules\Finance\Models\SalesInvoiceLine;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInvoiceService
{
    public function __construct(
        private readonly FinancialPeriodService $periodService,
        private readonly SequenceService $sequenceService,
        private readonly FinancialTransactionService $txService,
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = SalesInvoice::with(['client', 'period', 'lines'])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('source_reference', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('company_name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['overdue'])) {
            $query->overdue();
        }

        if (! empty($filters['from_date'])) {
            $query->where('invoice_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('invoice_date', '<=', $filters['to_date']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function find(string $id): SalesInvoice
    {
        return SalesInvoice::with(['client', 'period', 'lines', 'costCenter', 'payments.payment'])
            ->findOrFail($id);
    }

    public function create(array $data, string $createdBy): SalesInvoice
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $date = Carbon::parse($data['invoice_date'] ?? now());
            $period = $this->periodService->assertPeriodOpen($date);

            $type = $data['type'] ?? 'invoice';
            $prefix = match ($type) {
                'credit_note' => 'CN',
                'debit_note' => 'DN',
                default => 'INV',
            };

            $invoiceNumber = $this->sequenceService->next($prefix);

            $invoice = SalesInvoice::create([
                'invoice_number' => $invoiceNumber,
                'type' => $type,
                'client_id' => $data['client_id'],
                'financial_period_id' => $period->id,
                'invoice_date' => $date->toDateString(),
                'due_date' => ! empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : $date->copy()->addDays(30)->toDateString(),
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'source_reference' => $data['source_reference'] ?? null,
                'related_invoice_id' => $data['related_invoice_id'] ?? null,
                'currency' => $data['currency'] ?? 'TND',
                'subtotal_ht' => 0,
                'tax_amount' => 0,
                'total_ttc' => 0,
                'amount_paid' => 0,
                'amount_outstanding' => 0,
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'payment_terms' => $data['payment_terms'] ?? '30_days',
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'client_notes' => $data['client_notes'] ?? null,
                'created_by' => $createdBy,
                'updated_by' => $createdBy,
            ]);

            $subtotal = 0;
            $taxTotal = 0;

            if (! empty($data['lines']) && is_array($data['lines'])) {
                foreach ($data['lines'] as $index => $line) {
                    $qty = (float) ($line['quantity'] ?? 1);
                    $price = (float) ($line['unit_price'] ?? 0);
                    $lineSubtotal = round($qty * $price, 3);
                    $taxRate = (float) ($line['tax_rate'] ?? 0);
                    $lineTax = round($lineSubtotal * ($taxRate / 100), 3);
                    $lineTotal = $lineSubtotal + $lineTax;

                    SalesInvoiceLine::create([
                        'sales_invoice_id' => $invoice->id,
                        'line_number' => $index + 1,
                        'item_type' => $line['item_type'] ?? 'service',
                        'item_id' => $line['item_id'] ?? null,
                        'description' => $line['description'] ?? 'Line '.($index + 1),
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'subtotal_ht' => $lineSubtotal,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $lineTax,
                        'total_ttc' => $lineTotal,
                        'cost_center_id' => $line['cost_center_id'] ?? $invoice->cost_center_id,
                    ]);

                    $subtotal += $lineSubtotal;
                    $taxTotal += $lineTax;
                }
            }

            $totalTtc = $subtotal + $taxTotal;

            $invoice->update([
                'subtotal_ht' => $subtotal,
                'tax_amount' => $taxTotal,
                'total_ttc' => $totalTtc,
                'amount_outstanding' => $totalTtc,
            ]);

            if (! empty($data['issue_immediately'])) {
                $this->issue($invoice, $createdBy);
            }

            return $invoice->fresh(['lines', 'client', 'period']);
        });
    }

    public function issue(SalesInvoice $invoice, string $issuedBy): SalesInvoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft invoices can be issued.'],
            ]);
        }

        $date = Carbon::parse($invoice->invoice_date);
        $period = $this->periodService->assertPeriodOpen($date);

        return DB::transaction(function () use ($invoice, $issuedBy, $period) {
            $invoice->update([
                'status' => 'issued',
                'financial_period_id' => $period->id,
                'posted_at' => now(),
                'updated_by' => $issuedBy,
            ]);

            // Post central financial transaction
            $this->txService->record([
                'date' => $invoice->invoice_date,
                'type' => $invoice->type === 'credit_note' ? 'credit_note' : 'sales_invoice',
                'direction' => $invoice->type === 'credit_note' ? 'out' : 'in',
                'amount' => $invoice->total_ttc,
                'source_type' => SalesInvoice::class,
                'source_id' => $invoice->id,
                'party_type' => 'client',
                'party_id' => $invoice->client_id,
                'party_name' => $invoice->client?->company_name,
                'cost_center_id' => $invoice->cost_center_id,
                'reference' => $invoice->invoice_number,
                'description' => "Sales Invoice {$invoice->invoice_number}",
                'period_id' => $period->id,
            ], $issuedBy);

            return $invoice->fresh();
        });
    }

    public function void(SalesInvoice $invoice, string $reason, string $voidedBy): SalesInvoice
    {
        if ($invoice->status === 'void') {
            throw ValidationException::withMessages([
                'status' => ['Invoice is already voided.'],
            ]);
        }

        if ($invoice->amount_paid > 0) {
            throw ValidationException::withMessages([
                'amount_paid' => ['Cannot void an invoice with recorded payments. Please unallocate or refund first.'],
            ]);
        }

        return DB::transaction(function () use ($invoice, $reason, $voidedBy) {
            $invoice->update([
                'status' => 'void',
                'voided_at' => now(),
                'voided_by' => $voidedBy,
                'void_reason' => $reason,
                'updated_by' => $voidedBy,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Calculate Aging breakdown for Receivables.
     */
    public function calculateAging(?string $clientId = null): array
    {
        $query = SalesInvoice::posted()
            ->where('payment_status', '!=', 'paid')
            ->where('type', 'invoice');

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $invoices = $query->get();
        $now = Carbon::now();

        $aging = [
            'current' => 0.0,
            '1_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            '90_plus' => 0.0,
            'total' => 0.0,
        ];

        foreach ($invoices as $inv) {
            $balance = (float) $inv->amount_outstanding;
            $aging['total'] += $balance;

            $dueDate = Carbon::parse($inv->due_date);
            if ($dueDate->isFuture() || $dueDate->isToday()) {
                $aging['current'] += $balance;
            } else {
                $days = $now->diffInDays($dueDate);
                if ($days <= 30) {
                    $aging['1_30'] += $balance;
                } elseif ($days <= 60) {
                    $aging['31_60'] += $balance;
                } elseif ($days <= 90) {
                    $aging['61_90'] += $balance;
                } else {
                    $aging['90_plus'] += $balance;
                }
            }
        }

        return array_map(fn ($v) => round($v, 3), $aging);
    }
}
