<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Requests\StoreClientRequest;
use App\Modules\BusinessPartners\Requests\UpdateClientRequest;
use App\Modules\BusinessPartners\Resources\ClientResource;
use App\Modules\BusinessPartners\Services\PartnerQueryService;
use App\Modules\BusinessPartners\Services\PartnerWriteService;
use App\Modules\BusinessPartners\Traits\GeneratesPdf;
use App\Modules\BusinessPartners\Traits\HasPartnerActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ClientController extends Controller
{
    use AuthorizesPartnerRequests, GeneratesPdf, HasPartnerActions;

    protected string $partnerType = 'client';

    protected string $modelClass = Client::class;

    public function __construct(
        private readonly PartnerQueryService $queryService,
        private readonly PartnerWriteService $writeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $query = QueryBuilder::for(Client::with('accountManager'))
            ->allowedFilters([
                AllowedFilter::partial('legal_name'),
                AllowedFilter::partial('commercial_name'),
                AllowedFilter::partial('tax_id'),
                AllowedFilter::partial('client_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('client_category'),
                AllowedFilter::exact('industry'),
                AllowedFilter::exact('hq_country'),
            ])
            ->allowedSorts([
                AllowedSort::field('id'),
                AllowedSort::field('legal_name'),
                AllowedSort::field('client_id'),
                AllowedSort::field('status'),
                AllowedSort::field('client_category'),
                AllowedSort::field('created_at'),
            ])
            ->allowedIncludes(['addresses', 'contacts', 'bankAccounts', 'vatCertificates', 'documents'])
            ->defaultSort('-id');

        $this->queryService->applySearch($query, $request, ['legal_name', 'commercial_name', 'tax_id', 'client_id']);

        return $this->queryService->paginate($query, $request, ClientResource::class);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        return $this->queryService->options(
            Client::query(),
            $request,
            ['legal_name', 'commercial_name', 'tax_id', 'client_id', 'primary_email'],
            ['id', 'client_id', 'legal_name', 'commercial_name', 'status', 'primary_email', 'primary_phone', 'hq_country'],
            'client_id',
        );
    }

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        return response()->json([
            'data' => [
                'total_clients' => Client::count(),
                'total_active_clients' => Client::where('status', 'active')->count(),
                'total_inactive_clients' => Client::where('status', 'inactive')->count(),
                'total_prospect_clients' => Client::where('status', 'prospect')->count(),
                'total_suspended_clients' => Client::where('status', 'suspended')->count(),
            ],
        ]);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $this->authorize('create', Client::class);

        $client = $this->writeService->create(Client::class, $request->validated(), $request);

        return response()->json([
            'message' => 'Client created successfully.',
            'data' => new ClientResource($client),
        ], 201);
    }

    public function show(string $client): JsonResponse
    {
        $record = Client::with([
            'accountManager',
            'addresses.contacts',
            'addresses.clientSites',
            'contacts.addresses',
            'bankAccounts',
            'vatCertificates',
            'documents',
        ])->findOrFail($client);

        $this->authorize('view', $record);

        return response()->json([
            'data' => new ClientResource($record),
        ]);
    }

    public function update(UpdateClientRequest $request, string $client): JsonResponse
    {
        $record = Client::findOrFail($client);
        $this->authorize('update', $record);

        $record = $this->writeService->update($record, $request->validated(), $request);

        return response()->json([
            'message' => 'Client updated successfully.',
            'data' => new ClientResource($record),
        ]);
    }

    public function destroy(string $client): JsonResponse
    {
        $record = Client::findOrFail($client);
        $this->authorize('delete', $record);
        $this->writeService->delete($record);

        return response()->json([
            'message' => 'Client deleted successfully.',
        ]);
    }
}
