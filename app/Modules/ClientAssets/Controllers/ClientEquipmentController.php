<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Helpers\MediaFormatter;
use App\Modules\ClientAssets\Controllers\Concerns\AuthorizesClientAssetsRequests;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\ClientAssets\Models\EquipmentQrCode;
use App\Modules\ClientAssets\Requests\ReplaceQrCodeRequest;
use App\Modules\ClientAssets\Requests\StoreClientEquipmentRequest;
use App\Modules\ClientAssets\Requests\TransferEquipmentRequest;
use App\Modules\ClientAssets\Requests\UpdateClientEquipmentRequest;
use App\Modules\ClientAssets\Resources\ClientEquipmentResource;
use App\Modules\ClientAssets\Services\ClientAssetsQueryService;
use App\Modules\ClientAssets\Services\EquipmentManagementService;
use App\Modules\Shared\Services\MediaValidationService;
use App\Modules\Shared\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ClientEquipmentController extends Controller
{
    use AuthorizesClientAssetsRequests;

    private const ALLOWED_SORT_COLUMNS = [
        'equipment_code',
        'brand',
        'model_name',
        'lifecycle_status',
        'compliance_status',
        'condition_status',
        'criticality',
        'next_inspection_date',
        'install_date',
        'created_at',
        'updated_at',
    ];

    private array $listEagerLoads = [
        'client',
        'site',
        'area',
        'equipmentClass',
        'blueprint',
        'optionValues.blueprintOption',
        'currentQrCode',
        'media',
    ];

    private array $detailEagerLoads = [
        'client',
        'site.address',
        'area.parent',
        'equipmentClass',
        'blueprint.blueprintOptions.values',
        'catalogItem',
        'catalogVariant',
        'catalogBrand',
        'currentQrCode',
        'qrCodes.creator',
        'interventionCycles',
        'locationHistories.site',
        'locationHistories.area',
        'locationHistories.changer',
        'statusHistories.changer',
        'optionValues.blueprintOption',
        'optionValues.blueprintOptionValue',
        'complianceRecords.creator',
        'transfers.fromSite',
        'transfers.fromArea',
        'transfers.toSite',
        'transfers.toArea',
        'transfers.transferredByUser',
        'media',
    ];

    public function __construct(
        private readonly EquipmentManagementService $equipmentService,
        private readonly ClientAssetsQueryService $queryService,
    ) {}

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', ClientEquipment::class);

        $total = ClientEquipment::count();
        $operational = ClientEquipment::whereIn('lifecycle_status', ['operational', 'active'])->count();
        $underMaintenance = ClientEquipment::whereIn('lifecycle_status', ['under_maintenance', 'in_service'])->count();
        $overdue = ClientEquipment::where('compliance_status', 'overdue')->count();
        $withoutSite = ClientEquipment::whereNull('site_id')->count();
        $withoutArea = ClientEquipment::whereNotNull('site_id')->whereNull('area_id')->count();

        return response()->json([
            'total_equipment' => $total,
            'operational_count' => $operational,
            'maintenance_count' => $underMaintenance,
            'overdue_count' => $overdue,
            'without_site' => $withoutSite,
            'without_area' => $withoutArea,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ClientEquipment::class);

        $query = ClientEquipment::with($this->listEagerLoads);

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('equipment_code', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('internal_number', 'like', "%{$search}%")
                    ->orWhere('asset_tag', 'like', "%{$search}%")
                    ->orWhere('manufacturer_part_number', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model_name', 'like', "%{$search}%")
                    ->orWhereHas('currentQrCode', fn ($qq) => $qq->where('qr_code', 'like', "%{$search}%"))
                    ->orWhereHas('client', fn ($cq) => $cq->where('legal_name', 'like', "%{$search}%")->orWhere('commercial_name', 'like', "%{$search}%"))
                    ->orWhereHas('site', fn ($sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('site_code', 'like', "%{$search}%"));
            });
        }

        // Filters
        if ($clientId = $request->input('filter.client_id', $request->input('client_id'))) {
            $query->where('client_id', $clientId);
        }
        if ($siteId = $request->input('filter.site_id', $request->input('site_id'))) {
            $query->where('site_id', $siteId);
        }
        if ($areaId = $request->input('filter.area_id', $request->input('area_id'))) {
            $includeSubAreas = $request->boolean('include_sub_areas', false);
            if ($includeSubAreas) {
                $area = \App\Modules\ClientAssets\Models\ClientSiteArea::with('children')->find($areaId);
                if ($area) {
                    $descendantIds = array_merge([$areaId], $area->getAllDescendantIds());
                    $query->whereIn('area_id', $descendantIds);
                } else {
                    $query->where('area_id', $areaId);
                }
            } else {
                $query->where('area_id', $areaId);
            }
        }
        if ($classId = $request->input('filter.equipment_class_id', $request->input('equipment_class_id'))) {
            $query->where('equipment_class_id', $classId);
        }
        if ($blueprintId = $request->input('filter.blueprint_id', $request->input('blueprint_id'))) {
            $query->where('blueprint_id', $blueprintId);
        }
        if ($brandId = $request->input('filter.brand_id', $request->input('brand_id'))) {
            $query->where('brand_id', $brandId);
        }
        if ($lifecycleStatus = $request->input('filter.lifecycle_status', $request->input('lifecycle_status'))) {
            $query->where('lifecycle_status', $lifecycleStatus);
        }
        if ($complianceStatus = $request->input('filter.compliance_status', $request->input('compliance_status'))) {
            $query->where('compliance_status', $complianceStatus);
        }
        if ($conditionStatus = $request->input('filter.condition_status', $request->input('condition_status'))) {
            $query->where('condition_status', $conditionStatus);
        }
        if ($criticality = $request->input('filter.criticality', $request->input('criticality'))) {
            $query->where('criticality', $criticality);
        }

        $sortBy = $this->queryService->safeSort($request, self::ALLOWED_SORT_COLUMNS, 'created_at');
        $orderBy = $this->queryService->sortDirection($request);
        $query->orderBy($sortBy, $orderBy);

        $paginated = $query->paginate($this->queryService->cappedPerPage($request));

        return response()->json([
            'data' => ClientEquipmentResource::collection($paginated->items()),
            'total' => $paginated->total(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $equipment = ClientEquipment::with($this->detailEagerLoads)->findOrFail($id);
        $this->authorize('view', $equipment);

        return response()->json(['data' => new ClientEquipmentResource($equipment)]);
    }

    public function store(StoreClientEquipmentRequest $request): JsonResponse
    {
        $this->authorize('create', ClientEquipment::class);
        $equipment = $this->equipmentService->createEquipment($request->validated(), Auth::user());
        $equipment->load($this->detailEagerLoads);

        return response()->json([
            'message' => 'Equipment created successfully.',
            'data' => new ClientEquipmentResource($equipment),
        ], 201);
    }

    public function update(UpdateClientEquipmentRequest $request, string $id): JsonResponse
    {
        $equipment = ClientEquipment::findOrFail($id);
        $this->authorize('update', $equipment);
        $equipment = $this->equipmentService->updateEquipment($equipment, $request->validated(), Auth::user());
        $equipment->load($this->detailEagerLoads);

        return response()->json([
            'message' => 'Equipment updated successfully.',
            'data' => new ClientEquipmentResource($equipment),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $equipment = ClientEquipment::findOrFail($id);
        $this->authorize('delete', $equipment);
        $equipment->delete();

        return response()->json(['message' => 'Equipment deleted successfully.']);
    }

    // ─── Transfer Operation ──────────────────────────────────────

    public function transfer(TransferEquipmentRequest $request, string $id): JsonResponse
    {
        $equipment = ClientEquipment::findOrFail($id);
        $this->authorize('update', $equipment);
        $equipment = $this->equipmentService->transferEquipment($equipment, $request->validated(), Auth::user());
        $equipment->load($this->detailEagerLoads);

        return response()->json([
            'message' => 'Equipment transferred successfully.',
            'data' => new ClientEquipmentResource($equipment),
        ]);
    }

    // ─── Replace QR Code Operation ───────────────────────────────

    public function replaceQr(ReplaceQrCodeRequest $request, string $id): JsonResponse
    {
        $equipment = ClientEquipment::findOrFail($id);
        $this->authorize('update', $equipment);
        $this->equipmentService->replaceQrCode($equipment, $request->validated(), Auth::user());
        $equipment->load($this->detailEagerLoads);

        return response()->json([
            'message' => 'QR Code replaced successfully.',
            'data' => new ClientEquipmentResource($equipment),
        ]);
    }

    // ─── QR Download ────────────────────────────────────────────

    public function qrDownload(Request $request, string $id): mixed
    {
        $equipment = ClientEquipment::with('currentQrCode')->findOrFail($id);
        $this->authorize('view', $equipment);
        $qrCodeValue = $equipment->currentQrCode?->qr_code ?: $equipment->equipment_code;
        $url = url("/ca/scan/eq/{$qrCodeValue}");

        $format = strtolower($request->query('format', 'png'));

        if ($format === 'svg') {
            $svg = QrCodeService::generateSvg($url);

            return response($svg, 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => "attachment; filename=\"qr-{$equipment->equipment_code}.svg\"",
            ]);
        }

        $qr = QrCodeService::generatePng($url, 300);

        return response($qr, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => "attachment; filename=\"qr-{$equipment->equipment_code}.png\"",
        ]);
    }

    // ─── Public Scan Endpoint ───────────────────────────────────

    public function publicScan(string $code): JsonResponse
    {
        $qrRecord = EquipmentQrCode::where('qr_code', $code)->first();
        if ($qrRecord) {
            $equipment = ClientEquipment::with(['site', 'area', 'blueprint', 'currentQrCode', 'media'])
                ->findOrFail($qrRecord->equipment_id);
        } else {
            $equipment = ClientEquipment::with(['site', 'area', 'blueprint', 'currentQrCode', 'media'])
                ->where(function ($q) use ($code) {
                    $q->where('uuid', $code)->orWhere('equipment_code', $code);
                })
                ->firstOrFail();
        }

        return response()->json([
            'data' => [
                'equipment_code' => $equipment->equipment_code,
                'brand' => $equipment->brand,
                'model_name' => $equipment->model_name,
                'status' => $equipment->status,
                'lifecycle_status' => $equipment->lifecycle_status,
                'compliance_status' => $equipment->compliance_status,
                'condition_status' => $equipment->condition_status,
                'criticality' => $equipment->criticality,
                'install_date' => $equipment->install_date?->toDateString(),
                'next_inspection_date' => $equipment->next_inspection_date?->toDateString(),
                'site' => $equipment->site ? ['name' => $equipment->site->name, 'site_code' => $equipment->site->site_code] : null,
                'area' => $equipment->area ? ['name' => $equipment->area->name, 'hierarchy_path' => $equipment->area->hierarchy_path] : null,
                'is_current_qr' => $equipment->currentQrCode?->qr_code === $code || $equipment->equipment_code === $code,
            ],
        ]);
    }

    // ─── Media Operations (Photos, Certificates, Documents) ────────

    public function storeMedia(Request $request, string $equipmentId): JsonResponse
    {
        $equipment = ClientEquipment::findOrFail($equipmentId);
        $this->authorize('update', $equipment);

        $request->validate([
            'collection' => 'required|string|in:photos,certificates,documents',
            'file' => 'required|file',
            'title' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'is_main' => 'nullable|boolean',
        ]);

        $collection = $request->input('collection');
        $fileMeta = MediaValidationService::validateAndProcess(
            $request->file('file'),
            $collection,
            'file'
        );

        $isMain = $request->boolean('is_main');

        // If setting as main photo or first photo uploaded
        if ($collection === 'photos') {
            $existingPhotosCount = $equipment->getMedia('photos')->count();
            if ($existingPhotosCount === 0 || $isMain) {
                $isMain = true;
                foreach ($equipment->getMedia('photos') as $p) {
                    $p->setCustomProperty('is_main', false);
                    $p->save();
                }
            }
        }

        $customProperties = [
            'original_name' => $fileMeta['original_name'],
            'uploaded_by' => $fileMeta['uploaded_by'],
            'uploaded_by_id' => $fileMeta['uploaded_by_id'],
            'width' => $fileMeta['width'],
            'height' => $fileMeta['height'],
            'title' => $request->input('title', $fileMeta['original_name']),
            'category' => $request->input('category', 'general'),
            'notes' => $request->input('notes'),
            'is_main' => $isMain,
        ];

        $media = $equipment->addMediaFromRequest('file')
            ->withCustomProperties($customProperties)
            ->toMediaCollection($collection);

        $formatted = MediaFormatter::format($media);
        $formatted['is_main'] = $isMain;
        $formatted['category'] = $customProperties['category'];

        return response()->json([
            'message' => 'File uploaded successfully.',
            'data' => $formatted,
        ], 201);
    }

    public function setMainPhoto(string $equipmentId, Media $media): JsonResponse
    {
        $equipment = ClientEquipment::findOrFail($equipmentId);
        $this->authorize('update', $equipment);

        if ($media->model_id !== $equipment->id || $media->model_type !== ClientEquipment::class) {
            abort(403, 'Unauthorized file access.');
        }

        foreach ($equipment->getMedia('photos') as $p) {
            $p->setCustomProperty('is_main', $p->id === $media->id);
            $p->save();
        }

        return response()->json([
            'message' => 'Main photo updated successfully.',
        ]);
    }

    public function downloadMedia(string $equipmentId, Media $media)
    {
        $equipment = ClientEquipment::findOrFail($equipmentId);
        $this->authorize('view', $equipment);

        if ($media->model_id !== $equipment->id || $media->model_type !== ClientEquipment::class) {
            abort(403, 'Unauthorized file access.');
        }

        return $media->toResponse(request());
    }

    public function destroyMedia(string $equipmentId, Media $media): JsonResponse
    {
        $equipment = ClientEquipment::findOrFail($equipmentId);
        $this->authorize('update', $equipment);

        if ($media->model_id !== $equipment->id || $media->model_type !== ClientEquipment::class) {
            abort(403, 'Unauthorized file access.');
        }

        $wasMain = (bool) $media->getCustomProperty('is_main');
        $collection = $media->collection_name;

        $media->delete();

        // If main photo was deleted, nominate next available photo as main
        if ($wasMain && $collection === 'photos') {
            $nextPhoto = $equipment->getMedia('photos')->first();
            if ($nextPhoto) {
                $nextPhoto->setCustomProperty('is_main', true);
                $nextPhoto->save();
            }
        }

        return response()->json([
            'message' => 'File deleted successfully.',
        ]);
    }
}
