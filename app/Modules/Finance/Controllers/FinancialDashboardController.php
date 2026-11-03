<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialDashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $kpis = $this->dashboardService->getKpis();

        return response()->json([
            'success' => true,
            'data'    => $kpis,
        ]);
    }
}
