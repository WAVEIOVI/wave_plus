<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logistics\Controllers\Concerns\AuthorizesLogisticsRequests;
use App\Modules\Logistics\Models\ReturnOrder;
use App\Modules\Logistics\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnOrderController extends Controller
{
    use AuthorizesLogisticsRequests;

    public function __construct(
        protected ReturnService $returnService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReturnOrder::class);

        $returns = ReturnOrder::with(['client', 'deliveryWorkOrder'])->paginate();

        return response()->json($returns);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ReturnOrder::class);

        $validated = $request->validate([
            'return_number' => 'nullable|string|max:100',
            'dwo_id' => 'required|ulid|exists:delivery_work_orders,id',
            'client_id' => 'required|ulid|exists:clients,id',
            'return_reason' => 'required|string|max:2000',
            'status' => 'nullable|string',
        ]);

        $validated['return_number'] = $validated['return_number'] ?? ('RET-'.strtoupper(uniqid()));
        $validated['created_by'] = $request->user()->id;
        $returnOrder = ReturnOrder::create($validated);

        return response()->json($returnOrder, 201);
    }

    public function show(ReturnOrder $returnOrder): JsonResponse
    {
        $this->authorize('view', $returnOrder);

        $returnOrder->load(['items', 'client', 'deliveryWorkOrder', 'validatedBy']);

        return response()->json($returnOrder);
    }

    public function validateReturn(Request $request, ReturnOrder $returnOrder): JsonResponse
    {
        $this->authorize('validate', $returnOrder);

        try {
            $validatedReturn = $this->returnService->validate($returnOrder, $request->user());

            return response()->json($validatedReturn);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function destroy(ReturnOrder $returnOrder): JsonResponse
    {
        $this->authorize('delete', $returnOrder);

        $returnOrder->delete();

        return response()->json(null, 204);
    }
}
