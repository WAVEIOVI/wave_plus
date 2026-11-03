<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\BusinessPartners\Requests\StoreServiceProviderRequest;
use App\Modules\BusinessPartners\Requests\UpdateServiceProviderRequest;
use App\Modules\BusinessPartners\Resources\ServiceProviderResource;
use App\Modules\BusinessPartners\Services\PartnerQueryService;
use App\Modules\BusinessPartners\Services\PartnerWriteService;
use App\Modules\BusinessPartners\Traits\GeneratesPdf;
use App\Modules\BusinessPartners\Traits\HasPartnerActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ServiceProviderController extends Controller
{
    use AuthorizesPartnerRequests, GeneratesPdf, HasPartnerActions;

    protected string $partnerType = 'service_provider';

    protected string $modelClass = ServiceProvider::class;

    public function __construct(
        private readonly PartnerQueryService $queryService,
        private readonly PartnerWriteService $writeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceProvider::class);

        $query = QueryBuilder::for(ServiceProvider::with('accountManager'))
            ->allowedFilters([
                AllowedFilter::partial('legal_name'),
                AllowedFilter::partial('commercial_name'),
                AllowedFilter::partial('tax_id'),
                AllowedFilter::partial('service_provider_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('provider_tier'),
                AllowedFilter::exact('availability_status'),
                AllowedFilter::exact('contract_type'),
                AllowedFilter::exact('industry'),
                AllowedFilter::exact('hq_country'),
            ])
            ->allowedSorts([
                AllowedSort::field('id'),
                AllowedSort::field('legal_name'),
                AllowedSort::field('service_provider_id'),
                AllowedSort::field('status'),
                AllowedSort::field('provider_tier'),
                AllowedSort::field('created_at'),
            ])
            ->allowedIncludes(['addresses', 'contacts', 'bankAccounts', 'vatCertificates', 'documents'])
            ->defaultSort('-id');

        $this->queryService->applySearch($query, $request, ['legal_name', 'commercial_name', 'tax_id', 'service_provider_id']);

        return $this->queryService->paginate($query, $request, ServiceProviderResource::class);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceProvider::class);

        return $this->queryService->options(
            ServiceProvider::query(),
            $request,
            ['legal_name', 'commercial_name', 'tax_id', 'service_provider_id', 'primary_email'],
            ['id', 'service_provider_id', 'legal_name', 'commercial_name', 'status', 'primary_email', 'primary_phone', 'hq_country'],
            'service_provider_id',
        );
    }

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', ServiceProvider::class);

        return response()->json([
            'data' => [
                'total_service_providers' => ServiceProvider::count(),
                'total_active_service_providers' => ServiceProvider::where('status', 'active')->count(),
                'total_inactive_service_providers' => ServiceProvider::where('status', 'inactive')->count(),
                'total_prospect_service_providers' => ServiceProvider::where('status', 'prospect')->count(),
                'total_suspended_service_providers' => ServiceProvider::where('status', 'suspended')->count(),
            ],
        ]);
    }

    public function exportPdf(): Response
    {
        $this->authorize('viewAny', ServiceProvider::class);

        $serviceProviders = ServiceProvider::with('accountManager')
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        $html = view('pdf.service-providers.service-providers-list', compact('serviceProviders'))->render();

        $pdf = $this->makeBrowsershot($html, 'landscape')->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="service-providers-list.pdf"',
        ]);
    }

    public function exportSinglePdf(string $id): Response
    {
        $serviceProvider = ServiceProvider::with([
            'accountManager',
            'addresses.contacts',
            'contacts.addresses',
            'bankAccounts',
            'vatCertificates',
            'documents',
        ])->findOrFail($id);

        $this->authorize('view', $serviceProvider);

        $html = view('pdf.service-providers.service-provider-record', compact('serviceProvider'))->render();

        $pdf = $this->makeBrowsershot($html, 'portrait')->pdf();

        $filename = 'service-provider-'.$serviceProvider->service_provider_id.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function store(StoreServiceProviderRequest $request): JsonResponse
    {
        $this->authorize('create', ServiceProvider::class);

        $sp = $this->writeService->create(ServiceProvider::class, $request->validated(), $request);

        return response()->json([
            'message' => 'Service provider created successfully.',
            'data' => new ServiceProviderResource($sp),
        ], 201);
    }

    public function show(string $service_provider): JsonResponse
    {
        $record = ServiceProvider::with([
            'accountManager',
            'addresses.contacts',
            'contacts.addresses',
            'bankAccounts',
            'vatCertificates',
            'documents',
        ])->findOrFail($service_provider);

        $this->authorize('view', $record);

        return response()->json([
            'data' => new ServiceProviderResource($record),
        ]);
    }

    public function update(UpdateServiceProviderRequest $request, string $service_provider): JsonResponse
    {
        $record = ServiceProvider::findOrFail($service_provider);
        $this->authorize('update', $record);

        $record = $this->writeService->update($record, $request->validated(), $request);

        return response()->json([
            'message' => 'Service provider updated successfully.',
            'data' => new ServiceProviderResource($record),
        ]);
    }

    public function destroy(string $service_provider): JsonResponse
    {
        $record = ServiceProvider::findOrFail($service_provider);
        $this->authorize('delete', $record);
        $this->writeService->delete($record);

        return response()->json([
            'message' => 'Service provider deleted successfully.',
        ]);
    }
}
