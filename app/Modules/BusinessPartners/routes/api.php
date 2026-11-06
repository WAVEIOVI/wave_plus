<?php

use App\Modules\BusinessPartners\Controllers\BpAddressController;
use App\Modules\BusinessPartners\Controllers\BpBankAccountController;
use App\Modules\BusinessPartners\Controllers\BpContactController;
use App\Modules\BusinessPartners\Controllers\BpDocumentController;
use App\Modules\BusinessPartners\Controllers\BpVatCertificateController;
use App\Modules\BusinessPartners\Controllers\BusinessPartnerDashboardController;
use App\Modules\BusinessPartners\Controllers\BusinessPartnerEmailController;
use App\Modules\BusinessPartners\Controllers\ClientController;
use App\Modules\BusinessPartners\Controllers\ClientCsvController;
use App\Modules\BusinessPartners\Controllers\ClientPdfController;
use App\Modules\BusinessPartners\Controllers\EmailSearchController;
use App\Modules\BusinessPartners\Controllers\OrganisationController;
use App\Modules\BusinessPartners\Controllers\ServiceProviderController;
use App\Modules\BusinessPartners\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

// ── Business Partners Module ────────────────────────────────────────────
Route::prefix('bp')
    ->middleware(['auth:sanctum', 'bp.access'])
    ->group(function () {

        // Dashboard
        Route::get('dashboard-stats', [BusinessPartnerDashboardController::class, 'dashboardStats']);

        // Global Email Search
        Route::get('emails/search', [EmailSearchController::class, 'search']);

        // Clients PDF / CSV Export / Contextual Email
        Route::match(['get', 'post'], 'clients/export/pdf', [ClientPdfController::class, 'exportList']);
        Route::get('clients/{id}/export/pdf', [ClientPdfController::class, 'exportSingle']);
        Route::match(['get', 'post'], 'clients/export/csv', [ClientCsvController::class, 'exportList']);
        Route::get('clients/{id}/contextual-email', [BusinessPartnerEmailController::class, 'prepare'])->defaults('partnerType', 'client');
        Route::post('clients/send-email', [ClientController::class, 'sendEmailList']);
        Route::post('clients/{id}/send-email', [BusinessPartnerEmailController::class, 'send'])->defaults('partnerType', 'client');
        Route::post('clients/bulk-email/preview', [BusinessPartnerEmailController::class, 'bulkPreview'])->defaults('partnerType', 'client');
        Route::post('clients/bulk-email', [BusinessPartnerEmailController::class, 'bulkSend'])->defaults('partnerType', 'client');

        // Clients CRUD
        Route::get('clients/meta', [ClientController::class, 'meta']);
        Route::get('clients/options', [ClientController::class, 'options']);
        Route::get('clients', [ClientController::class, 'index']);
        Route::post('clients', [ClientController::class, 'store']);
        Route::get('clients/{client}', [ClientController::class, 'show']);
        Route::put('clients/{client}', [ClientController::class, 'update']);
        Route::delete('clients/{client}', [ClientController::class, 'destroy']);

        // Static supplier routes
        Route::get('suppliers/export/pdf', [SupplierController::class, 'exportPdf']);
        Route::get('suppliers', [SupplierController::class, 'index']);
        Route::post('suppliers', [SupplierController::class, 'store']);

        // Dynamic supplier routes
        Route::get('suppliers/{id}/scorecard', [SupplierController::class, 'scorecard']);
        Route::get('suppliers/{id}/export/pdf', [SupplierController::class, 'exportSinglePdf']);
        Route::get('suppliers/{id}/contextual-email', [BusinessPartnerEmailController::class, 'prepare'])->defaults('partnerType', 'supplier');
        Route::post('suppliers/send-email', [SupplierController::class, 'sendEmailList']);
        Route::post('suppliers/{id}/send-email', [BusinessPartnerEmailController::class, 'send'])->defaults('partnerType', 'supplier');
        Route::post('suppliers/bulk-email/preview', [BusinessPartnerEmailController::class, 'bulkPreview'])->defaults('partnerType', 'supplier');
        Route::post('suppliers/bulk-email', [BusinessPartnerEmailController::class, 'bulkSend'])->defaults('partnerType', 'supplier');

        // Suppliers CRUD
        Route::get('suppliers/meta', [SupplierController::class, 'meta']);
        Route::get('suppliers/options', [SupplierController::class, 'options']);
        Route::get('suppliers', [SupplierController::class, 'index']);
        Route::post('suppliers', [SupplierController::class, 'store']);
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);

        // Service Providers PDF Export / Email
        Route::get('service-providers/export/pdf', [ServiceProviderController::class, 'exportPdf']);
        Route::get('service-providers/{id}/export/pdf', [ServiceProviderController::class, 'exportSinglePdf']);
        Route::get('service-providers/{id}/contextual-email', [BusinessPartnerEmailController::class, 'prepare'])->defaults('partnerType', 'service_provider');
        Route::post('service-providers/send-email', [ServiceProviderController::class, 'sendEmailList']);
        Route::post('service-providers/{id}/send-email', [BusinessPartnerEmailController::class, 'send'])->defaults('partnerType', 'service_provider');
        Route::post('service-providers/bulk-email/preview', [BusinessPartnerEmailController::class, 'bulkPreview'])->defaults('partnerType', 'service_provider');
        Route::post('service-providers/bulk-email', [BusinessPartnerEmailController::class, 'bulkSend'])->defaults('partnerType', 'service_provider');

        // Service Providers CRUD
        Route::get('service-providers/meta', [ServiceProviderController::class, 'meta']);
        Route::get('service-providers/options', [ServiceProviderController::class, 'options']);
        Route::get('service-providers', [ServiceProviderController::class, 'index']);
        Route::post('service-providers', [ServiceProviderController::class, 'store']);
        Route::get('service-providers/{service_provider}', [ServiceProviderController::class, 'show']);
        Route::put('service-providers/{service_provider}', [ServiceProviderController::class, 'update']);
        Route::delete('service-providers/{service_provider}', [ServiceProviderController::class, 'destroy']);

        // Organisations PDF Export / Email
        Route::get('organisations/export/pdf', [OrganisationController::class, 'exportPdf']);
        Route::get('organisations/{id}/export/pdf', [OrganisationController::class, 'exportSinglePdf']);
        Route::get('organisations/{id}/contextual-email', [BusinessPartnerEmailController::class, 'prepare'])->defaults('partnerType', 'organisation');
        Route::post('organisations/send-email', [OrganisationController::class, 'sendEmailList']);
        Route::post('organisations/{id}/send-email', [BusinessPartnerEmailController::class, 'send'])->defaults('partnerType', 'organisation');
        Route::post('organisations/bulk-email/preview', [BusinessPartnerEmailController::class, 'bulkPreview'])->defaults('partnerType', 'organisation');
        Route::post('organisations/bulk-email', [BusinessPartnerEmailController::class, 'bulkSend'])->defaults('partnerType', 'organisation');

        // Bulk Email Batches
        Route::get('bulk-email/batches/{batchId}', [BusinessPartnerEmailController::class, 'showBatch']);
        Route::get('bulk-email/batches', [BusinessPartnerEmailController::class, 'listBatches']);

        // Organisations CRUD
        Route::get('organisations/meta', [OrganisationController::class, 'meta']);
        Route::get('organisations/options', [OrganisationController::class, 'options']);
        Route::get('organisations', [OrganisationController::class, 'index']);
        Route::post('organisations', [OrganisationController::class, 'store']);
        Route::get('organisations/{organisation}', [OrganisationController::class, 'show']);
        Route::put('organisations/{organisation}', [OrganisationController::class, 'update']);
        Route::delete('organisations/{organisation}', [OrganisationController::class, 'destroy']);

        // ── Nested Polymorphic Sub-Resources ─────────────────────────────

        // Contact ↔ Address Linking (Must register before wildcard owners)
        Route::post('contacts/{contactId}/addresses', [BpContactController::class, 'linkAddress']);
        Route::delete('contacts/{contactId}/addresses/{addressId}', [BpContactController::class, 'unlinkAddress']);

        // Addresses for any partner type
        Route::get('{ownerType}/{ownerId}/addresses', [BpAddressController::class, 'index']);
        Route::post('{ownerType}/{ownerId}/addresses', [BpAddressController::class, 'store']);
        Route::get('addresses/{address}', [BpAddressController::class, 'show']);
        Route::put('addresses/{address}', [BpAddressController::class, 'update']);
        Route::delete('addresses/{address}', [BpAddressController::class, 'destroy']);

        // Contacts for any partner type
        Route::get('{ownerType}/{ownerId}/contacts', [BpContactController::class, 'index']);
        Route::post('{ownerType}/{ownerId}/contacts', [BpContactController::class, 'store']);
        Route::get('contacts/{contact}', [BpContactController::class, 'show']);
        Route::put('contacts/{contact}', [BpContactController::class, 'update']);
        Route::delete('contacts/{contact}', [BpContactController::class, 'destroy']);

        // Bank Accounts for any partner type
        Route::get('{ownerType}/{ownerId}/bank-accounts', [BpBankAccountController::class, 'index']);
        Route::post('{ownerType}/{ownerId}/bank-accounts', [BpBankAccountController::class, 'store']);
        Route::get('bank-accounts/{bankAccount}', [BpBankAccountController::class, 'show']);
        Route::put('bank-accounts/{bankAccount}', [BpBankAccountController::class, 'update']);
        Route::delete('bank-accounts/{bankAccount}', [BpBankAccountController::class, 'destroy']);

        // VAT Certificates for any partner type
        Route::get('{ownerType}/{ownerId}/vat-certificates', [BpVatCertificateController::class, 'index']);
        Route::post('{ownerType}/{ownerId}/vat-certificates', [BpVatCertificateController::class, 'store']);
        Route::get('vat-certificates/{vatCertificate}', [BpVatCertificateController::class, 'show']);
        Route::put('vat-certificates/{vatCertificate}', [BpVatCertificateController::class, 'update']);
        Route::delete('vat-certificates/{vatCertificate}', [BpVatCertificateController::class, 'destroy']);

        // Documents for any partner type
        Route::get('{ownerType}/{ownerId}/documents', [BpDocumentController::class, 'index']);
        Route::post('{ownerType}/{ownerId}/documents', [BpDocumentController::class, 'store']);
        Route::get('documents/{document}', [BpDocumentController::class, 'show']);
        Route::put('documents/{document}', [BpDocumentController::class, 'update']);
        Route::delete('documents/{document}', [BpDocumentController::class, 'destroy']);
        Route::get('documents/{document}/download', [BpDocumentController::class, 'download']);
    });
