<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Services\BusinessPartnerEmailSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailSearchController extends Controller
{
    use AuthorizesPartnerRequests;

    public function __construct(
        private readonly BusinessPartnerEmailSearchService $emailSearchService,
    ) {}

    /**
     * Search for emails across Business Partner entities, Contacts, and Addresses.
     */
    public function search(Request $request): JsonResponse
    {
        $categories = $this->emailSearchService->getAllowedPartnerCategories($request->user());
        if ($categories === []) {
            abort(403, 'You do not have permission to view any partner records.');
        }

        $query = trim($request->input('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $results = $this->emailSearchService->search($request->user(), $query);

        return response()->json($results->values());
    }
}
