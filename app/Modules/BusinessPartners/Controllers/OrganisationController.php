<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Requests\StoreOrganisationRequest;
use App\Modules\BusinessPartners\Requests\UpdateOrganisationRequest;
use App\Modules\BusinessPartners\Resources\OrganisationResource;
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

class OrganisationController extends Controller
{
    use AuthorizesPartnerRequests, GeneratesPdf, HasPartnerActions;

    protected string $partnerType = 'organisation';

    protected string $modelClass = Organisation::class;

    public function __construct(
        private readonly PartnerQueryService $queryService,
        private readonly PartnerWriteService $writeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Organisation::class);

        $query = QueryBuilder::for(Organisation::with('accountManager'))
            ->allowedFilters([
                AllowedFilter::partial('legal_name'),
                AllowedFilter::partial('commercial_name'),
                AllowedFilter::partial('tax_id'),
                AllowedFilter::partial('organisation_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('organisation_type'),
                AllowedFilter::exact('scope'),
                AllowedFilter::exact('hq_country'),
            ])
            ->allowedSorts([
                AllowedSort::field('id'),
                AllowedSort::field('legal_name'),
                AllowedSort::field('organisation_id'),
                AllowedSort::field('status'),
                AllowedSort::field('organisation_type'),
                AllowedSort::field('created_at'),
            ])
            ->allowedIncludes(['addresses', 'contacts', 'bankAccounts', 'vatCertificates', 'documents'])
            ->defaultSort('-id');

        $this->queryService->applySearch($query, $request, ['legal_name', 'commercial_name', 'tax_id', 'organisation_id']);

        return $this->queryService->paginate($query, $request, OrganisationResource::class);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Organisation::class);

        return $this->queryService->options(
            Organisation::query(),
            $request,
            ['legal_name', 'commercial_name', 'tax_id', 'organisation_id', 'primary_email'],
            ['id', 'organisation_id', 'legal_name', 'commercial_name', 'status', 'primary_email', 'primary_phone', 'hq_country'],
            'organisation_id',
        );
    }

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', Organisation::class);

        return response()->json([
            'data' => [
                'total_organisations' => Organisation::count(),
                'total_active_organisations' => Organisation::where('status', 'active')->count(),
                'total_inactive_organisations' => Organisation::where('status', 'inactive')->count(),
                'total_prospect_organisations' => Organisation::where('status', 'prospect')->count(),
                'total_archived_organisations' => Organisation::where('status', 'archived')->count(),
            ],
        ]);
    }

    public function exportPdf(): Response
    {
        $this->authorize('viewAny', Organisation::class);

        $organisations = Organisation::with('accountManager')
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        $html = view('pdf.organisations.organisations-list', compact('organisations'))->render();

        $pdf = $this->makeBrowsershot($html, 'landscape')->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="organisations-list.pdf"',
        ]);
    }

    public function exportSinglePdf(string $id): Response
    {
        $organisation = Organisation::with([
            'accountManager',
            'addresses.contacts',
            'contacts.addresses',
            'bankAccounts',
            'vatCertificates',
            'documents',
        ])->findOrFail($id);

        $this->authorize('view', $organisation);

        $html = view('pdf.organisations.organisation-record', compact('organisation'))->render();

        $pdf = $this->makeBrowsershot($html, 'portrait')->pdf();

        $filename = 'organisation-'.$organisation->organisation_id.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function store(StoreOrganisationRequest $request): JsonResponse
    {
        $this->authorize('create', Organisation::class);

        $org = $this->writeService->create(Organisation::class, $request->validated(), $request);

        return response()->json([
            'message' => 'Organisation created successfully.',
            'data' => new OrganisationResource($org),
        ], 201);
    }

    public function show(string $organisation): JsonResponse
    {
        $record = Organisation::with([
            'accountManager',
            'addresses.contacts',
            'contacts.addresses',
            'bankAccounts',
            'vatCertificates',
            'documents',
        ])->findOrFail($organisation);

        $this->authorize('view', $record);

        return response()->json([
            'data' => new OrganisationResource($record),
        ]);
    }

    public function update(UpdateOrganisationRequest $request, string $organisation): JsonResponse
    {
        $record = Organisation::findOrFail($organisation);
        $this->authorize('update', $record);

        $record = $this->writeService->update($record, $request->validated(), $request);

        return response()->json([
            'message' => 'Organisation updated successfully.',
            'data' => new OrganisationResource($record),
        ]);
    }

    public function destroy(string $organisation): JsonResponse
    {
        $record = Organisation::findOrFail($organisation);
        $this->authorize('delete', $record);
        $this->writeService->delete($record);

        return response()->json([
            'message' => 'Organisation deleted successfully.',
        ]);
    }
}
