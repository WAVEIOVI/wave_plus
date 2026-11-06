<?php

use App\Modules\ClientAssets\Controllers\ClientEquipmentController;
use App\Modules\ClientAssets\Controllers\ClientSiteController;
use App\Modules\ClientAssets\Controllers\EquipmentClassLookupController;
use App\Modules\ClientAssets\Controllers\WorkwearEmployeeController;
use App\Modules\ClientAssets\Controllers\WorkwearFabricController;
use App\Modules\ClientAssets\Controllers\WorkwearItemController;
use App\Modules\ClientAssets\Controllers\WorkwearModelController;
use Illuminate\Support\Facades\Route;

// ── Client Assets Module ────────────────────────────────────────────────

Route::prefix('client-assets')
    ->middleware(['auth:sanctum', 'client-assets.access'])
    ->group(function () {

        // ── Equipment Classes (shortcut) ────────────────────────────
        Route::get('equipment-classes', [EquipmentClassLookupController::class, 'index']);
        Route::get('equipment-classes/{id}', [EquipmentClassLookupController::class, 'show']);

        // ── Client Sites ────────────────────────────────────────────
        Route::get('sites/meta', [ClientSiteController::class, 'meta']);
        Route::get('sites', [ClientSiteController::class, 'index']);
        Route::post('sites', [ClientSiteController::class, 'store']);
        Route::get('sites/{site}', [ClientSiteController::class, 'show']);
        Route::put('sites/{site}', [ClientSiteController::class, 'update']);
        Route::delete('sites/{site}', [ClientSiteController::class, 'destroy']);

        // Site Areas (recursive hierarchy)
        Route::get('sites/{site}/areas', [ClientSiteController::class, 'indexAreas']);
        Route::post('sites/{site}/areas', [ClientSiteController::class, 'storeArea']);
        Route::put('areas/{area}', [ClientSiteController::class, 'updateArea']);
        Route::delete('areas/{area}', [ClientSiteController::class, 'destroyArea']);

        // Site Media (Photos, Documents, Site Plans)
        Route::post('sites/{site}/media', [ClientSiteController::class, 'storeMedia']);
        Route::post('sites/{site}/media/{media}/set-main', [ClientSiteController::class, 'setMainPhoto']);
        Route::get('sites/{site}/media/{media}/download', [ClientSiteController::class, 'downloadMedia']);
        Route::delete('sites/{site}/media/{media}', [ClientSiteController::class, 'destroyMedia']);

        // ── Client Equipment ────────────────────────────────────────
        Route::get('equipment/meta', [ClientEquipmentController::class, 'meta']);
        Route::get('equipment', [ClientEquipmentController::class, 'index']);
        Route::post('equipment', [ClientEquipmentController::class, 'store']);
        Route::get('equipment/{eq}', [ClientEquipmentController::class, 'show']);
        Route::put('equipment/{eq}', [ClientEquipmentController::class, 'update']);
        Route::delete('equipment/{eq}', [ClientEquipmentController::class, 'destroy']);
        Route::post('equipment/{eq}/transfer', [ClientEquipmentController::class, 'transfer']);
        Route::post('equipment/{eq}/replace-qr', [ClientEquipmentController::class, 'replaceQr']);
        Route::get('equipment/{eq}/qr/download', [ClientEquipmentController::class, 'qrDownload']);

        // Equipment Media (Photos, Certificates, Documents)
        Route::post('equipment/{eq}/media', [ClientEquipmentController::class, 'storeMedia']);
        Route::post('equipment/{eq}/media/{media}/set-main', [ClientEquipmentController::class, 'setMainPhoto']);
        Route::get('equipment/{eq}/media/{media}/download', [ClientEquipmentController::class, 'downloadMedia']);
        Route::delete('equipment/{eq}/media/{media}', [ClientEquipmentController::class, 'destroyMedia']);

        // ── Workwear Fabrics (Settings & Lookup) ────────────────────
        Route::get('workwear-fabrics', [WorkwearFabricController::class, 'index']);
        Route::post('workwear-fabrics/seed-defaults', [WorkwearFabricController::class, 'seedDefaults']);
        Route::post('workwear-fabrics', [WorkwearFabricController::class, 'store']);
        Route::get('workwear-fabrics/{fabric}', [WorkwearFabricController::class, 'show']);
        Route::put('workwear-fabrics/{fabric}', [WorkwearFabricController::class, 'update']);
        Route::delete('workwear-fabrics/{fabric}', [WorkwearFabricController::class, 'destroy']);

        // ── Workwear Models ─────────────────────────────────────────
        Route::get('workwear-models', [WorkwearModelController::class, 'index']);
        Route::post('workwear-models', [WorkwearModelController::class, 'store']);
        Route::get('workwear-models/{model}', [WorkwearModelController::class, 'show']);
        Route::put('workwear-models/{model}', [WorkwearModelController::class, 'update']);
        Route::delete('workwear-models/{model}', [WorkwearModelController::class, 'destroy']);
        Route::post('workwear-models/{model}/version', [WorkwearModelController::class, 'createVersion']);
        Route::post('workwear-models/{model}/options', [WorkwearModelController::class, 'storeOption']);
        Route::put('workwear-models/{model}/options/{option}', [WorkwearModelController::class, 'updateOption']);
        Route::delete('workwear-models/{model}/options/{option}', [WorkwearModelController::class, 'destroyOption']);
        Route::post('workwear-models/{model}/options/{option}/values', [WorkwearModelController::class, 'storeOptionValue']);
        Route::delete('workwear-models/{model}/options/{option}/values/{value}', [WorkwearModelController::class, 'destroyOptionValue']);
        Route::post('workwear-models/{model}/media', [WorkwearModelController::class, 'storeMedia']);
        Route::get('workwear-models/{model}/media/{media}/download', [WorkwearModelController::class, 'downloadMedia']);
        Route::delete('workwear-models/{model}/media/{media}', [WorkwearModelController::class, 'destroyMedia']);

        // ── Workwear Employees ──────────────────────────────────────
        Route::get('workwear-employees', [WorkwearEmployeeController::class, 'index']);
        Route::post('workwear-employees/batch', [WorkwearEmployeeController::class, 'batchStore']);
        Route::post('workwear-employees', [WorkwearEmployeeController::class, 'store']);
        Route::get('workwear-employees/{emp}', [WorkwearEmployeeController::class, 'show']);
        Route::put('workwear-employees/{emp}', [WorkwearEmployeeController::class, 'update']);
        Route::delete('workwear-employees/{emp}', [WorkwearEmployeeController::class, 'destroy']);
        Route::get('workwear-employees/{emp}/history', [WorkwearEmployeeController::class, 'history']);

        // ── Workwear Items & KPIs ───────────────────────────────────
        Route::get('workwear-kpis', [WorkwearItemController::class, 'kpis']);
        Route::get('workwear-stock', [WorkwearItemController::class, 'stock']);
        Route::get('workwear-document-options', [WorkwearItemController::class, 'documentOptions']);
        Route::get('workwear-items', [WorkwearItemController::class, 'index']);
        Route::post('workwear-items/batch', [WorkwearItemController::class, 'batchStore']);
        Route::post('workwear-items/assign', [WorkwearItemController::class, 'assign']);
        Route::post('workwear-items', [WorkwearItemController::class, 'store']);
        Route::get('workwear-items/{item}', [WorkwearItemController::class, 'show']);
        Route::put('workwear-items/{item}', [WorkwearItemController::class, 'update']);
        Route::delete('workwear-items/{item}', [WorkwearItemController::class, 'destroy']);
        Route::post('workwear-items/{item}/assign', [WorkwearItemController::class, 'assignSingle']);
        Route::post('workwear-items/{item}/media', [WorkwearItemController::class, 'storeMedia']);
        Route::delete('workwear-items/{item}/media/{media}', [WorkwearItemController::class, 'destroyMedia']);
        Route::post('workwear-items/{item}/flag-stolen', [WorkwearItemController::class, 'flagStolen']);
        Route::get('workwear-items/{item}/qr/download', [WorkwearItemController::class, 'qrDownload']);
        Route::get('workwear-items/{item}/qr-code', [WorkwearItemController::class, 'qrDownload']);
        Route::post('assignments/{assignment}/return', [WorkwearItemController::class, 'returnAssignment']);
    });

// ── Public QR Scan Routes (No Auth) ─────────────────────────────────────
Route::prefix('ca/scan')->middleware('throttle:30,1')->group(function () {
    Route::get('eq/{uuid}', [ClientEquipmentController::class, 'publicScan'])->middleware('throttle:30,1');
    Route::get('ww/{uuid}', [WorkwearItemController::class, 'publicScan'])->middleware('throttle:30,1');
});
