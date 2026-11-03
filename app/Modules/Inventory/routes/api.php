<?php

use App\Modules\Inventory\Controllers\InventoryController;
use App\Modules\Inventory\Controllers\StockLotController;
use App\Modules\Inventory\Controllers\StockMovementController;
use App\Modules\Inventory\Controllers\WarehouseLocationController;
use App\Modules\Inventory\Controllers\WarehouseSiteController;
use App\Modules\Inventory\Controllers\WarehouseZoneController;
use Illuminate\Support\Facades\Route;

// ── Inventory & Warehouse Module ────────────────────────────────────────

Route::prefix('inventory')
    ->middleware(['auth:sanctum', 'inventory.access'])
    ->group(function () {

        // Dashboard KPIs
        Route::get('dashboard-stats', [InventoryController::class, 'dashboardStats']);

        // Stock Items (read-only — written by movements)
        Route::get('stock-items', [InventoryController::class, 'index']);
        Route::get('stock-items/{stockItem}', [InventoryController::class, 'show']);

        // Stock Movements (immutable ledger)
        Route::get('movements', [StockMovementController::class, 'index']);
        Route::get('movements/{id}', [StockMovementController::class, 'show']);
        Route::post('movements/adjustment', [StockMovementController::class, 'storeAdjustment']);
        Route::post('movements/transfer', [StockMovementController::class, 'storeTransfer']);
        Route::post('movements/initial-load', [StockMovementController::class, 'storeInitialLoad']);

        // Stock Lots
        Route::get('lots', [StockLotController::class, 'index']);
        Route::post('lots', [StockLotController::class, 'store']);
        Route::get('lots/{id}', [StockLotController::class, 'show']);
        Route::put('lots/{id}', [StockLotController::class, 'update']);
        Route::delete('lots/{id}', [StockLotController::class, 'destroy']);

        // Warehouse Sites
        Route::get('sites', [WarehouseSiteController::class, 'index']);
        Route::post('sites', [WarehouseSiteController::class, 'store']);
        Route::get('sites/{id}', [WarehouseSiteController::class, 'show']);
        Route::put('sites/{id}', [WarehouseSiteController::class, 'update']);
        Route::delete('sites/{id}', [WarehouseSiteController::class, 'destroy']);

        // Mobile Warehouse Vehicle Linking
        Route::post('sites/{id}/link-vehicle', [WarehouseSiteController::class, 'linkVehicle']);
        Route::post('sites/{id}/unlink-vehicle', [WarehouseSiteController::class, 'unlinkVehicle']);
        Route::get('sites/{id}/assignment-history', [WarehouseSiteController::class, 'assignmentHistory']);

        // Warehouse Zones
        Route::get('zones', [WarehouseZoneController::class, 'index']);
        Route::post('zones', [WarehouseZoneController::class, 'store']);
        Route::get('zones/{id}', [WarehouseZoneController::class, 'show']);
        Route::put('zones/{id}', [WarehouseZoneController::class, 'update']);
        Route::delete('zones/{id}', [WarehouseZoneController::class, 'destroy']);

        // Warehouse Locations
        Route::get('locations', [WarehouseLocationController::class, 'index']);
        Route::post('locations', [WarehouseLocationController::class, 'store']);
        Route::get('locations/{id}', [WarehouseLocationController::class, 'show']);
        Route::put('locations/{id}', [WarehouseLocationController::class, 'update']);
        Route::delete('locations/{id}', [WarehouseLocationController::class, 'destroy']);
        Route::patch('locations/{id}/toggle-active', [WarehouseLocationController::class, 'toggleActive']);
    });
