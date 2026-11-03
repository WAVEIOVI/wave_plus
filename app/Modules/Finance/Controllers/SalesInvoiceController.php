<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\SalesInvoice;
use App\Modules\Finance\Services\SalesInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesInvoiceController extends Controller
{
    public function __construct(
        private readonly SalesInvoiceService $invoiceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $invoices = $this->invoiceService->list($request->all());

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $invoice = $this->invoiceService->find($id);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|ulid|exists:clients,id',
            'type' => 'nullable|string|in:invoice,credit_note,debit_note',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'source_type' => 'nullable|string',
            'source_id' => 'nullable|ulid',
            'source_reference' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'payment_terms' => 'nullable|string',
            'cost_center_id' => 'nullable|ulid|exists:cost_centers,id',
            'internal_notes' => 'nullable|string',
            'client_notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0',
            'issue_immediately' => 'nullable|boolean',
        ]);

        $invoice = $this->invoiceService->create($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Sales invoice created successfully',
            'data' => $invoice,
        ], 201);
    }

    public function issue(SalesInvoice $salesInvoice, Request $request): JsonResponse
    {
        $invoice = $this->invoiceService->issue($salesInvoice, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Invoice issued and posted successfully',
            'data' => $invoice,
        ]);
    }

    public function void(SalesInvoice $salesInvoice, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $invoice = $this->invoiceService->void($salesInvoice, $validated['reason'], $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Invoice voided successfully',
            'data' => $invoice,
        ]);
    }

    public function aging(Request $request): JsonResponse
    {
        $clientId = $request->filled('client_id') ? $request->input('client_id') : null;
        $aging = $this->invoiceService->calculateAging($clientId);

        return response()->json([
            'success' => true,
            'data' => $aging,
        ]);
    }
}
