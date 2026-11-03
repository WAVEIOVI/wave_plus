<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Models\BpVatCertificate;
use App\Modules\BusinessPartners\Requests\StoreBpVatCertificateRequest;
use App\Modules\BusinessPartners\Requests\UpdateBpVatCertificateRequest;
use App\Modules\BusinessPartners\Resources\BpVatCertificateResource;
use Illuminate\Http\JsonResponse;

class BpVatCertificateController extends Controller
{
    use AuthorizesPartnerRequests;

    public function index(string $ownerType, string $ownerId): JsonResponse
    {
        $this->authorizePartnerView($ownerType, $ownerId);

        $certs = BpVatCertificate::where('certifiable_type', $this->morphClass($ownerType))
            ->where('certifiable_id', $ownerId)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => BpVatCertificateResource::collection($certs),
        ]);
    }

    public function store(StoreBpVatCertificateRequest $request, string $ownerType, string $ownerId): JsonResponse
    {
        $owner = $this->authorizePartnerUpdate($ownerType, $ownerId);

        $data = $request->validated();
        $data['certifiable_type'] = $owner::class;
        $data['certifiable_id'] = $owner->getKey();

        $cert = BpVatCertificate::create($data);

        return response()->json([
            'message' => 'VAT certificate created successfully.',
            'data' => new BpVatCertificateResource($cert),
        ], 201);
    }

    public function show(string $vatCertificate): JsonResponse
    {
        $record = BpVatCertificate::findOrFail($vatCertificate);
        $this->authorizeAddressable($record, 'view');

        return response()->json([
            'data' => new BpVatCertificateResource($record),
        ]);
    }

    public function update(UpdateBpVatCertificateRequest $request, string $vatCertificate): JsonResponse
    {
        $record = BpVatCertificate::findOrFail($vatCertificate);
        $this->authorizeAddressable($record, 'update');
        $record->update($request->validated());

        return response()->json([
            'message' => 'VAT certificate updated successfully.',
            'data' => new BpVatCertificateResource($record->fresh()),
        ]);
    }

    public function destroy(string $vatCertificate): JsonResponse
    {
        $record = BpVatCertificate::findOrFail($vatCertificate);
        $this->authorizeAddressable($record, 'update');
        $record->delete();

        return response()->json([
            'message' => 'VAT certificate deleted successfully.',
        ]);
    }
}
