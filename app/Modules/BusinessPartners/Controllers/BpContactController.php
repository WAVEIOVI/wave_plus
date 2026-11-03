<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\BpContact;
use App\Modules\BusinessPartners\Requests\LinkContactAddressRequest;
use App\Modules\BusinessPartners\Requests\StoreBpContactRequest;
use App\Modules\BusinessPartners\Requests\UpdateBpContactRequest;
use App\Modules\BusinessPartners\Resources\BpContactResource;
use App\Modules\BusinessPartners\Services\BpContactAddressService;
use Illuminate\Http\JsonResponse;

class BpContactController extends Controller
{
    use AuthorizesPartnerRequests;

    public function __construct(
        private readonly BpContactAddressService $contactAddressService,
    ) {}

    public function index(string $ownerType, string $ownerId): JsonResponse
    {
        $this->authorizePartnerView($ownerType, $ownerId);

        $contacts = BpContact::where('contactable_type', $this->morphClass($ownerType))
            ->where('contactable_id', $ownerId)
            ->with('addresses')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => BpContactResource::collection($contacts),
        ]);
    }

    public function store(StoreBpContactRequest $request, string $ownerType, string $ownerId): JsonResponse
    {
        $owner = $this->authorizePartnerUpdate($ownerType, $ownerId);

        $data = $request->validated();
        $data['contactable_type'] = $owner::class;
        $data['contactable_id'] = $owner->getKey();

        $contact = BpContact::create($data);

        return response()->json([
            'message' => 'Contact created successfully.',
            'data' => new BpContactResource($contact),
        ], 201);
    }

    public function show(string $contact): JsonResponse
    {
        $record = BpContact::with('addresses')->findOrFail($contact);
        $this->authorizeAddressable($record, 'view');

        return response()->json([
            'data' => new BpContactResource($record),
        ]);
    }

    public function update(UpdateBpContactRequest $request, string $contact): JsonResponse
    {
        $record = BpContact::findOrFail($contact);
        $this->authorizeAddressable($record, 'update');
        $record->update($request->validated());

        return response()->json([
            'message' => 'Contact updated successfully.',
            'data' => new BpContactResource($record->fresh()),
        ]);
    }

    public function destroy(string $contact): JsonResponse
    {
        $record = BpContact::findOrFail($contact);
        $this->authorizeAddressable($record, 'update');
        $record->delete();

        return response()->json([
            'message' => 'Contact deleted successfully.',
        ]);
    }

    public function linkAddress(LinkContactAddressRequest $request, string $contactId): JsonResponse
    {
        $contact = BpContact::findOrFail($contactId);
        $this->authorizeAddressable($contact, 'update');
        $address = BpAddress::findOrFail($request->validated('bp_address_id'));

        $this->contactAddressService->link(
            $contact,
            $address,
            $request->boolean('is_primary_location', false),
        );

        return response()->json([
            'message' => 'Contact linked to address successfully.',
            'data' => new BpContactResource($contact->load('addresses')),
        ]);
    }

    public function unlinkAddress(string $contactId, string $addressId): JsonResponse
    {
        $contact = BpContact::findOrFail($contactId);
        $this->authorizeAddressable($contact, 'update');
        $address = BpAddress::findOrFail($addressId);

        $this->contactAddressService->unlink($contact, $address);

        return response()->json([
            'message' => 'Contact unlinked from address successfully.',
        ]);
    }
}
