<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\OperationalHub\Models\ServiceDeliveryNote;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\OperationalHub\Resources\ServiceDeliveryNoteResource;
use App\Modules\OperationalHub\Services\ServiceDeliveryNoteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceDeliveryNoteController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ServiceDeliveryNoteService $sdnService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ServiceDeliveryNote::class);

        $query = ServiceDeliveryNote::query()
            ->with(['client', 'serviceSite', 'issuer', 'swo'])
            ->withCount('lines');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }
        if ($request->filled('swo_id')) {
            $query->where('swo_id', $request->input('swo_id'));
        }

        $perPage = (int) $request->input('itemsPerPage', $request->input('per_page', 15));
        $notes = $query->latest()->paginate($perPage);

        return ServiceDeliveryNoteResource::collection($notes);
    }

    public function show(ServiceDeliveryNote $serviceDeliveryNote): JsonResponse
    {
        $this->authorize('view', $serviceDeliveryNote);

        $serviceDeliveryNote->load(['client', 'serviceSite', 'issuer', 'swo', 'lines.equipment']);

        return response()->json([
            'data' => new ServiceDeliveryNoteResource($serviceDeliveryNote),
        ]);
    }

    public function generate(Request $request, ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('create', ServiceDeliveryNote::class);

        $missionId = $request->filled('mission_id') ? $request->input('mission_id') : null;
        $sdn = $this->sdnService->generateFromSwo($serviceWorkOrder, $request->user(), $missionId, $request->all());

        return response()->json([
            'message' => 'Service Delivery Note generated successfully.',
            'data' => new ServiceDeliveryNoteResource($sdn),
        ], 201);
    }

    public function documentableStats(ServiceWorkOrder $serviceWorkOrder): JsonResponse
    {
        $this->authorize('view', $serviceWorkOrder);
        $stats = $this->sdnService->getDocumentableStats($serviceWorkOrder);

        return response()->json(['data' => $stats]);
    }

    public function sign(Request $request, ServiceDeliveryNote $serviceDeliveryNote): JsonResponse
    {
        $this->authorize('update', $serviceDeliveryNote);

        $request->validate([
            'client_signer_name' => 'required|string|max:150',
            'client_signature' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $sdn = $this->sdnService->sign($serviceDeliveryNote, $request->all(), $request->user());

        return response()->json([
            'message' => 'Service Delivery Note signed successfully.',
            'data' => new ServiceDeliveryNoteResource($sdn),
        ]);
    }
}
