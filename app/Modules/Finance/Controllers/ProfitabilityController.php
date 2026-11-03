<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\ProfitabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfitabilityController extends Controller
{
    public function __construct(
        private readonly ProfitabilityService $profitabilityService,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $overview = $this->profitabilityService->getOverview($request->all());

        return response()->json([
            'success' => true,
            'data'    => $overview,
        ]);
    }

    public function salesOrder(string $id): JsonResponse
    {
        $data = $this->profitabilityService->forSalesOrder($id);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
