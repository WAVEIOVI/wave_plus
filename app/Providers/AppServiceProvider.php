<?php

namespace App\Providers;

use App\Modules\BusinessPartners\Models\BpBankAccount;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\ServiceProvider as ServiceProviderPartner;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\BusinessPartners\Policies\BpBankAccountPolicy;
use App\Modules\BusinessPartners\Policies\ClientPolicy;
use App\Modules\BusinessPartners\Policies\OrganisationPolicy;
use App\Modules\BusinessPartners\Policies\ServiceProviderPolicy;
use App\Modules\BusinessPartners\Policies\SupplierPolicy;
use App\Modules\Catalog\Models\CatalogBrand;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\Catalog\Models\EquipmentClass;
use App\Modules\Catalog\Models\Guideline;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Catalog\Policies\CatalogBrandPolicy;
use App\Modules\Catalog\Policies\CatalogCategoryPolicy;
use App\Modules\Catalog\Policies\CatalogItemPolicy;
use App\Modules\Catalog\Policies\EquipmentBlueprintPolicy;
use App\Modules\Catalog\Policies\EquipmentClassPolicy;
use App\Modules\Catalog\Policies\GuidelinePolicy;
use App\Modules\Catalog\Policies\UnitOfMeasurePolicy;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\ClientAssets\Models\ClientSiteArea;
use App\Modules\ClientAssets\Models\WorkwearEmployee;
use App\Modules\ClientAssets\Models\WorkwearFabric;
use App\Modules\ClientAssets\Models\WorkwearItem;
use App\Modules\ClientAssets\Models\WorkwearModel;
use App\Modules\ClientAssets\Policies\ClientEquipmentPolicy;
use App\Modules\ClientAssets\Policies\ClientSiteAreaPolicy;
use App\Modules\ClientAssets\Policies\ClientSitePolicy;
use App\Modules\ClientAssets\Policies\WorkwearEmployeePolicy;
use App\Modules\ClientAssets\Policies\WorkwearItemPolicy;
use App\Modules\ClientAssets\Policies\WorkwearModelPolicy;
use App\Modules\Crm\Models\Contract;
use App\Modules\Crm\Models\GcsDocument;
use App\Modules\Crm\Models\LsaDocument;
use App\Modules\Crm\Models\PriceList;
use App\Modules\Crm\Models\PriceRequest;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Policies\ContractPolicy;
use App\Modules\Crm\Policies\GcsDocumentPolicy;
use App\Modules\Crm\Policies\LsaDocumentPolicy;
use App\Modules\Crm\Policies\PriceListPolicy;
use App\Modules\Crm\Policies\PriceRequestPolicy;
use App\Modules\Crm\Policies\QuotationPolicy;
use App\Modules\Crm\Policies\SalesOrderPolicy;
use App\Modules\Hr\Models\Attendance;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Policies\AttendancePolicy;
use App\Modules\Hr\Policies\EmployeePolicy;
use App\Modules\Hr\Policies\LeaveRequestPolicy;
use App\Modules\Hr\Policies\RecruitmentPolicy;
use App\Modules\Inventory\Events\GrnConfirmed;
use App\Modules\Inventory\Listeners\ProcessGrnReceipt;
use App\Modules\Inventory\Models\StockItem;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Inventory\Models\WarehouseZone;
use App\Modules\Inventory\Policies\StockItemPolicy;
use App\Modules\Inventory\Policies\StockLotPolicy;
use App\Modules\Inventory\Policies\StockMovementPolicy;
use App\Modules\Inventory\Policies\WarehouseLocationPolicy;
use App\Modules\Inventory\Policies\WarehouseSitePolicy;
use App\Modules\Inventory\Policies\WarehouseZonePolicy;
use App\Modules\Logistics\Events\ReturnValidated;
use App\Modules\Logistics\Listeners\ProcessReturnRestock;
use App\Modules\Logistics\Models\DeliveryException;
use App\Modules\Logistics\Models\DeliveryNote;
use App\Modules\Logistics\Models\DeliveryWave;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\ProofOfDelivery;
use App\Modules\Logistics\Models\ReturnOrder;
use App\Modules\Logistics\Models\ShipmentTracking;
use App\Modules\Logistics\Policies\DeliveryExceptionPolicy;
use App\Modules\Logistics\Policies\DeliveryNotePolicy;
use App\Modules\Logistics\Policies\DeliveryWavePolicy;
use App\Modules\Logistics\Policies\DeliveryWorkOrderPolicy;
use App\Modules\Logistics\Policies\ProofOfDeliveryPolicy;
use App\Modules\Logistics\Policies\ReturnOrderPolicy;
use App\Modules\Logistics\Policies\ShipmentTrackingPolicy;
use App\Modules\OperationalHub\Models\ChecklistTemplate;
use App\Modules\OperationalHub\Models\Intervention;
use App\Modules\OperationalHub\Models\Mission;
use App\Modules\OperationalHub\Models\ServiceDeliveryNote;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use App\Modules\OperationalHub\Models\WorkshopExitPass;
use App\Modules\OperationalHub\Models\WorkshopReception;
use App\Modules\OperationalHub\Models\WorkshopUnit;
use App\Modules\OperationalHub\Policies\ChecklistTemplatePolicy;
use App\Modules\OperationalHub\Policies\InterventionPolicy;
use App\Modules\OperationalHub\Policies\MissionPolicy;
use App\Modules\OperationalHub\Policies\ServiceDeliveryNotePolicy;
use App\Modules\OperationalHub\Policies\ServiceWorkOrderPolicy;
use App\Modules\OperationalHub\Policies\WorkshopExitPassPolicy;
use App\Modules\OperationalHub\Policies\WorkshopReceptionPolicy;
use App\Modules\OperationalHub\Policies\WorkshopUnitPolicy;
use App\Modules\Procurement\Models\GoodsReceiptNote;
use App\Modules\Procurement\Models\InternalPurchaseRequest;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\RfqHeader;
use App\Modules\Procurement\Policies\GoodsReceiptNotePolicy;
use App\Modules\Procurement\Policies\InternalPurchaseRequestPolicy;
use App\Modules\Procurement\Policies\PurchaseOrderPolicy;
use App\Modules\Procurement\Policies\RfqHeaderPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Modules\Communications\Email\Contracts\MailboxReaderInterface::class,
            \App\Modules\Communications\Email\Services\ImapMailboxReader::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(GrnConfirmed::class, ProcessGrnReceipt::class);
        Event::listen(ReturnValidated::class, ProcessReturnRestock::class);

        Gate::before(function ($user) {
            if ($user?->hasRole('Super Admin')) {
                return true;
            }

            return null;
        });

        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(Candidate::class, RecruitmentPolicy::class);

        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(ServiceProviderPartner::class, ServiceProviderPolicy::class);
        Gate::policy(Organisation::class, OrganisationPolicy::class);
        Gate::policy(BpBankAccount::class, BpBankAccountPolicy::class);

        Gate::policy(CatalogItem::class, CatalogItemPolicy::class);
        Gate::policy(CatalogCategory::class, CatalogCategoryPolicy::class);
        Gate::policy(CatalogBrand::class, CatalogBrandPolicy::class);
        Gate::policy(UnitOfMeasure::class, UnitOfMeasurePolicy::class);
        Gate::policy(EquipmentClass::class, EquipmentClassPolicy::class);
        Gate::policy(EquipmentBlueprint::class, EquipmentBlueprintPolicy::class);
        Gate::policy(Guideline::class, GuidelinePolicy::class);

        Gate::policy(InternalPurchaseRequest::class, InternalPurchaseRequestPolicy::class);
        Gate::policy(RfqHeader::class, RfqHeaderPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(GoodsReceiptNote::class, GoodsReceiptNotePolicy::class);

        Gate::policy(Quotation::class, QuotationPolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(PriceRequest::class, PriceRequestPolicy::class);
        Gate::policy(PriceList::class, PriceListPolicy::class);
        Gate::policy(LsaDocument::class, LsaDocumentPolicy::class);
        Gate::policy(GcsDocument::class, GcsDocumentPolicy::class);

        Gate::policy(StockItem::class, StockItemPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        Gate::policy(WarehouseSite::class, WarehouseSitePolicy::class);
        Gate::policy(WarehouseZone::class, WarehouseZonePolicy::class);
        Gate::policy(WarehouseLocation::class, WarehouseLocationPolicy::class);
        Gate::policy(StockLot::class, StockLotPolicy::class);

        Gate::policy(ClientSite::class, ClientSitePolicy::class);
        Gate::policy(ClientSiteArea::class, ClientSiteAreaPolicy::class);
        Gate::policy(ClientEquipment::class, ClientEquipmentPolicy::class);
        Gate::policy(WorkwearFabric::class, WorkwearModelPolicy::class);
        Gate::policy(WorkwearModel::class, WorkwearModelPolicy::class);
        Gate::policy(WorkwearEmployee::class, WorkwearEmployeePolicy::class);
        Gate::policy(WorkwearItem::class, WorkwearItemPolicy::class);

        Gate::policy(DeliveryWorkOrder::class, DeliveryWorkOrderPolicy::class);
        Gate::policy(DeliveryWave::class, DeliveryWavePolicy::class);
        Gate::policy(ProofOfDelivery::class, ProofOfDeliveryPolicy::class);
        Gate::policy(DeliveryException::class, DeliveryExceptionPolicy::class);
        Gate::policy(DeliveryNote::class, DeliveryNotePolicy::class);
        Gate::policy(ShipmentTracking::class, ShipmentTrackingPolicy::class);
        Gate::policy(ReturnOrder::class, ReturnOrderPolicy::class);

        Gate::policy(ServiceWorkOrder::class, ServiceWorkOrderPolicy::class);
        Gate::policy(Mission::class, MissionPolicy::class);
        Gate::policy(Intervention::class, InterventionPolicy::class);
        Gate::policy(ServiceDeliveryNote::class, ServiceDeliveryNotePolicy::class);
        Gate::policy(WorkshopUnit::class, WorkshopUnitPolicy::class);
        Gate::policy(WorkshopReception::class, WorkshopReceptionPolicy::class);
        Gate::policy(WorkshopExitPass::class, WorkshopExitPassPolicy::class);
        Gate::policy(ChecklistTemplate::class, ChecklistTemplatePolicy::class);

        // ── Financial Center Module ─────────────────────────────────
        Gate::policy(\App\Modules\Finance\Models\SalesInvoice::class, \App\Modules\Finance\Policies\FinancePolicy::class);
        Gate::policy(\App\Modules\Finance\Models\SupplierInvoice::class, \App\Modules\Finance\Policies\FinancePolicy::class);
        Gate::policy(\App\Modules\Finance\Models\Payment::class, \App\Modules\Finance\Policies\FinancePolicy::class);
        Gate::policy(\App\Modules\Finance\Models\FinancialAccount::class, \App\Modules\Finance\Policies\FinancePolicy::class);
        Gate::policy(\App\Modules\Finance\Models\Expense::class, \App\Modules\Finance\Policies\FinancePolicy::class);
        Gate::policy(\App\Modules\Finance\Models\FinancialPeriod::class, \App\Modules\Finance\Policies\FinancePolicy::class);
        Gate::policy(\App\Modules\Finance\Models\CostCenter::class, \App\Modules\Finance\Policies\FinancePolicy::class);
        Gate::policy(\App\Modules\Finance\Models\Budget::class, \App\Modules\Finance\Policies\FinancePolicy::class);
        Gate::policy(\App\Modules\Finance\Models\ChartOfAccount::class, \App\Modules\Finance\Policies\FinancePolicy::class);
        Gate::policy(\App\Modules\Finance\Models\JournalEntry::class, \App\Modules\Finance\Policies\FinancePolicy::class);

        // ── Communications Module ───────────────────────────────────────
        Gate::policy(\App\Modules\Communications\Email\Models\EmailMessage::class, \App\Modules\Communications\Email\Policies\EmailMessagePolicy::class);
        Gate::policy(\App\Modules\Communications\Email\Models\EmailSenderProfile::class, \App\Modules\Communications\Email\Policies\EmailSenderProfilePolicy::class);
        Gate::policy(\App\Modules\Communications\Email\Models\EmailTemplate::class, \App\Modules\Communications\Email\Policies\EmailTemplatePolicy::class);
        Gate::policy(\App\Modules\Communications\Email\Models\EmailMailbox::class, \App\Modules\Communications\Email\Policies\EmailMailboxPolicy::class);
        Gate::policy(\App\Modules\Communications\Email\Models\EmailConversation::class, \App\Modules\Communications\Email\Policies\EmailConversationPolicy::class);
        Gate::policy(\App\Modules\Communications\Email\Automation\Models\EmailAutomationRule::class, \App\Modules\Communications\Email\Automation\Policies\EmailAutomationPolicy::class);
        Gate::policy(\App\Modules\Communications\Email\Automation\Models\EmailApprovalRequest::class, \App\Modules\Communications\Email\Automation\Policies\EmailAutomationPolicy::class);
        Gate::policy(\App\Modules\Communications\Email\Automation\Models\EmailFollowUpTask::class, \App\Modules\Communications\Email\Automation\Policies\EmailAutomationPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\Communications\Email\Commands\SyncEmailMailboxesCommand::class,
                \App\Modules\Communications\Email\Automation\Commands\RunEmailAutomationSchedulerCommand::class,
            ]);
        }
    }
}
