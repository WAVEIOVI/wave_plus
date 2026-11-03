<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Services\PartnerDashboardService;
use Illuminate\Http\JsonResponse;

class BusinessPartnerDashboardController extends Controller
{
    use AuthorizesPartnerRequests;

    /**
     * Aggregate dashboard statistics across all four Business Partner types.
     */
    public function dashboardStats(PartnerDashboardService $dashboard): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        return response()->json([
            'data' => $dashboard->statistics(),
        ]);
    }
}
