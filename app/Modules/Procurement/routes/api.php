<?php

use App\Modules\Procurement\Controllers\GoodsReceiptNoteController;
use App\Modules\Procurement\Controllers\IprController;
use App\Modules\Procurement\Controllers\ProcurementDashboardController;
use App\Modules\Procurement\Controllers\PurchaseOrderController;
use App\Modules\Procurement\Controllers\RfqController;
use App\Modules\Procurement\Controllers\RfqResponseController;
use Illuminate\Support\Facades\Route;

// ── Procurement Module ──────────────────────────────────────────────────

Route::prefix('procurement')
    ->middleware(['auth:sanctum', 'procurement.access'])
    ->group(function () {

        // Internal Purchase Requests (IPR)
        Route::get('iprs/meta', [IprController::class, 'meta']);
        Route::get('iprs', [IprController::class, 'index']);
        Route::post('iprs', [IprController::class, 'store']);
        Route::get('iprs/{ipr}', [IprController::class, 'show']);
        Route::put('iprs/{ipr}', [IprController::class, 'update']);
        Route::patch('iprs/{ipr}/status', [IprController::class, 'updateStatus']);
        Route::delete('iprs/{ipr}', [IprController::class, 'destroy']);

        // Request for Quotation (RFQ)
        Route::get('rfqs/meta', [RfqController::class, 'meta']);
        Route::patch('rfqs/{rfq}/status', [RfqController::class, 'updateStatus']);
        Route::get('rfqs', [RfqController::class, 'index']);
        Route::post('rfqs', [RfqController::class, 'store']);
        Route::get('rfqs/{rfq}', [RfqController::class, 'show']);
        Route::put('rfqs/{rfq}', [RfqController::class, 'update']);
        Route::delete('rfqs/{rfq}', [RfqController::class, 'destroy']);

        // RFQ Responses (Supplier Bids)
        Route::post('rfqs/{rfq}/responses', [RfqResponseController::class, 'store']);

        // Purchase Orders (PO)
        Route::get('pos/meta', [PurchaseOrderController::class, 'meta']);
        Route::patch('pos/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus']);
        Route::get('pos', [PurchaseOrderController::class, 'index']);
        Route::post('pos', [PurchaseOrderController::class, 'store']);
        Route::get('pos/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
        Route::put('pos/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
        Route::delete('pos/{purchaseOrder}', [PurchaseOrderController::class, 'destroy']);

        // Goods Receipt Notes (GRN)
        Route::get('grns/meta', [GoodsReceiptNoteController::class, 'meta']);
        Route::get('grns', [GoodsReceiptNoteController::class, 'index']);
        Route::post('grns', [GoodsReceiptNoteController::class, 'store']);
        Route::get('grns/{goodsReceiptNote}', [GoodsReceiptNoteController::class, 'show']);
        Route::put('grns/{goodsReceiptNote}', [GoodsReceiptNoteController::class, 'update']);
        Route::patch('grns/{goodsReceiptNote}/status', [GoodsReceiptNoteController::class, 'updateStatus']);
        Route::get('grns/{goodsReceiptNote}/download-delivery-note', [GoodsReceiptNoteController::class, 'downloadDeliveryNote']);
        Route::delete('grns/{goodsReceiptNote}', [GoodsReceiptNoteController::class, 'destroy']);

        // Procurement Dashboard
        Route::get('dashboard', [ProcurementDashboardController::class, 'index']);
        Route::get('suppliers/{supplierId}/scorecard', [ProcurementDashboardController::class, 'supplierScorecard']);
    });
