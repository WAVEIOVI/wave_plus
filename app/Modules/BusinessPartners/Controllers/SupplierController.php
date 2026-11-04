<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\BusinessPartners\Requests\StoreSupplierRequest;
use App\Modules\BusinessPartners\Requests\UpdateSupplierRequest;
use App\Modules\BusinessPartners\Resources\SupplierResource;
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

class SupplierController extends Controller
{
    use AuthorizesPartnerRequests, GeneratesPdf, HasPartnerActions;

    protected string $partnerType = 'supplier';

    protected string $modelClass = Supplier::class;

    public function __construct(
        private readonly PartnerQueryService $queryService,
        private readonly PartnerWriteService $writeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        $query = QueryBuilder::for(Supplier::with('accountManager'))
            ->allowedFilters([
                AllowedFilter::partial('legal_name'),
                AllowedFilter::partial('commercial_name'),
                AllowedFilter::partial('tax_id'),
                AllowedFilter::partial('supplier_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('supplier_category'),
                AllowedFilter::exact('industry'),
                AllowedFilter::exact('hq_country'),
            ])
            ->allowedSorts([
                AllowedSort::field('id'),
                AllowedSort::field('legal_name'),
                AllowedSort::field('supplier_id'),
                AllowedSort::field('status'),
                AllowedSort::field('supplier_category'),
                AllowedSort::field('created_at'),
            ])
            ->allowedIncludes(['addresses', 'contacts', 'bankAccounts', 'vatCertificates', 'documents'])
            ->defaultSort('-id');

        $this->queryService->applySearch($query, $request, ['legal_name', 'commercial_name', 'tax_id', 'supplier_id']);

        return $this->queryService->paginate($query, $request, SupplierResource::class);
    }

    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        return $this->queryService->options(
            Supplier::query(),
            $request,
            ['legal_name', 'commercial_name', 'tax_id', 'supplier_id', 'primary_email'],
            ['id', 'supplier_id', 'legal_name', 'commercial_name', 'status', 'primary_email', 'primary_phone', 'hq_country'],
            'supplier_id',
        );
    }

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', Supplier::class);

        return response()->json([
            'data' => [
                'total_suppliers' => Supplier::count(),
                'total_active_suppliers' => Supplier::where('status', 'active')->count(),
                'total_inactive_suppliers' => Supplier::where('status', 'inactive')->count(),
                'total_prospect_suppliers' => Supplier::where('status', 'prospect')->count(),
                'total_suspended_suppliers' => Supplier::where('status', 'suspended')->count(),
            ],
        ]);
    }

    public function exportPdf(): Response
    {
        $this->authorize('export', Supplier::class);

        $suppliers = Supplier::with('accountManager')
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get();

        $html = view('pdf.suppliers.suppliers-list', compact('suppliers'))->render();

        $pdf = $this->makeBrowsershot($html, 'landscape')->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="suppliers-list.pdf"',
        ]);
    }

    public function exportSinglePdf(string $id): Response
    {
        $supplier = Supplier::with([
            'accountManager',
            'addresses.contacts',
            'contacts.addresses',
            'bankAccounts',
            'vatCertificates',
            'documents',
        ])->findOrFail($id);

        $this->authorize('export', $supplier);

        $html = view('pdf.suppliers.supplier-record', compact('supplier'))->render();

        $pdf = $this->makeBrowsershot($html, 'portrait')->pdf();

        $filename = 'supplier-'.$supplier->supplier_id.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = $this->writeService->create(Supplier::class, $request->validated(), $request);

        return response()->json([
            'message' => 'Supplier created successfully.',
            'data' => new SupplierResource($supplier),
        ], 201);
    }

    public function show(string $supplier): JsonResponse
    {
        $record = Supplier::with([
            'accountManager',
            'addresses.contacts',
            'contacts.addresses',
            'bankAccounts',
            'vatCertificates',
            'documents',
        ])->findOrFail($supplier);

        $this->authorize('view', $record);

        return response()->json([
            'data' => new SupplierResource($record),
        ]);
    }

    public function update(UpdateSupplierRequest $request, string $supplier): JsonResponse
    {
        $record = Supplier::findOrFail($supplier);
        $this->authorize('update', $record);

        $record = $this->writeService->update($record, $request->validated(), $request);

        return response()->json([
            'message' => 'Supplier updated successfully.',
            'data' => new SupplierResource($record),
        ]);
    }

    public function destroy(string $supplier): JsonResponse
    {
        $record = Supplier::findOrFail($supplier);
        $this->authorize('delete', $record);
        $this->writeService->delete($record);

        return response()->json([
            'message' => 'Supplier deleted successfully.',
        ]);
    }
}
