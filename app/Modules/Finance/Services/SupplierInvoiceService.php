<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\SupplierInvoice;
use App\Modules\Finance\Models\SupplierInvoiceLine;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierInvoiceService
{
    public function __construct(
        private readonly FinancialPeriodService $periodService,
        private readonly SequenceService $sequenceService,
        private readonly FinancialTransactionService $txService,
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = SupplierInvoice::with(['supplier', 'period', 'lines'])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('supplier_invoice_ref', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('company_name', 'like', "%{$search}%");
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

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
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

    public function find(string $id): SupplierInvoice
    {
        return SupplierInvoice::with(['supplier', 'period', 'lines', 'costCenter', 'paymentAllocations.payment'])
            ->findOrFail($id);
    }

    public function create(array $data, string $createdBy): SupplierInvoice
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $date = Carbon::parse($data['invoice_date'] ?? now());
            $period = $this->periodService->assertPeriodOpen($date);

            $type = $data['type'] ?? 'standard';
            $invoiceNumber = $this->sequenceService->next('SINV');

            $invoice = SupplierInvoice::create([
                'invoice_number' => $invoiceNumber,
                'supplier_invoice_ref' => $data['supplier_invoice_ref'] ?? null,
                'type' => $type,
                'supplier_id' => $data['supplier_id'],
                'financial_period_id' => $period->id,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'grn_id' => $data['grn_id'] ?? null,
                'invoice_date' => $date->toDateString(),
                'due_date' => ! empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : $date->copy()->addDays(30)->toDateString(),
                'currency' => $data['currency'] ?? 'TND',
                'subtotal_ht' => 0,
                'tax_amount' => 0,
                'total_ttc' => 0,
                'amount_paid' => 0,
                'amount_outstanding' => 0,
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'matching_status' => ! empty($data['purchase_order_id']) ? 'matched' : 'unmatched',
                'payment_terms' => $data['payment_terms'] ?? '30_days',
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'notes' => $data['notes'] ?? null,
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

                    SupplierInvoiceLine::create([
                        'supplier_invoice_id' => $invoice->id,
                        'line_number' => $index + 1,
                        'item_id' => $line['item_id'] ?? null,
                        'po_line_id' => $line['po_line_id'] ?? null,
                        'description' => $line['description'] ?? 'Line '.($index + 1),
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'subtotal_ht' => $lineSubtotal,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $lineTax,
                        'total_ttc' => $lineTotal,
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

            return $invoice->fresh(['lines', 'supplier', 'period']);
        });
    }

    public function approve(SupplierInvoice $invoice, string $approvedBy): SupplierInvoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft supplier invoices can be approved.'],
            ]);
        }

        $date = Carbon::parse($invoice->invoice_date);
        $period = $this->periodService->assertPeriodOpen($date);

        return DB::transaction(function () use ($invoice, $approvedBy, $period) {
            $invoice->update([
                'status' => 'approved',
                'financial_period_id' => $period->id,
                'posted_at' => now(),
                'updated_by' => $approvedBy,
            ]);

            // Post central financial transaction (payable)
            $this->txService->record([
                'date' => $invoice->invoice_date,
                'type' => 'supplier_invoice',
                'direction' => 'out',
                'amount' => $invoice->total_ttc,
                'source_type' => SupplierInvoice::class,
                'source_id' => $invoice->id,
                'party_type' => 'supplier',
                'party_id' => $invoice->supplier_id,
                'party_name' => $invoice->supplier?->company_name,
                'cost_center_id' => $invoice->cost_center_id,
                'reference' => $invoice->invoice_number,
                'description' => "Supplier Invoice {$invoice->invoice_number} (Ref: {$invoice->supplier_invoice_ref})",
                'period_id' => $period->id,
            ], $approvedBy);

            return $invoice->fresh();
        });
    }

    public function calculateAging(?string $supplierId = null): array
    {
        $query = SupplierInvoice::where('status', 'approved')
            ->where('payment_status', '!=', 'paid');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
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
