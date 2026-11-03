<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\SalesInvoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerFinancialService
{
    public function __construct(
        private readonly SalesInvoiceService $salesInvoiceService,
    ) {}

    public function listCustomers(array $filters = []): LengthAwarePaginator
    {
        $query = Client::query()->select('id', 'company_name', 'legal_name', 'phone', 'email', 'credit_limit', 'credit_currency', 'payment_terms');

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('company_name', 'like', "%{$s}%")
                    ->orWhere('legal_name', 'like', "%{$s}%");
            });
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15));

        // Attach financial totals to each client
        $paginator->getCollection()->transform(function ($client) {
            $invoices = SalesInvoice::posted()->forClient($client->id)->where('type', 'invoice')->get();
            $creditNotes = SalesInvoice::posted()->forClient($client->id)->where('type', 'credit_note')->get();

            $totalInvoiced = (float) $invoices->sum('total_ttc');
            $totalCreditNotes = (float) $creditNotes->sum('total_ttc');
            $outstanding = (float) $invoices->sum('amount_outstanding');
            $overdue = (float) $invoices->where('is_overdue', true)->sum('amount_outstanding');
            $limit = (float) ($client->credit_limit ?? 0);
            $available = $limit > 0 ? max(0, $limit - $outstanding) : null;

            $client->financial_summary = [
                'total_invoiced' => round($totalInvoiced, 3),
                'total_credit_notes' => round($totalCreditNotes, 3),
                'outstanding' => round($outstanding, 3),
                'overdue' => round($overdue, 3),
                'credit_limit' => round($limit, 3),
                'available_credit' => $available !== null ? round($available, 3) : null,
                'is_over_limit' => $limit > 0 && $outstanding > $limit,
            ];

            return $client;
        });

        return $paginator;
    }

    public function getAccountDetails(string $clientId): array
    {
        $client = Client::findOrFail($clientId);

        $invoices = SalesInvoice::forClient($clientId)->orderByDesc('invoice_date')->get();
        $invoiceItems = $invoices->where('type', 'invoice');
        $creditNotes = $invoices->where('type', 'credit_note');
        $debitNotes = $invoices->where('type', 'debit_note');

        $totalInvoiced = (float) $invoiceItems->sum('total_ttc');
        $totalPaid = (float) $invoiceItems->sum('amount_paid');
        $outstanding = (float) $invoiceItems->where('status', '!=', 'void')->sum('amount_outstanding');
        $overdue = (float) $invoiceItems->filter(fn ($i) => $i->is_overdue)->sum('amount_outstanding');

        $creditLimit = (float) ($client->credit_limit ?? 0);
        $availableCredit = $creditLimit > 0 ? max(0, $creditLimit - $outstanding) : null;

        $payments = Payment::where('party_type', 'client')
            ->where('party_id', $clientId)
            ->with(['financialAccount', 'allocations'])
            ->orderByDesc('payment_date')
            ->get();

        $aging = $this->salesInvoiceService->calculateAging($clientId);

        // Build Statement of Account (Chronological timeline of Invoices and Payments)
        $timeline = [];
        foreach ($invoices as $inv) {
            $timeline[] = [
                'type' => $inv->type,
                'date' => $inv->invoice_date->toDateString(),
                'reference' => $inv->invoice_number,
                'description' => "Invoice {$inv->invoice_number}",
                'debit' => $inv->type === 'credit_note' ? 0 : (float) $inv->total_ttc,
                'credit' => $inv->type === 'credit_note' ? (float) $inv->total_ttc : 0,
                'status' => $inv->status,
                'due_date' => $inv->due_date?->toDateString(),
            ];
        }

        foreach ($payments as $pay) {
            $timeline[] = [
                'type' => 'payment',
                'date' => $pay->payment_date->toDateString(),
                'reference' => $pay->payment_number,
                'description' => "Payment {$pay->payment_number} via ".($pay->method ?? 'direct'),
                'debit' => 0,
                'credit' => (float) $pay->amount,
                'status' => $pay->status,
            ];
        }

        usort($timeline, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return [
            'client' => [
                'id' => $client->id,
                'company_name' => $client->company_name,
                'legal_name' => $client->legal_name,
                'tax_number' => $client->tax_number ?? null,
                'credit_limit' => round($creditLimit, 3),
                'credit_currency' => $client->credit_currency ?? 'TND',
                'payment_terms' => $client->payment_terms,
            ],
            'summary' => [
                'credit_limit' => round($creditLimit, 3),
                'outstanding' => round($outstanding, 3),
                'overdue' => round($overdue, 3),
                'available_credit' => $availableCredit !== null ? round($availableCredit, 3) : null,
                'is_over_limit' => $creditLimit > 0 && $outstanding > $creditLimit,
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
