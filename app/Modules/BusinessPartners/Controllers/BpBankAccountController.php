<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Models\BpBankAccount;
use App\Modules\BusinessPartners\Requests\StoreBpBankAccountRequest;
use App\Modules\BusinessPartners\Requests\UpdateBpBankAccountRequest;
use App\Modules\BusinessPartners\Resources\BpBankAccountResource;
use Illuminate\Http\JsonResponse;

class BpBankAccountController extends Controller
{
    use AuthorizesPartnerRequests;

    public function index(string $ownerType, string $ownerId): JsonResponse
    {
        $this->authorizePartnerView($ownerType, $ownerId);

        $accounts = BpBankAccount::where('bankable_type', $this->morphClass($ownerType))
            ->where('bankable_id', $ownerId)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => BpBankAccountResource::collection($accounts),
        ]);
    }

    public function store(StoreBpBankAccountRequest $request, string $ownerType, string $ownerId): JsonResponse
    {
        $owner = $this->authorizePartnerUpdate($ownerType, $ownerId);

        $data = $request->validated();
        $data['bankable_type'] = $owner::class;
        $data['bankable_id'] = $owner->getKey();

        $account = BpBankAccount::create($data);

        return response()->json([
            'message' => 'Bank account created successfully.',
            'data' => new BpBankAccountResource($account),
        ], 201);
    }

    public function show(string $bankAccount): JsonResponse
    {
        $record = BpBankAccount::findOrFail($bankAccount);
        $this->authorizeAddressable($record, 'view');

        return response()->json([
            'data' => new BpBankAccountResource($record),
        ]);
    }

    public function update(UpdateBpBankAccountRequest $request, string $bankAccount): JsonResponse
    {
        $record = BpBankAccount::findOrFail($bankAccount);
        $this->authorizeAddressable($record, 'update');
        $record->update($request->validated());

        return response()->json([
            'message' => 'Bank account updated successfully.',
            'data' => new BpBankAccountResource($record->fresh()),
        ]);
    }

    public function destroy(string $bankAccount): JsonResponse
    {
        $record = BpBankAccount::findOrFail($bankAccount);
        $this->authorizeAddressable($record, 'update');
        $record->delete();

        return response()->json([
            'message' => 'Bank account deleted successfully.',
        ]);
    }
}
