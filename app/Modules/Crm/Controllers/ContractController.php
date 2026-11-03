<?php

declare(strict_types=1);

namespace App\Modules\Crm\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Controllers\Concerns\AuthorizesCrmRequests;
use App\Modules\Crm\Enums\ContractStatus;
use App\Modules\Crm\Models\Contract;
use App\Modules\Crm\Requests\StoreContractRequest;
use App\Modules\Crm\Requests\UpdateContractRequest;
use App\Modules\Crm\Resources\ContractResource;
use App\Modules\Crm\Services\ContractWriteService;
use App\Modules\Crm\Services\CrmQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    use AuthorizesCrmRequests;

    public function __construct(
        private readonly ContractWriteService $writeService,
        private readonly CrmQueryService $queryService,
    ) {}

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', Contract::class);
        $base = Contract::query();

        return response()->json([
            'data' => [
                'total_contracts' => (clone $base)->count(),
                'active' => (clone $base)->where('status', 'active')->count(),
                'expiring_soon' => (clone $base)->where('status', 'expiring_soon')->count(),
                'expired' => (clone $base)->where('status', 'expired')->count(),
            ],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contract::class);
        $query = Contract::with(['client']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return ContractResource::collection(
            $query->latest()->paginate($this->queryService->cappedPerPage($request))
        );
    }

    public function show(Contract $contract): ContractResource
    {
        $this->authorize('view', $contract);
        $contract->load([
            'client.contacts',
            'client.addresses',
            'clientRepresentative',
            'providerRepresentative',
            'priceList',
            'defaultDeliveryLocation',
            'renewedFrom',
            'originatingQuotation',
            'lines.catalogItem.variants',
        ]);

        return new ContractResource($contract);
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $this->authorize('create', Contract::class);
        $contract = $this->writeService->create($request->validated());

        return (new ContractResource($contract->load([
            'client.contacts',
            'client.addresses',
            'clientRepresentative',
            'providerRepresentative',
            'priceList',
            'defaultDeliveryLocation',
            'lines.catalogItem',
        ])))->response()->setStatusCode(201);
    }

    public function update(UpdateContractRequest $request, Contract $contract): ContractResource
    {
        $this->authorize('update', $contract);
        $contract = $this->writeService->update($contract, $request->validated());

        return new ContractResource($contract->load([
            'client.contacts',
            'client.addresses',
            'clientRepresentative',
            'providerRepresentative',
            'priceList',
            'defaultDeliveryLocation',
            'lines.catalogItem',
        ]));
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $this->authorize('delete', $contract);
        $this->writeService->delete($contract);

        return response()->noContent();
    }

    public function updateStatus(Request $request, Contract $contract): ContractResource
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::enum(ContractStatus::class)],
            'notes' => ['nullable', 'string'],
        ]);

        $this->authorize('update', $contract);
        $contract = $this->writeService->transition($contract, $validated['status']);

        return new ContractResource($contract->load(['client', 'lines']));
    }

    public function uploadDocument(Request $request, Contract $contract): JsonResponse
    {
        $this->authorize('update', $contract);
        $request->validate([
            'document' => ['required', 'file'],
        ]);

        $fileMeta = \App\Modules\Shared\Services\MediaValidationService::validateAndProcess(
            $request->file('document'),
            \App\Modules\Shared\Services\MediaValidationService::CATEGORY_DOCUMENT,
            'document'
        );

        // Delete legacy file if exists
        if ($contract->document_path && Storage::disk('public')->exists($contract->document_path)) {
            Storage::disk('public')->delete($contract->document_path);
        }

        $contract->clearMediaCollection('contract_document');
        $media = $contract->addMediaFromRequest('document')
            ->usingFileName($fileMeta['original_name'])
            ->withCustomProperties([
                'uploaded_by' => $fileMeta['uploaded_by'],
                'uploaded_by_id' => $fileMeta['uploaded_by_id'],
            ])
            ->toMediaCollection('contract_document');

        $downloadUrl = url("/api/crm/contracts/{$contract->id}/download-document");
        $contract->update([
            'document_path' => $media->getPath(),
        ]);

        return response()->json([
            'document_path' => $contract->document_path,
            'document_url' => $downloadUrl,
            'media' => \App\Modules\Shared\Helpers\MediaFormatter::format($media),
        ]);
    }

    public function downloadDocument(Contract $contract)
    {
        $this->authorize('view', $contract);
        $media = $contract->getFirstMedia('contract_document');

        if ($media) {
            return $media->toResponse(request());
        }

        if ($contract->document_path && Storage::disk('public')->exists($contract->document_path)) {
            return Storage::disk('public')->download($contract->document_path, $contract->reference . '.pdf');
        }

        abort(404, 'No document attached to this contract.');
    }
}

