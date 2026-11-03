<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Requests\StoreBpAddressRequest;
use App\Modules\BusinessPartners\Requests\UpdateBpAddressRequest;
use App\Modules\BusinessPartners\Resources\BpAddressResource;
use App\Modules\BusinessPartners\Services\AddressManagementService;
use Illuminate\Http\JsonResponse;

class BpAddressController extends Controller
{
    use AuthorizesPartnerRequests;

    public function __construct(
        private readonly AddressManagementService $addresses,
    ) {}

    public function index(string $ownerType, string $ownerId): JsonResponse
    {
        $this->authorizePartnerView($ownerType, $ownerId);

        $addresses = BpAddress::where('addressable_type', $this->morphClass($ownerType))
            ->where('addressable_id', $ownerId)
            ->with(['contacts', 'clientSites'])
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => BpAddressResource::collection($addresses),
        ]);
    }

    public function store(StoreBpAddressRequest $request, string $ownerType, string $ownerId): JsonResponse
    {
        $owner = $this->authorizePartnerUpdate($ownerType, $ownerId);
        $address = $this->addresses->createForOwner($owner, $request->validated());

        return response()->json([
            'message' => 'Address created successfully.',
            'data' => new BpAddressResource($address),
        ], 201);
    }

    public function show(string $address): JsonResponse
    {
        $record = BpAddress::with(['contacts', 'clientSites'])->findOrFail($address);
        $this->authorizeAddressable($record, 'view');

        return response()->json([
            'data' => new BpAddressResource($record),
        ]);
    }

    public function update(UpdateBpAddressRequest $request, string $address): JsonResponse
    {
        $record = BpAddress::findOrFail($address);
        $this->authorizeAddressable($record, 'update');
        $record = $this->addresses->update($record, $request->validated());

        return response()->json([
            'message' => 'Address updated successfully.',
            'data' => new BpAddressResource($record->fresh()->load(['contacts', 'clientSites'])),
        ]);
    }

    public function destroy(string $address): JsonResponse
    {
        $record = BpAddress::findOrFail($address);
        $this->authorizeAddressable($record, 'update');
        $this->addresses->delete($record);

        return response()->json([
            'message' => 'Address deleted successfully.',
        ]);
    }
}
