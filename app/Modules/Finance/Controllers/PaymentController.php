<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['financialAccount', 'period'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('financial_account_id')) {
            $query->where('financial_account_id', $request->input('financial_account_id'));
        }

        if ($request->filled('party_type')) {
            $query->where('party_type', $request->input('party_type'));
        }

        if ($request->filled('party_id')) {
            $query->where('party_id', $request->input('party_id'));
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('payment_number', 'like', "%{$s}%")
                    ->orWhere('party_name', 'like', "%{$s}%")
                    ->orWhere('reference', 'like', "%{$s}%");
            });
        }

        $payments = $query->paginate((int) ($request->input('per_page', 15)));

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $payment = Payment::with(['financialAccount', 'period', 'allocations'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'direction' => 'required|in:in,out',
            'type' => 'nullable|string',
            'payment_date' => 'required|date',
            'financial_account_id' => 'required|ulid|exists:financial_accounts,id',
            'party_type' => 'nullable|string|in:client,supplier,employee,other',
            'party_id' => 'nullable|ulid',
            'party_name' => 'nullable|string|max:150',
            'method' => 'nullable|string|in:cash,bank_transfer,check,credit_card,other',
            'reference' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:3',
            'amount' => 'required|numeric|min:0.01',
            'cost_center_id' => 'nullable|ulid|exists:cost_centers,id',
            'notes' => 'nullable|string',
            'confirm' => 'nullable|boolean',
        ]);

        $payment = $this->paymentService->create($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment created successfully',
            'data' => $payment,
        ], 201);
    }

    public function confirm(Payment $payment, Request $request): JsonResponse
    {
        $confirmed = $this->paymentService->confirm($payment, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment confirmed and posted to account successfully',
            'data' => $confirmed,
        ]);
    }

    public function allocate(Payment $payment, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'allocations' => 'required|array|min:1',
            'allocations.*.invoice_type' => 'required|in:sales_invoice,supplier_invoice',
            'allocations.*.invoice_id' => 'required|string',
            'allocations.*.amount' => 'required|numeric|min:0.01',
            'allocations.*.notes' => 'nullable|string',
        ]);

        $allocated = $this->paymentService->allocate($payment, $validated['allocations'], $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment allocated successfully',
            'data' => $allocated,
        ]);
    }
}
