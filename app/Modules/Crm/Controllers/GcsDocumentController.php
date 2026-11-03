<?php

declare(strict_types=1);

namespace App\Modules\Crm\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Controllers\Concerns\AuthorizesCrmRequests;
use App\Modules\Crm\Models\GcsDocument;
use App\Modules\Crm\Resources\GcsDocumentResource;
use App\Modules\Crm\Services\CrmQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GcsDocumentController extends Controller
{
    use AuthorizesCrmRequests;

    public function __construct(
        private readonly CrmQueryService $queryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', GcsDocument::class);
        $query = GcsDocument::with(['salesOrder']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return GcsDocumentResource::collection(
            $query->latest()->paginate($this->queryService->cappedPerPage($request))
        );
    }

    public function show(GcsDocument $gcsDocument): GcsDocumentResource
    {
        $this->authorize('view', $gcsDocument);
        $gcsDocument->load(['salesOrder.client', 'contract']);

        return new GcsDocumentResource($gcsDocument);
    }
}
