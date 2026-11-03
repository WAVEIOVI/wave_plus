<?php

declare(strict_types=1);

use App\Modules\Logistics\Controllers\DeliveryExceptionController;
use App\Modules\Logistics\Controllers\DeliveryNoteController;
use App\Modules\Logistics\Controllers\DeliveryServiceProviderController;
use App\Modules\Logistics\Controllers\DeliveryWaveController;
use App\Modules\Logistics\Controllers\DeliveryWorkOrderController;
use App\Modules\Logistics\Controllers\ProofOfDeliveryController;
use App\Modules\Logistics\Controllers\ReturnOrderController;
use App\Modules\Logistics\Controllers\ShipmentTrackingController;
use App\Modules\Hr\Controllers\FleetVehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Logistics Module API Routes
|--------------------------------------------------------------------------
|
| Prefixed with /api/logistics and protected by auth:sanctum middleware.
|
*/

Route::prefix('logistics')
    ->middleware(['auth:sanctum'])
    ->group(function () {

        // ── Delivery Work Orders (DWO) ──────────────────────────────
        Route::get('dwos/meta', [DeliveryWorkOrderController::class, 'meta']);
        Route::get('dwos/kanban', [DeliveryWorkOrderController::class, 'kanban']);
        Route::get('dwos/eligible-so-lines/{salesOrder}', [DeliveryWorkOrderController::class, 'eligibleSoLines']);
        Route::post('dwos/from-so', [DeliveryWorkOrderController::class, 'createFromSo']);
        Route::get('dwos', [DeliveryWorkOrderController::class, 'index']);
        Route::get('dwos/{deliveryWorkOrder}', [DeliveryWorkOrderController::class, 'show']);
        Route::put('dwos/{deliveryWorkOrder}', [DeliveryWorkOrderController::class, 'update']);
        Route::delete('dwos/{deliveryWorkOrder}', [DeliveryWorkOrderController::class, 'destroy']);
        Route::patch('dwos/{deliveryWorkOrder}/transition', [DeliveryWorkOrderController::class, 'transition']);
        Route::post('dwos/{deliveryWorkOrder}/lines/{line}/approve-substitution', [DeliveryWorkOrderController::class, 'approveSubstitution']);
        Route::post('dwos/{deliveryWorkOrder}/delivery-note', [DeliveryWorkOrderController::class, 'generateDeliveryNote']);

        // Picking operations on DWO
        Route::post('dwos/{deliveryWorkOrder}/picks', [DeliveryWorkOrderController::class, 'batchConfirmPicks']);
        Route::post('dwos/{deliveryWorkOrder}/lines/{line}/pick', [DeliveryWorkOrderController::class, 'recordPick']);

        // ── Proof of Delivery (POD) ──────────────────────────────────
        Route::get('dwos/{deliveryWorkOrder}/pod', [ProofOfDeliveryController::class, 'show']);
        Route::post('dwos/{deliveryWorkOrder}/pod', [ProofOfDeliveryController::class, 'store']);

        // ── Delivery Exceptions ─────────────────────────────────────
        Route::get('dwos/{deliveryWorkOrder}/exceptions', [DeliveryExceptionController::class, 'index']);
        Route::post('dwos/{deliveryWorkOrder}/exceptions', [DeliveryExceptionController::class, 'store']);

        // ── Fleet / Vehicles ─────────────────────────────────────────
        // Compatibility reads: Fleet master data is owned by People & Skills.
        Route::get('vehicles', [FleetVehicleController::class, 'index']);
        Route::get('vehicles/{vehicle}', [FleetVehicleController::class, 'show']);

        // ── Delivery Service Providers ───────────────────────────────
        Route::get('delivery-providers/available', [DeliveryServiceProviderController::class, 'available']);
        Route::get('delivery-providers', [DeliveryServiceProviderController::class, 'index']);
        Route::post('delivery-providers', [DeliveryServiceProviderController::class, 'store']);
        Route::get('delivery-providers/{provider}', [DeliveryServiceProviderController::class, 'show']);
        Route::put('delivery-providers/{provider}', [DeliveryServiceProviderController::class, 'update']);
        Route::delete('delivery-providers/{provider}', [DeliveryServiceProviderController::class, 'destroy']);
        
        // Provider operations
        Route::patch('delivery-providers/{provider}/status', [DeliveryServiceProviderController::class, 'updateStatus']);
        Route::patch('delivery-providers/{provider}/capacity', [DeliveryServiceProviderController::class, 'updateCapacity']);
        Route::post('delivery-providers/{provider}/reset-daily-load', [DeliveryServiceProviderController::class, 'resetDailyLoad']);
        Route::post('delivery-providers/{provider}/incidents', [DeliveryServiceProviderController::class, 'recordIncident']);
        Route::get('delivery-providers/{provider}/activity-log', [DeliveryServiceProviderController::class, 'activityLog']);
        Route::get('delivery-providers/{provider}/performance-report', [DeliveryServiceProviderController::class, 'performanceReport']);
        Route::patch('exceptions/{exception}/resolve', [DeliveryExceptionController::class, 'resolve']);


        // ── Delivery Waves (pick-up / delivery route planning) ───────
        // Vehicle / driver references point at HR-owned fleet master data.
        Route::get('waves', [DeliveryWaveController::class, 'index']);
        Route::post('waves', [DeliveryWaveController::class, 'store']);
        Route::get('waves/{deliveryWave}', [DeliveryWaveController::class, 'show']);
        Route::put('waves/{deliveryWave}', [DeliveryWaveController::class, 'update']);
        Route::delete('waves/{deliveryWave}', [DeliveryWaveController::class, 'destroy']);
        Route::post('waves/{deliveryWave}/dispatch', [DeliveryWaveController::class, 'dispatch']);
        Route::post('waves/{deliveryWave}/complete', [DeliveryWaveController::class, 'complete']);
        Route::post('waves/{deliveryWave}/dwos', [DeliveryWaveController::class, 'addDwo']);
        Route::delete('waves/{deliveryWave}/dwos/{deliveryWorkOrder}', [DeliveryWaveController::class, 'removeDwo']);
        Route::post('waves/{deliveryWave}/reorder-stops', [DeliveryWaveController::class, 'reorderStops']);

        // ── Delivery Notes ──────────────────────────────────────────
        Route::get('delivery-notes', [DeliveryNoteController::class, 'index']);
        Route::get('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'show']);
        Route::patch('delivery-notes/{deliveryNote}/sign', [DeliveryNoteController::class, 'sign']);
        Route::post('delivery-notes/{deliveryNote}/confirm', [DeliveryNoteController::class, 'confirm']);

        // ── Shipment Tracking ───────────────────────────────────────
        Route::get('dwos/{deliveryWorkOrder}/tracking', [ShipmentTrackingController::class, 'history']);
        Route::post('dwos/{deliveryWorkOrder}/tracking', [ShipmentTrackingController::class, 'addEvent']);

        // ── Return Orders ───────────────────────────────────────────
        Route::get('returns', [ReturnOrderController::class, 'index']);
        Route::post('returns', [ReturnOrderController::class, 'store']);
        Route::get('returns/{returnOrder}', [ReturnOrderController::class, 'show']);
        Route::patch('returns/{returnOrder}/validate', [ReturnOrderController::class, 'validateReturn']);
        Route::delete('returns/{returnOrder}', [ReturnOrderController::class, 'destroy']);
    });
