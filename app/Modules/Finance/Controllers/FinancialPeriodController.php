<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\FinancialPeriod;
use App\Modules\Finance\Services\FinancialPeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialPeriodController extends Controller
{
    public function __construct(
        private readonly FinancialPeriodService $periodService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $periods = $this->periodService->list($request->all());

        return response()->json([
            'success' => true,
            'data' => $periods,
        ]);
    }

    public function current(): JsonResponse
    {
        $period = $this->periodService->currentPeriod();

        return response()->json([
            'success' => true,
            'data' => $period,
        ]);
    }

    public function close(FinancialPeriod $financialPeriod, Request $request): JsonResponse
    {
        $closed = $this->periodService->closePeriod($financialPeriod, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => "Financial period '{$closed->name}' closed successfully",
            'data' => $closed,
        ]);
    }

    public function reopen(FinancialPeriod $financialPeriod, Request $request): JsonResponse
    {
        $reopened = $this->periodService->reopenPeriod($financialPeriod);

        return response()->json([
            'success' => true,
            'message' => "Financial period '{$reopened->name}' reopened successfully",
            'data' => $reopened,
        ]);
    }
}
