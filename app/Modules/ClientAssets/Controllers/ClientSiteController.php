<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Helpers\MediaFormatter;
use App\Modules\ClientAssets\Controllers\Concerns\AuthorizesClientAssetsRequests;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\ClientAssets\Models\ClientSiteArea;
use App\Modules\ClientAssets\Requests\StoreClientSiteAreaRequest;
use App\Modules\ClientAssets\Requests\StoreClientSiteRequest;
use App\Modules\ClientAssets\Requests\UpdateClientSiteAreaRequest;
use App\Modules\ClientAssets\Requests\UpdateClientSiteRequest;
use App\Modules\ClientAssets\Resources\ClientSiteAreaResource;
use App\Modules\ClientAssets\Resources\ClientSiteResource;
use App\Modules\ClientAssets\Services\ClientAssetsQueryService;
use App\Modules\ClientAssets\Services\ClientSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Media;

class ClientSiteController extends Controller
{
    use AuthorizesClientAssetsRequests;

    private const ALLOWED_SORT_COLUMNS = [
        'name',
        'site_code',
        'status',
        'site_type',
        'risk_class',
        'created_at',
        'updated_at',
    ];

    public function __construct(
        private readonly ClientSiteService $siteService,
        private readonly ClientAssetsQueryService $queryService,
    ) {}

    public function meta(): JsonResponse
    {
        $this->authorize('viewAny', ClientSite::class);

        return response()->json($this->siteService->getSitesMeta());
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ClientSite::class);

        $query = ClientSite::with(['client', 'address.contacts', 'media'])
            ->withCount(['areas', 'equipment']);

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('site_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('legal_name', 'like', "%{$search}%")
                            ->orWhere('commercial_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($clientId = $request->input('filter.client_id', $request->input('client_id'))) {
            $query->where('client_id', $clientId);
        }
        if ($status = $request->input('filter.status', $request->input('status'))) {
            $query->where('status', $status);
        }
        if ($type = $request->input('filter.site_type', $request->input('site_type'))) {
            $query->where('site_type', $type);
        }
        if ($riskClass = $request->input('filter.risk_class', $request->input('risk_class'))) {
            $query->where('risk_class', $riskClass);
        }

        $sortBy = $this->queryService->safeSort($request, self::ALLOWED_SORT_COLUMNS, 'created_at');
        $orderBy = $this->queryService->sortDirection($request);
        $query->orderBy($sortBy, $orderBy);

        $paginated = $query->paginate($this->queryService->cappedPerPage($request));

        return response()->json([
            'data' => ClientSiteResource::collection($paginated->items()),
            'total' => $paginated->total(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $site = ClientSite::with([
            'client.media',
            'address.contacts',
            'rootAreas.allChildren',
            'areas.children',
            'equipment.blueprint',
            'equipment.currentQrCode',
            'media',
        ])
            ->withCount(['areas', 'equipment'])
            ->findOrFail($id);

        $this->authorize('view', $site);

        return response()->json(['data' => new ClientSiteResource($site)]);
    }

    public function store(StoreClientSiteRequest $request): JsonResponse
    {
        $this->authorize('create', ClientSite::class);
        $site = $this->siteService->createSite($request->validated(), Auth::user());
        $site->load(['client', 'address.contacts', 'media']);
        $site->loadCount(['areas', 'equipment']);

        return response()->json([
            'message' => 'Client site created successfully.',
            'data' => new ClientSiteResource($site),
        ], 201);
    }

    public function update(UpdateClientSiteRequest $request, string $id): JsonResponse
    {
        $site = ClientSite::findOrFail($id);
        $this->authorize('update', $site);
        $site = $this->siteService->updateSite($site, $request->validated(), Auth::user());
        $site->load(['client', 'address.contacts', 'media']);
        $site->loadCount(['areas', 'equipment']);

        return response()->json([
            'message' => 'Client site updated successfully.',
            'data' => new ClientSiteResource($site),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $site = ClientSite::findOrFail($id);
        $this->authorize('delete', $site);

        if ($site->equipment()->exists()) {
            return response()->json([
                'message' => 'Cannot delete site: it has linked equipment.',
            ], 422);
        }

        $site->delete();

        return response()->json(['message' => 'Client site deleted successfully.']);
    }

    // ─── Area Sub-Resources (Recursive Hierarchy) ────────────────

    public function indexAreas(string $siteId): JsonResponse
    {
        $site = ClientSite::findOrFail($siteId);
        $this->authorize('view', $site);

        $areas = ClientSiteArea::where('site_id', $site->id)
            ->whereNull('parent_area_id')
            ->with(['allChildren', 'parent'])
            ->withCount('equipment')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => ClientSiteAreaResource::collection($areas),
            'flat_list' => ClientSiteAreaResource::collection(
                ClientSiteArea::where('site_id', $siteId)
                    ->withCount('equipment')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }

    public function storeArea(StoreClientSiteAreaRequest $request, string $siteId): JsonResponse
    {
        $site = ClientSite::findOrFail($siteId);
        $this->authorize('update', $site);

        $validated = $request->validated();
        $validated['site_id'] = $site->id;
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $area = ClientSiteArea::create($validated);
        $area->load(['parent', 'children']);

        return response()->json([
            'message' => 'Site area created successfully.',
            'data' => new ClientSiteAreaResource($area),
        ], 201);
    }

    public function updateArea(UpdateClientSiteAreaRequest $request, string $areaId): JsonResponse
    {
        $area = ClientSiteArea::findOrFail($areaId);
        $this->authorize('update', $area);

        $validated = $request->validated();
        $validated['updated_by'] = Auth::id();

        $area->update($validated);
        $area->load(['parent', 'children']);

        return response()->json([
            'message' => 'Site area updated successfully.',
            'data' => new ClientSiteAreaResource($area),
        ]);
    }

    public function destroyArea(string $areaId): JsonResponse
    {
        $area = ClientSiteArea::findOrFail($areaId);
        $this->authorize('delete', $area);

        if ($area->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete area: it has sub-areas. Delete or move sub-areas first.',
            ], 422);
        }

        if ($area->equipment()->exists()) {
            return response()->json([
                'message' => 'Cannot delete area: it has linked equipment.',
            ], 422);
        }

        $area->delete();

        return response()->json(['message' => 'Site area deleted successfully.']);
    }

    // ─── Media Operations (Photos, Documents, Site Plans) ──────────

    public function storeMedia(Request $request, string $siteId): JsonResponse
    {
        $site = ClientSite::findOrFail($siteId);
        $this->authorize('update', $site);

        $request->validate([
            'collection' => 'required|string|in:photos,documents,site_plans',
            'file' => 'required|file',
            'title' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'is_main' => 'nullable|boolean',
        ]);

        $collection = $request->input('collection');
        $fileMeta = \App\Modules\Shared\Services\MediaValidationService::validateAndProcess(
            $request->file('file'),
            $collection,
            'file'
        );

        $isMain = $request->boolean('is_main');

        // If setting as main photo or first photo uploaded
        if ($collection === 'photos') {
            $existingPhotosCount = $site->getMedia('photos')->count();
            if ($existingPhotosCount === 0 || $isMain) {
                $isMain = true;
                // Unmark existing
                foreach ($site->getMedia('photos') as $p) {
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

        $media = $site->addMediaFromRequest('file')
            ->usingFileName($fileMeta['original_name'])
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

    public function setMainPhoto(string $siteId, string|Media $media): JsonResponse
    {
        $site = ClientSite::findOrFail($siteId);
        $this->authorize('update', $site);

        $mediaRecord = $media instanceof Media ? $media : Media::findOrFail($media);

        if ((string) $mediaRecord->model_id !== (string) $site->id || ! str_ends_with((string) $mediaRecord->model_type, 'ClientSite')) {
            abort(403, 'Unauthorized file access.');
        }

        foreach ($site->getMedia('photos') as $p) {
            $p->setCustomProperty('is_main', (string) $p->id === (string) $mediaRecord->id);
            $p->save();
        }

        return response()->json([
            'message' => 'Main photo updated successfully.',
        ]);
    }

    public function downloadMedia(string $siteId, string|Media $media)
    {
        $site = ClientSite::findOrFail($siteId);
        $this->authorize('view', $site);

        $mediaRecord = $media instanceof Media ? $media : Media::findOrFail($media);

        if ((string) $mediaRecord->model_id !== (string) $site->id || ! str_ends_with((string) $mediaRecord->model_type, 'ClientSite')) {
            abort(403, 'Unauthorized file access.');
        }

        return $mediaRecord->toResponse(request());
    }

    public function destroyMedia(string $siteId, string|Media $media): JsonResponse
    {
        $site = ClientSite::findOrFail($siteId);
        $this->authorize('update', $site);

        $mediaRecord = $media instanceof Media ? $media : Media::findOrFail($media);

        if ((string) $mediaRecord->model_id !== (string) $site->id || ! str_ends_with((string) $mediaRecord->model_type, 'ClientSite')) {
            abort(403, 'Unauthorized file access.');
        }

        $wasMain = (bool) $mediaRecord->getCustomProperty('is_main');
        $collection = $mediaRecord->collection_name;

        $mediaRecord->delete();

        // If main photo was deleted, nominate next available photo as main
        if ($wasMain && $collection === 'photos') {
            $nextPhoto = $site->getMedia('photos')->first();
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
