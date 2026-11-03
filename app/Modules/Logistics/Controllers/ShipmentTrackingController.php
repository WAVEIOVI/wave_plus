<?php

declare(strict_types=1);

namespace App\Modules\Logistics\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logistics\Controllers\Concerns\AuthorizesLogisticsRequests;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\ShipmentTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentTrackingController extends Controller
{
    use AuthorizesLogisticsRequests;

    public function history(DeliveryWorkOrder $deliveryWorkOrder): JsonResponse
    {
        $this->authorize('view', $deliveryWorkOrder);

        $history = ShipmentTracking::where('dwo_id', $deliveryWorkOrder->id)
            ->with(['recordedBy'])
            ->orderBy('recorded_at', 'desc')
            ->get();

        return response()->json($history);
    }

    public function addEvent(Request $request, DeliveryWorkOrder $deliveryWorkOrder): JsonResponse
    {
        $this->authorize('update', $deliveryWorkOrder);

        $validated = $request->validate([
            'event_type'     => 'required|in:dispatched,checkpoint,delayed,arrived,incident',
            'status_label'   => 'required|string',
            'latitude'       => 'nullable|numeric',
            'longitude'      => 'nullable|numeric',
            'location_label' => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);

        $tracking = ShipmentTracking::create([
            'dwo_id'         => $deliveryWorkOrder->id,
            'event_type'     => $validated['event_type'],
            'status_label'   => $validated['status_label'],
            'latitude'       => $validated['latitude'] ?? null,
            'longitude'      => $validated['longitude'] ?? null,
            'location_label' => $validated['location_label'] ?? null,
            'notes'          => $validated['notes'] ?? null,
            'recorded_by'    => $request->user()->id,
            'recorded_at'    => now(),
            'created_by'     => $request->user()->id,
            'updated_by'     => $request->user()->id,
        ]);

        return response()->json($tracking, 201);
    }
}
