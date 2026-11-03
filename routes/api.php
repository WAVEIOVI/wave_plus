<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\RegistrationRequestController;
use App\Http\Controllers\ResourceValidationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPdfController;
use App\Modules\Shared\Controllers\BrandLogoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded within the "api" middleware group. Enjoy building!
|
*/

// ── Public Auth & Registration Request Routes ───────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    // NOTE: Direct user registration is disabled. Visitors submit registration requests.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
    });
});

// ── Public Registration Requests (Visitors submit request to register) ──
Route::prefix('public/registration-requests')
    ->middleware(['throttle:10,1'])
    ->group(function () {
        Route::post('/', [RegistrationRequestController::class, 'publicStore']);
    });

// ── Global Search (all authenticated users) ─────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('search', [GlobalSearchController::class, 'search']);
});

// ── Admin Routes (requires authentication + Super Admin|Admin role) ─────
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:Super Admin|Admin'])
    ->group(function () {

        // Users PDF Export
        Route::get('users/export/pdf', [UserPdfController::class, 'exportList']);
        Route::get('users/{id}/export/pdf', [UserPdfController::class, 'exportSingle']);

        // Users Options for Wizard / Scoping
        Route::get('users/creation-options', [UserController::class, 'creationOptions']);

        // Users CRUD
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{id}', [UserController::class, 'show']);
        Route::put('users/{id}', [UserController::class, 'update']);
        Route::put('users/{id}/password', [UserController::class, 'changePassword']);
        Route::delete('users/{id}', [UserController::class, 'destroy']);

        // Registration Requests Management
        Route::get('registration-requests', [RegistrationRequestController::class, 'index']);
        Route::post('registration-requests', [RegistrationRequestController::class, 'store']);
        Route::get('registration-requests/{id}', [RegistrationRequestController::class, 'show']);
        Route::put('registration-requests/{id}/status', [RegistrationRequestController::class, 'updateStatus']);
        Route::post('registration-requests/{id}/approve', [RegistrationRequestController::class, 'approve']);
        Route::delete('registration-requests/{id}', [RegistrationRequestController::class, 'destroy']);

        // Roles
        Route::get('roles', [RoleController::class, 'index']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::get('roles/{id}', [RoleController::class, 'show']);
        Route::put('roles/{id}', [RoleController::class, 'update']);
        Route::put('roles/{id}/permissions', [RoleController::class, 'syncPermissions']);
        Route::delete('roles/{id}', [RoleController::class, 'destroy']);

        // Permissions (read-only)
        Route::get('permissions', [RoleController::class, 'permissions']);
    });

// ── Module Route Files ──────────────────────────────────────────────
require base_path('app/Modules/BusinessPartners/routes/api.php');
require base_path('app/Modules/Catalog/routes/api.php');
require base_path('app/Modules/Procurement/routes/api.php');
require base_path('app/Modules/Crm/routes/api.php');
require base_path('app/Modules/Inventory/routes/api.php');
require base_path('app/Modules/ClientAssets/routes/api.php');

// ── Brand Logo API (Shared) ──────────────────────────────────────────
Route::prefix('brand-logo')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('config', [BrandLogoController::class, 'getConfig']);
        Route::get('resolve', [BrandLogoController::class, 'resolve']);
        Route::get('search', [BrandLogoController::class, 'search']);
    });

// ── Resource Validation (Cross-Module) ──────────────────────────────────
Route::prefix('resource-validation')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::post('validate-dwo', [ResourceValidationController::class, 'validateDwo']);
        Route::post('validate-swo', [ResourceValidationController::class, 'validateSwo']);
        Route::get('vehicles/{vehicle}/validate', [ResourceValidationController::class, 'validateVehicle']);
        Route::get('drivers/{driver}/validate', [ResourceValidationController::class, 'validateDriver']);
        Route::get('vehicles/{vehicle}/available-drivers', [ResourceValidationController::class, 'availableDriversForVehicle']);
        Route::get('drivers/{driver}/available-vehicles', [ResourceValidationController::class, 'availableVehiclesForDriver']);
        Route::get('compatibility/{vehicle}/{driver}', [ResourceValidationController::class, 'validateCompatibility']);
    });

// ── Logistics Module ────────────────────────────────────────────────────
// Routes are defined in app/Modules/Logistics/routes/api.php
require base_path('app/Modules/Logistics/routes/api.php');

// ── HR / People & Skills Module ─────────────────────────────────────────
// Routes are defined in app/Modules/Hr/routes/api.php
require base_path('app/Modules/Hr/routes/api.php');

// ── Operational Hub & Workshop Module ───────────────────────────────────
// Routes are defined in app/Modules/OperationalHub/routes/api.php
require base_path('app/Modules/OperationalHub/routes/api.php');

// ── Financial Center Module ─────────────────────────────────────────────
// Routes are defined in app/Modules/Finance/routes/api.php
require base_path('app/Modules/Finance/routes/api.php');

// -- Communications Module
// Routes are defined in app/Modules/Communications/routes/api.php
require base_path('app/Modules/Communications/routes/api.php');
