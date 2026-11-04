<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Services\PartnerDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessPartnerDashboardController extends Controller
{
    use AuthorizesPartnerRequests;

    /**
     * Aggregate dashboard statistics across all four Business Partner types.
     */
    public function dashboardStats(Request $request, PartnerDashboardService $dashboard): JsonResponse
    {
        abort_unless($request->user()?->can('read-bp-dashboard'), 403, 'You do not have access to the Business Partners dashboard.');

        return response()->json([
            'data' => $dashboard->statistics($request->user()),
        ]);
    }
}
