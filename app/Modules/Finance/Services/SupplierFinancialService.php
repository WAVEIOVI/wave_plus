<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\SupplierInvoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SupplierFinancialService
{
    public function __construct(
        private readonly SupplierInvoiceService $supplierInvoiceService,
    ) {}

    public function listSuppliers(array $filters = []): LengthAwarePaginator
    {
        $query = Supplier::query()
            ->with(['contacts' => fn($q) => $q->where('is_primary', true)->select('id', 'contactable_id', 'contactable_type', 'phone', 'email')])
            ->select('id', 'legal_name', 'commercial_name', 'primary_phone', 'primary_email', 'payment_terms');

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('legal_name', 'like', "%{$s}%")
                    ->orWhere('commercial_name', 'like', "%{$s}%");
            });
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15));

        // Attach financial totals to each supplier
        $paginator->getCollection()->transform(function ($supplier) {
            $invoices = SupplierInvoice::where('supplier_id', $supplier->id)->get();
            $approved = $invoices->where('status', 'approved');

            $totalInvoiced = (float) $invoices->sum('total_ttc');
            $outstanding = (float) $approved->sum('amount_outstanding');
            $overdue = (float) $approved->filter(fn ($i) => $i->is_overdue)->sum('amount_outstanding');

            // Extract contact info from primary contact or fallback to supplier fields
            $primaryContact = $supplier->contacts->first();
            $company_name = $supplier->commercial_name ?: $supplier->legal_name;
            $phone = $primaryContact?->phone ?? $supplier->primary_phone;
            $email = $primaryContact?->email ?? $supplier->primary_email;

            // Add computed fields to maintain API contract
            $supplier->company_name = $company_name;
            $supplier->phone = $phone;
            $supplier->email = $email;

            $supplier->financial_summary = [
                'total_invoiced' => round($totalInvoiced, 3),
                'outstanding' => round($outstanding, 3),
                'overdue' => round($overdue, 3),
            ];

            // Unset the relation to clean up response
            unset($supplier->contacts);

            return $supplier;
        });

        return $paginator;
    }

    public function getAccountDetails(string $supplierId): array
    {
        $supplier = Supplier::with(['contacts' => fn($q) => $q->where('is_primary', true)->select('id', 'contactable_id', 'contactable_type', 'phone', 'email')])
            ->findOrFail($supplierId);

        $invoices = SupplierInvoice::where('supplier_id', $supplierId)->orderByDesc('invoice_date')->get();
        $approved = $invoices->where('status', 'approved');

        $totalInvoiced = (float) $invoices->sum('total_ttc');
        $totalPaid = (float) $invoices->sum('amount_paid');
        $outstanding = (float) $approved->sum('amount_outstanding');
        $overdue = (float) $approved->filter(fn ($i) => $i->is_overdue)->sum('amount_outstanding');

        $payments = Payment::where('party_type', 'supplier')
            ->where('party_id', $supplierId)
            ->with(['financialAccount', 'allocations'])
            ->orderByDesc('payment_date')
            ->get();

        $aging = $this->supplierInvoiceService->calculateAging($supplierId);

        // Build Statement of Account
        $timeline = [];
        foreach ($invoices as $inv) {
            $timeline[] = [
                'type' => 'supplier_invoice',
                'date' => $inv->invoice_date->toDateString(),
                'reference' => $inv->invoice_number,
                'description' => "Supplier Invoice {$inv->invoice_number} (Ref: {$inv->supplier_invoice_ref})",
                'debit' => 0,
                'credit' => (float) $inv->total_ttc,
                'status' => $inv->status,
                'due_date' => $inv->due_date?->toDateString(),
            ];
        }

        foreach ($payments as $pay) {
            $timeline[] = [
                'type' => 'payment',
                'date' => $pay->payment_date->toDateString(),
                'reference' => $pay->payment_number,
                'description' => "Payment {$pay->payment_number}",
                'debit' => (float) $pay->amount,
                'credit' => 0,
                'status' => $pay->status,
            ];
        }

        usort($timeline, fn ($a, $b) => strcmp($b['date'], $a['date']));

        $primaryContact = $supplier->contacts->first();
        $company_name = $supplier->commercial_name ?: $supplier->legal_name;

        return [
            'supplier' => [
                'id' => $supplier->id,
                'company_name' => $company_name,
                'legal_name' => $supplier->legal_name,
                'tax_number' => $supplier->tax_id ?? null,
                'payment_terms' => $supplier->payment_terms,
            ],
            'summary' => [
                'outstanding' => round($outstanding, 3),
                'overdue' => round($overdue, 3),
                'total_invoiced' => round($totalInvoiced, 3),
                'total_paid' => round($totalPaid, 3),
            ],
            'aging' => $aging,
            'invoices' => $invoices,
            'payments' => $payments,
            'timeline' => $timeline,
        ];
    }
}
