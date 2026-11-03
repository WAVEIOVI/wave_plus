<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logistics\Controllers\Concerns\AuthorizesLogisticsRequests;
use App\Modules\Logistics\Models\DeliveryException;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Resources\DeliveryExceptionResource;
use App\Modules\Logistics\Services\DeliveryExceptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryExceptionController extends Controller
{
    use AuthorizesLogisticsRequests;

    public function __construct(
        private readonly DeliveryExceptionService $exceptionService,
    ) {}

    public function index(DeliveryWorkOrder $deliveryWorkOrder): AnonymousResourceCollection
    {
        $this->authorize('view', $deliveryWorkOrder);

        $exceptions = $deliveryWorkOrder->exceptions()->with(['resolver'])->latest()->get();

        return DeliveryExceptionResource::collection($exceptions);
    }

    public function store(Request $request, DeliveryWorkOrder $deliveryWorkOrder): JsonResponse
    {
        $this->authorize('createException', $deliveryWorkOrder);

        $validated = $request->validate([
            'dwo_line_id' => 'nullable|ulid|exists:dwo_lines,id',
            'reason' => 'required|string',
            'affected_qty' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $exception = $this->exceptionService->recordException($deliveryWorkOrder, $validated, $request->user());

        return (new DeliveryExceptionResource($exception))->response()->setStatusCode(201);
    }

    public function resolve(Request $request, DeliveryException $exception): JsonResponse
    {
        $this->authorize('resolve', $exception);

        $validated = $request->validate([
            'resolution_notes' => 'required|string|max:2000',
        ]);

        $updated = $this->exceptionService->resolveException($exception, $validated, $request->user());

        return (new DeliveryExceptionResource($updated))->response();
    }
}
