<?php

use App\Modules\Crm\Controllers\ContractController;
use App\Modules\Crm\Controllers\GcsDocumentController;
use App\Modules\Crm\Controllers\LsaDocumentController;
use App\Modules\Crm\Controllers\PriceListController;
use App\Modules\Crm\Controllers\PriceRequestController;
use App\Modules\Crm\Controllers\QuotationController;
use App\Modules\Crm\Controllers\SalesOrderController;
use Illuminate\Support\Facades\Route;

// ── CRM & Sales Module ──────────────────────────────────────────────────

Route::prefix('crm')
    ->middleware(['auth:sanctum', 'crm.access'])
    ->group(function () {

        // Price Lists
        Route::get('price-lists/meta', [PriceListController::class, 'meta']);
        Route::get('price-lists', [PriceListController::class, 'index']);
        Route::post('price-lists', [PriceListController::class, 'store']);
        Route::get('price-lists/{priceList}', [PriceListController::class, 'show']);
        Route::put('price-lists/{priceList}', [PriceListController::class, 'update']);
        Route::patch('price-lists/{priceList}/status', [PriceListController::class, 'updateStatus']);
        Route::delete('price-lists/{priceList}', [PriceListController::class, 'destroy']);
        Route::post('price-lists/{priceList}/lines/from-blueprint', [PriceListController::class, 'addLinesFromBlueprint']);

        // Price Requests (RFQ)
        Route::get('price-requests/meta', [PriceRequestController::class, 'meta']);
        Route::get('price-requests', [PriceRequestController::class, 'index']);
        Route::post('price-requests', [PriceRequestController::class, 'store']);
        Route::get('price-requests/{priceRequest}', [PriceRequestController::class, 'show']);
        Route::put('price-requests/{priceRequest}', [PriceRequestController::class, 'update']);
        Route::patch('price-requests/{priceRequest}/status', [PriceRequestController::class, 'updateStatus']);
        Route::delete('price-requests/{priceRequest}', [PriceRequestController::class, 'destroy']);
        Route::post('price-requests/{priceRequest}/lines/from-blueprint', [PriceRequestController::class, 'addLinesFromBlueprint']);

        // Quotations
        Route::get('quotations/meta', [QuotationController::class, 'meta']);
        Route::get('quotations', [QuotationController::class, 'index']);
        Route::post('quotations', [QuotationController::class, 'store']);
        Route::get('quotations/{quotation}', [QuotationController::class, 'show']);
        Route::put('quotations/{quotation}', [QuotationController::class, 'update']);
        Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy']);
        Route::patch('quotations/{quotation}/status', [QuotationController::class, 'updateStatus']);
        Route::post('quotations/{quotation}/revisions', [QuotationController::class, 'createRevision']);
        Route::post('quotations/{quotation}/lines/from-blueprint', [QuotationController::class, 'addLinesFromBlueprint']);

        // Contracts
        Route::get('contracts/meta', [ContractController::class, 'meta']);
        Route::get('contracts', [ContractController::class, 'index']);
        Route::post('contracts', [ContractController::class, 'store']);
        Route::get('contracts/{contract}', [ContractController::class, 'show']);
        Route::put('contracts/{contract}', [ContractController::class, 'update']);
        Route::delete('contracts/{contract}', [ContractController::class, 'destroy']);
        Route::patch('contracts/{contract}/status', [ContractController::class, 'updateStatus']);
        Route::post('contracts/{contract}/document', [ContractController::class, 'uploadDocument']);
        Route::get('contracts/{contract}/download-document', [ContractController::class, 'downloadDocument']);


        // Sales Orders
        Route::get('sales-orders/meta', [SalesOrderController::class, 'meta']);
        Route::get('sales-orders', [SalesOrderController::class, 'index']);
        Route::post('sales-orders/wizard', [SalesOrderController::class, 'wizard']);
        Route::post('sales-orders', [SalesOrderController::class, 'store']);
        Route::post('sales-orders/convert-quotation/{quotation}', [SalesOrderController::class, 'convertFromQuotation']);
        Route::post('sales-orders/convert-contract/{contract}', [SalesOrderController::class, 'convertFromContract']);
        Route::get('sales-orders/{salesOrder}/commitment-document', [SalesOrderController::class, 'commitmentDocument']);
        Route::get('sales-orders/{salesOrder}', [SalesOrderController::class, 'show']);
        Route::put('sales-orders/{salesOrder}', [SalesOrderController::class, 'update']);
        Route::delete('sales-orders/{salesOrder}', [SalesOrderController::class, 'destroy']);
        Route::post('sales-orders/{salesOrder}/submit', [SalesOrderController::class, 'submit']);
        Route::post('sales-orders/{salesOrder}/start-feasibility-review', [SalesOrderController::class, 'startFeasibilityReview']);
        Route::get('sales-orders/{salesOrder}/stock-check', [SalesOrderController::class, 'stockCheck']);
        Route::get('sales-orders/{salesOrder}/credit-check', [SalesOrderController::class, 'creditCheck']);
        Route::post('sales-orders/{salesOrder}/feasibility-review', [SalesOrderController::class, 'feasibilityReview']);
        Route::post('sales-orders/{salesOrder}/accept', [SalesOrderController::class, 'accept']);
        Route::post('sales-orders/{salesOrder}/reject', [SalesOrderController::class, 'reject']);
        Route::post('sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel']);
        Route::post('sales-orders/{salesOrder}/revisions', [SalesOrderController::class, 'createRevision']);
        Route::post('sales-orders/{salesOrder}/lines/from-blueprint', [SalesOrderController::class, 'addLinesFromBlueprint']);
        Route::get('sales-orders/{salesOrder}/locations', [SalesOrderController::class, 'locations']);
        Route::post('sales-orders/{salesOrder}/locations', [SalesOrderController::class, 'addLocation']);
        Route::delete('sales-orders/{salesOrder}/locations/{location}', [SalesOrderController::class, 'removeLocation']);

        // LSA Documents (Read-only)
        Route::get('lsa-documents', [LsaDocumentController::class, 'index']);
        Route::get('lsa-documents/{lsaDocument}', [LsaDocumentController::class, 'show']);

        // GCS Documents (Read-only)
        Route::get('gcs-documents', [GcsDocumentController::class, 'index']);
        Route::get('gcs-documents/{gcsDocument}', [GcsDocumentController::class, 'show']);
    });
