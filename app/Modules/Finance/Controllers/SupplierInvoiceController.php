<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\SupplierInvoice;
use App\Modules\Finance\Services\SupplierInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierInvoiceController extends Controller
{
    public function __construct(
        private readonly SupplierInvoiceService $invoiceService,
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
            'supplier_id' => 'required|ulid|exists:suppliers,id',
            'supplier_invoice_ref' => 'nullable|string|max:100',
            'type' => 'nullable|string',
            'purchase_order_id' => 'nullable|ulid',
            'grn_id' => 'nullable|ulid',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'currency' => 'nullable|string|max:3',
            'payment_terms' => 'nullable|string',
            'cost_center_id' => 'nullable|ulid|exists:cost_centers,id',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0',
        ]);

        $invoice = $this->invoiceService->create($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Supplier invoice created successfully',
            'data' => $invoice,
        ], 201);
    }

    public function approve(SupplierInvoice $supplierInvoice, Request $request): JsonResponse
    {
        $invoice = $this->invoiceService->approve($supplierInvoice, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Supplier invoice approved and posted successfully',
            'data' => $invoice,
        ]);
    }

    public function aging(Request $request): JsonResponse
    {
        $supplierId = $request->filled('supplier_id') ? $request->input('supplier_id') : null;
        $aging = $this->invoiceService->calculateAging($supplierId);

        return response()->json([
            'success' => true,
            'data' => $aging,
        ]);
    }
}
