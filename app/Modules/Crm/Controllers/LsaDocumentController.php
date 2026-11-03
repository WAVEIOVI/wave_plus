<?php

declare(strict_types=1);

namespace App\Modules\Crm\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Controllers\Concerns\AuthorizesCrmRequests;
use App\Modules\Crm\Models\LsaDocument;
use App\Modules\Crm\Resources\LsaDocumentResource;
use App\Modules\Crm\Services\CrmQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LsaDocumentController extends Controller
{
    use AuthorizesCrmRequests;

    public function __construct(
        private readonly CrmQueryService $queryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LsaDocument::class);
        $query = LsaDocument::with(['salesOrder']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return LsaDocumentResource::collection(
            $query->latest()->paginate($this->queryService->cappedPerPage($request))
        );
    }

    public function show(LsaDocument $lsaDocument): LsaDocumentResource
    {
        $this->authorize('view', $lsaDocument);
        $lsaDocument->load(['salesOrder.client', 'contract']);

        return new LsaDocumentResource($lsaDocument);
    }
}
