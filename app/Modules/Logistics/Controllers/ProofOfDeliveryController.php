<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logistics\Controllers\Concerns\AuthorizesLogisticsRequests;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\ProofOfDelivery;
use App\Modules\Logistics\Resources\ProofOfDeliveryResource;
use App\Modules\Logistics\Services\PodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProofOfDeliveryController extends Controller
{
    use AuthorizesLogisticsRequests;

    public function __construct(
        private readonly PodService $podService,
    ) {}

    public function show(DeliveryWorkOrder $deliveryWorkOrder): JsonResponse
    {
        $this->authorize('view', $deliveryWorkOrder);

        $pod = $deliveryWorkOrder->pod()->with(['creator', 'updater'])->first();
        if (!$pod) {
            return response()->json(['data' => null], 404);
        }

        return (new ProofOfDeliveryResource($pod))->response();
    }

    public function store(Request $request, DeliveryWorkOrder $deliveryWorkOrder): JsonResponse
    {
        $this->authorize('recordPod', $deliveryWorkOrder);

        $validated = $request->validate([
            'delivered_at'      => 'nullable|date',
            'receiver_name'     => 'required|string|max:150',
            'receiver_title'    => 'nullable|string|max:100',
            'signature_data'    => 'nullable|string',
            'signed_at'         => 'nullable|date',
            'condition_overall' => 'nullable|string|in:good,damaged,partial,refused',
            'notes'             => 'nullable|string|max:2000',
            'latitude'          => 'nullable|numeric|between:-90,90',
            'longitude'         => 'nullable|numeric|between:-180,180',
        ]);

        $pod = $this->podService->recordPod($deliveryWorkOrder, $validated, $request->user());

        return (new ProofOfDeliveryResource($pod))->response()->setStatusCode(201);
    }
}
