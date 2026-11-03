<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\CustomerFinancialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerFinancialController extends Controller
{
    public function __construct(
        private readonly CustomerFinancialService $customerFinancialService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customers = $this->customerFinancialService->listCustomers($request->all());

        return response()->json([
            'success' => true,
            'data'    => $customers,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $account = $this->customerFinancialService->getAccountDetails($id);

        return response()->json([
            'success' => true,
            'data'    => $account,
        ]);
    }
}
