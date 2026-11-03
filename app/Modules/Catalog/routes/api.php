<?php

use App\Modules\Catalog\Controllers\CatalogBrandController;
use App\Modules\Catalog\Controllers\CatalogCategoryController;
use App\Modules\Catalog\Controllers\CatalogDashboardController;
use App\Modules\Catalog\Controllers\CatalogItemController;
use App\Modules\Catalog\Controllers\CatalogItemMediaController;
use App\Modules\Catalog\Controllers\CatalogItemPdfController;
use App\Modules\Catalog\Controllers\CatalogVariantController;
use App\Modules\Catalog\Controllers\EquipmentBlueprintController;
use App\Modules\Catalog\Controllers\EquipmentClassController;
use App\Modules\Catalog\Controllers\GuidelineController;
use App\Modules\Catalog\Controllers\UnitOfMeasureController;
use Illuminate\Support\Facades\Route;

// ── Catalog Module ──────────────────────────────────────────────────────
Route::prefix('catalog')
    ->middleware(['auth:sanctum', 'catalog.access'])
    ->group(function () {

        // Dashboard
        Route::get('dashboard-stats', [CatalogDashboardController::class, 'dashboardStats']);

        // Categories
        Route::get('categories', [CatalogCategoryController::class, 'index']);
        Route::post('categories', [CatalogCategoryController::class, 'store']);
        Route::get('categories/{category}', [CatalogCategoryController::class, 'show']);
        Route::put('categories/{category}', [CatalogCategoryController::class, 'update']);
        Route::delete('categories/{category}', [CatalogCategoryController::class, 'destroy']);

        // Brands
        Route::get('brands', [CatalogBrandController::class, 'index']);
        Route::post('brands', [CatalogBrandController::class, 'store']);
        Route::get('brands/{brand}', [CatalogBrandController::class, 'show']);
        Route::put('brands/{brand}', [CatalogBrandController::class, 'update']);
        Route::delete('brands/{brand}', [CatalogBrandController::class, 'destroy']);

        // Units of Measure
        Route::get('uom', [UnitOfMeasureController::class, 'index']);
        Route::post('uom', [UnitOfMeasureController::class, 'store']);
        Route::get('uom/{unitOfMeasure}', [UnitOfMeasureController::class, 'show']);
        Route::put('uom/{unitOfMeasure}', [UnitOfMeasureController::class, 'update']);
        Route::delete('uom/{unitOfMeasure}', [UnitOfMeasureController::class, 'destroy']);

        // Static item routes must be registered before {catalogItem}
        Route::get('items', [CatalogItemController::class, 'index']);
        Route::post('items', [CatalogItemController::class, 'store']);
        Route::get('items/meta', [CatalogItemController::class, 'meta']);
        Route::get('items/options', [CatalogItemController::class, 'options']);
        Route::get('items/export/pdf', [CatalogItemPdfController::class, 'exportList']);
        Route::post('items/send-email', [CatalogItemController::class, 'sendEmailList']);

        Route::get('items/{catalogItem}', [CatalogItemController::class, 'show']);
        Route::put('items/{catalogItem}', [CatalogItemController::class, 'update']);
        Route::delete('items/{catalogItem}', [CatalogItemController::class, 'destroy']);
        Route::get('items/{catalogItem}/export/pdf', [CatalogItemPdfController::class, 'exportSingle']);
        Route::post('items/{catalogItem}/send-email', [CatalogItemController::class, 'sendEmailSingle']);
        Route::post('items/{catalogItem}/media', [CatalogItemMediaController::class, 'store']);
        Route::delete('items/{catalogItem}/media/{media}', [CatalogItemMediaController::class, 'destroy']);
        Route::get('items/{catalogItem}/media/{media}/download', [CatalogItemMediaController::class, 'download']);
        Route::get('items/{catalogItem}/options', [CatalogVariantController::class, 'indexOptions']);
        Route::get('items/{catalogItem}/variants', [CatalogVariantController::class, 'indexVariants']);
        Route::post('items/{catalogItem}/variants/generate', [CatalogItemController::class, 'generateVariants']);

        Route::post('options', [CatalogVariantController::class, 'storeOption']);
        Route::put('options/{option}', [CatalogVariantController::class, 'updateOption']);
        Route::delete('options/{option}', [CatalogVariantController::class, 'destroyOption']);

        Route::post('variants', [CatalogVariantController::class, 'storeVariant']);
        Route::put('variants/{variant}', [CatalogVariantController::class, 'updateVariant']);
        Route::delete('variants/{variant}', [CatalogVariantController::class, 'destroyVariant']);

        // Equipment Classes
        Route::get('equipment-classes', [EquipmentClassController::class, 'index']);
        Route::post('equipment-classes', [EquipmentClassController::class, 'store']);
        Route::get('equipment-classes/{id}', [EquipmentClassController::class, 'show']);
        Route::put('equipment-classes/{id}', [EquipmentClassController::class, 'update']);
        Route::delete('equipment-classes/{id}', [EquipmentClassController::class, 'destroy']);

        // Equipment Blueprints
        Route::get('blueprints', [EquipmentBlueprintController::class, 'index']);
        Route::post('blueprints', [EquipmentBlueprintController::class, 'store']);
        Route::get('blueprints/{equipmentBlueprint}', [EquipmentBlueprintController::class, 'show']);
        Route::post('blueprints/{equipmentBlueprint}/preview', [EquipmentBlueprintController::class, 'preview']);
        Route::put('blueprints/{equipmentBlueprint}', [EquipmentBlueprintController::class, 'update']);
        Route::delete('blueprints/{equipmentBlueprint}', [EquipmentBlueprintController::class, 'destroy']);
        Route::post('blueprints/{blueprint}/resolve-lines', [\App\Modules\Catalog\Controllers\BlueprintLinePreviewController::class, 'resolveLines']);

        // Guidelines (standalone)
        Route::get('guidelines', [GuidelineController::class, 'index']);
        Route::post('guidelines', [GuidelineController::class, 'store']);
        Route::get('guidelines/{guideline}', [GuidelineController::class, 'show']);
        Route::put('guidelines/{guideline}', [GuidelineController::class, 'update']);
        Route::delete('guidelines/{guideline}', [GuidelineController::class, 'destroy']);

        // Guideline Steps
        Route::post('guideline-steps', [GuidelineController::class, 'storeStep']);
        Route::put('guideline-steps/{step}', [GuidelineController::class, 'updateStep']);
        Route::delete('guideline-steps/{step}', [GuidelineController::class, 'destroyStep']);

        // Blueprint ↔ Guideline (attach/detach)
        Route::get('blueprints/{blueprintId}/guidelines', [GuidelineController::class, 'blueprintGuidelines']);
        Route::post('blueprints/{blueprintId}/guidelines/{guidelineId}', [GuidelineController::class, 'attachToBlueprint']);
        Route::delete('blueprints/{blueprintId}/guidelines/{guidelineId}', [GuidelineController::class, 'detachFromBlueprint']);
    });
