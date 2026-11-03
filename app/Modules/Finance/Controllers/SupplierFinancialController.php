<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Services\SupplierFinancialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierFinancialController extends Controller
{
    public function __construct(
        private readonly SupplierFinancialService $supplierFinancialService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $suppliers = $this->supplierFinancialService->listSuppliers($request->all());

        return response()->json([
            'success' => true,
            'data'    => $suppliers,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $account = $this->supplierFinancialService->getAccountDetails($id);

        return response()->json([
            'success' => true,
            'data'    => $account,
        ]);
    }
}
