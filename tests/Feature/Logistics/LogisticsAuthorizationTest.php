<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\CatalogCategory;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\UnitOfMeasure;
use App\Modules\Crm\Enums\ItemTypeSnapshot;
use App\Modules\Crm\Enums\PaymentTerms;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\SalesOrderType;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\Crm\Models\SalesOrderLine;
use App\Modules\Inventory\Models\WarehouseSite;
use App\Modules\Logistics\Enums\DeliveryWaveStatus;
use App\Modules\Logistics\Enums\DwoStatus;
use App\Modules\Logistics\Models\DeliveryException;
use App\Modules\Logistics\Models\DeliveryNote;
use App\Modules\Logistics\Models\DeliveryWave;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\Logistics\Models\DwoLine;

use App\Modules\Logistics\Models\ProofOfDelivery;
use App\Modules\Logistics\Models\ReturnOrder;
use App\Modules\Logistics\Services\DwoPickingService;
use App\Modules\Logistics\Models\ShipmentTracking;
use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\Permission;
use App\Models\Role;
use Tests\TestCase;

class LogisticsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $unauthorizedUser;
    private User $authorizedUser;
    private User $superAdminUser;

    private Client $client;
    private BpAddress $address;
    private WarehouseSite $warehouse;
    private SalesOrder $salesOrder;
    private SalesOrderLine $soLine;
    private DeliveryWorkOrder $dwo;
    private DwoLine $dwoLine;
    private DeliveryWave $wave;
    private DeliveryException $exception;
    
    private DwoLine $dwoItem;
    private DeliveryNote $deliveryNote;
    private ReturnOrder $returnOrder;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::findOrCreate('read-Logistics', 'web');
        Permission::findOrCreate('create-Logistics', 'web');
        Permission::findOrCreate('edit-Logistics', 'web');
        Permission::findOrCreate('delete-Logistics', 'web');
        Role::findOrCreate('Super Admin', 'web');

        $this->unauthorizedUser = User::factory()->create();

        $this->authorizedUser = User::factory()->create();
        $this->authorizedUser->givePermissionTo([
            'read-Logistics',
            'create-Logistics',
            'edit-Logistics',
            'delete-Logistics',
        ]);

        $this->superAdminUser = User::factory()->create();
        $this->superAdminUser->assignRole('Super Admin');

        // Setup base fixtures
        $this->client = Client::create([
            'legal_name' => 'Auth Test Client SARL',
            'status'     => 'active',
        ]);

        $this->address = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id'   => $this->client->id,
            'type'             => 'shipping',
            'label'            => 'Auth Test Site',
            'address_street'   => '123 Test Avenue',
            'city'             => 'Tunis',
            'country'          => 'Tunisia',
            'is_primary'       => true,
        ]);

        $uom = UnitOfMeasure::firstOrCreate(
            ['code' => 'UNIT'],
            ['name' => ['en' => 'Unit'], 'type' => 'count']
        );

        $category = CatalogCategory::create([
            'name' => ['fr' => 'Catégorie Test'],
        ]);

        $item = CatalogItem::create([
            'code'         => 'PROD-AUTH-' . uniqid(),
            'name'         => ['fr' => 'Produit Auth Test'],
            'item_type'    => 'product',
            'is_stockable' => true,
            'is_sellable'  => true,
            'category_id'  => $category->id,
            'uom_id'       => $uom->id,
            'status'       => 'active',
        ]);

        $variant = CatalogVariant::create([
            'catalog_item_id' => $item->id,
            'sku'             => 'SKU-AUTH-' . uniqid(),
            'name'            => 'Standard Variant',
            'is_default'      => true,
        ]);

        $this->warehouse = WarehouseSite::create([
            'code'   => 'WH-AUTH-' . uniqid(),
            'name'   => 'Entrepôt Auth Test',
            'status' => 'active',
        ]);

        $this->salesOrder = SalesOrder::create([
            'client_id'        => $this->client->id,
            'status'           => SalesOrderStatus::Accepted,
            'so_type'          => SalesOrderType::Sales,
            'payment_terms'    => PaymentTerms::Immediate,
            'has_goods_lines'  => true,
            'currency'         => 'TND',
            'total_ht'         => 100,
            'total_ttc'        => 119,
        ]);

        $this->soLine = SalesOrderLine::create([
            'sales_order_id'       => $this->salesOrder->id,
            'catalog_item_id'      => $item->id,
            'variant_id'           => $variant->id,
            'item_type_snapshot'   => ItemTypeSnapshot::PhysicalProduct,
            'description_snapshot' => 'Produit Auth Test',
            'qty'                  => 10,
            'unit_price'           => 10,
            'total_ht'             => 100,
            'total_ttc'            => 119,
        ]);

        $this->dwo = DeliveryWorkOrder::create([
            'dwo_number'             => 'DWO-AUTH-001',
            'sales_order_id'         => $this->salesOrder->id,
            'client_id'              => $this->client->id,
            'warehouse_id'           => $this->warehouse->id,
            'delivery_bp_address_id' => $this->address->id,
            'scheduled_date'         => now()->toDateString(),
            'status'                 => DwoStatus::Draft->value,
            'created_by'             => $this->authorizedUser->id,
        ]);

        $this->dwoLine = DwoLine::create([
            'dwo_id'                 => $this->dwo->id,
            'so_item_id'             => $this->soLine->id,
            'catalog_item_id'        => $item->id,
            'catalog_variant_id'     => $variant->id,
            'description_snapshot'   => 'Produit Auth Test',
            'qty_ordered'            => 10,
            'qty_allocated'          => 10,
            'qty_picked'             => 0,
            'qty_loaded'             => 0,
            'qty_delivered'          => 0,
            'created_by'             => $this->authorizedUser->id,
        ]);

        $this->wave = DeliveryWave::create([
            'wave_number'  => 'WAVE-AUTH-001',
            'warehouse_id' => $this->warehouse->id,
            'planned_date' => now()->toDateString(),
            'status'       => DeliveryWaveStatus::Draft->value,
            'created_by'   => $this->authorizedUser->id,
        ]);

        $this->exception = DeliveryException::create([
            'dwo_id'      => $this->dwo->id,
            'dwo_line_id' => $this->dwoLine->id,
            'reason'      => 'damaged_goods',
            'reported_by' => $this->authorizedUser->id,
        ]);

        

        $this->dwoItem = DwoLine::create([
            'dwo_id'               => $this->dwo->id,
            'so_item_id'           => $this->soLine->id,
            'catalog_item_id'      => $item->id,
            'catalog_variant_id'   => $variant->id,
            'description_snapshot' => 'Produit Auth Test',
            'qty_ordered'          => 5,
            'qty_allocated'        => 5,
            'qty_picked'           => 0,
            'qty_loaded'           => 0,
            'qty_delivered'        => 0,
            'created_by'           => $this->authorizedUser->id,
        ]);

        $this->deliveryNote = DeliveryNote::create([
            'dn_number'     => 'DN-AUTH-001',
            'dwo_id'        => $this->dwo->id,
            'client_id'     => $this->client->id,
            'delivery_date' => now()->toDateString(),
            'created_by'    => $this->authorizedUser->id,
        ]);

        $this->returnOrder = ReturnOrder::create([
            'return_number' => 'RET-AUTH-001',
            'dwo_id'        => $this->dwo->id,
            'client_id'     => $this->client->id,
            'return_reason' => 'Defective goods',
            'status'        => 'draft',
            'created_by'    => $this->authorizedUser->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DeliveryWorkOrder Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_unauthorized_user_cannot_view_dwo_meta(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson('/api/logistics/dwos/meta');
        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_dwo_meta(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson('/api/logistics/dwos/meta');
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_list_dwos(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson('/api/logistics/dwos');
        $response->assertForbidden();
    }

    public function test_authorized_user_can_list_dwos(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson('/api/logistics/dwos');
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_view_dwo_kanban(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson('/api/logistics/dwos/kanban');
        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_dwo_kanban(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson('/api/logistics/dwos/kanban');
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_view_eligible_so_lines(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson("/api/logistics/dwos/eligible-so-lines/{$this->salesOrder->id}");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_eligible_so_lines(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson("/api/logistics/dwos/eligible-so-lines/{$this->salesOrder->id}");
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_create_dwo_from_so(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->postJson('/api/logistics/dwos/from-so', [
            'sales_order_id'         => $this->salesOrder->id,
            'delivery_bp_address_id' => $this->address->id,
            'lines'                  => [
                ['so_line_id' => $this->soLine->id, 'qty_to_deliver' => 5],
            ],
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_create_dwo_from_so(): void
    {
        $so = SalesOrder::create([
            'client_id'        => $this->client->id,
            'status'           => SalesOrderStatus::Accepted,
            'so_type'          => SalesOrderType::Sales,
            'payment_terms'    => PaymentTerms::Immediate,
            'has_goods_lines'  => true,
            'currency'         => 'TND',
            'total_ht'         => 100,
            'total_ttc'        => 119,
        ]);

        $line = SalesOrderLine::create([
            'sales_order_id'       => $so->id,
            'catalog_item_id'      => $this->dwoLine->catalog_item_id,
            'variant_id'           => $this->dwoLine->catalog_variant_id,
            'item_type_snapshot'   => ItemTypeSnapshot::PhysicalProduct,
            'description_snapshot' => 'Produit Auth Test',
            'qty'                  => 10,
            'unit_price'           => 10,
            'total_ht'             => 100,
            'total_ttc'            => 119,
        ]);

        Sanctum::actingAs($this->authorizedUser);
        $response = $this->postJson('/api/logistics/dwos/from-so', [
            'sales_order_id'         => $so->id,
            'delivery_bp_address_id' => $this->address->id,
            'lines'                  => [
                ['so_line_id' => $line->id, 'qty_to_deliver' => 5],
            ],
        ]);
        $response->assertCreated();
    }

    public function test_unauthorized_user_cannot_show_dwo(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson("/api/logistics/dwos/{$this->dwo->id}");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_show_dwo(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson("/api/logistics/dwos/{$this->dwo->id}");
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_update_dwo(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->putJson("/api/logistics/dwos/{$this->dwo->id}", [
            'priority' => 'high',
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_update_dwo(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->putJson("/api/logistics/dwos/{$this->dwo->id}", [
            'priority' => 'high',
        ]);
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_delete_dwo(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->deleteJson("/api/logistics/dwos/{$this->dwo->id}");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_delete_draft_dwo(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->deleteJson("/api/logistics/dwos/{$this->dwo->id}");
        $response->assertNoContent();
    }

    public function test_unauthorized_user_cannot_transition_dwo(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->patchJson("/api/logistics/dwos/{$this->dwo->id}/transition", [
            'status' => 'planned',
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_transition_dwo(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->patchJson("/api/logistics/dwos/{$this->dwo->id}/transition", [
            'status' => 'planned',
        ]);
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_record_pick_for_dwo(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->postJson("/api/logistics/dwos/{$this->dwo->id}/lines/{$this->dwoLine->id}/pick", [
            'qty_picked' => 5,
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_record_pick_for_dwo(): void
    {
        $this->dwo->update(['status' => DwoStatus::Picking->value]);

        Sanctum::actingAs($this->authorizedUser);
        $response = $this->postJson("/api/logistics/dwos/{$this->dwo->id}/lines/{$this->dwoLine->id}/pick", [
            'qty_picked' => 5,
        ]);
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_batch_pick(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->postJson("/api/logistics/dwos/{$this->dwo->id}/picks", [
            'picks' => [
                ['line_id' => $this->dwoLine->id, 'qty_picked' => 5],
            ],
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_batch_pick(): void
    {
        $this->dwo->update(['status' => DwoStatus::Picking->value]);

        Sanctum::actingAs($this->authorizedUser);
        $response = $this->postJson("/api/logistics/dwos/{$this->dwo->id}/picks", [
            'picks' => [
                ['line_id' => (string) $this->dwoLine->id, 'qty_picked' => 5],
            ],
        ]);
        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | ProofOfDelivery Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_unauthorized_user_cannot_view_pod(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson("/api/logistics/dwos/{$this->dwo->id}/pod");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_pod(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson("/api/logistics/dwos/{$this->dwo->id}/pod");
        // No pod yet, returns 404 with authorized check passed
        $response->assertNotFound();
    }

    public function test_unauthorized_user_cannot_store_pod(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->postJson("/api/logistics/dwos/{$this->dwo->id}/pod", [
            'receiver_name' => 'Foulen Ben Foulen',
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_store_pod(): void
    {
        $this->dwo->update(['status' => DwoStatus::Delivered->value]);

        Sanctum::actingAs($this->authorizedUser);
        $response = $this->postJson("/api/logistics/dwos/{$this->dwo->id}/pod", [
            'receiver_name' => 'Foulen Ben Foulen',
        ]);
        $response->assertCreated();
    }

    /*
    |--------------------------------------------------------------------------
    | DeliveryException Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_unauthorized_user_cannot_list_exceptions(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson("/api/logistics/dwos/{$this->dwo->id}/exceptions");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_list_exceptions(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson("/api/logistics/dwos/{$this->dwo->id}/exceptions");
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_create_exception(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->postJson("/api/logistics/dwos/{$this->dwo->id}/exceptions", [
            'reason' => 'customer_unavailable',
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_create_exception(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->postJson("/api/logistics/dwos/{$this->dwo->id}/exceptions", [
            'reason' => 'customer_unavailable',
        ]);
        $response->assertCreated();
    }

    public function test_unauthorized_user_cannot_resolve_exception(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->patchJson("/api/logistics/exceptions/{$this->exception->id}/resolve", [
            'resolution_notes' => 'Resolved after contacting client',
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_resolve_exception(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->patchJson("/api/logistics/exceptions/{$this->exception->id}/resolve", [
            'resolution_notes' => 'Resolved after contacting client',
        ]);
        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | DeliveryWave Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_unauthorized_user_cannot_list_waves(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson('/api/logistics/waves');
        $response->assertForbidden();
    }

    public function test_authorized_user_can_list_waves(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson('/api/logistics/waves');
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_create_wave(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->postJson('/api/logistics/waves', [
            'planned_date' => now()->toDateString(),
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_create_wave(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->postJson('/api/logistics/waves', [
            'planned_date' => now()->toDateString(),
        ]);
        $response->assertCreated();
    }

    public function test_unauthorized_user_cannot_show_wave(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson("/api/logistics/waves/{$this->wave->id}");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_show_wave(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson("/api/logistics/waves/{$this->wave->id}");
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_update_wave(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->putJson("/api/logistics/waves/{$this->wave->id}", [
            'route_notes' => 'Updated notes',
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_update_wave(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->putJson("/api/logistics/waves/{$this->wave->id}", [
            'route_notes' => 'Updated notes',
        ]);
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_delete_wave(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->deleteJson("/api/logistics/waves/{$this->wave->id}");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_delete_draft_wave(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->deleteJson("/api/logistics/waves/{$this->wave->id}");
        $response->assertNoContent();
    }

    public function test_unauthorized_user_cannot_dispatch_wave(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->postJson("/api/logistics/waves/{$this->wave->id}/dispatch");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_dispatch_wave(): void
    {
        $vehicle = Vehicle::create([
            'plate_number' => 'TU-' . rand(100, 999) . '-AUTH',
            'label'        => 'Camion Tournée',
            'type'         => 'truck',
            'capacity_kg'  => 3500,
            'status'       => 'available',
        ]);

        // Dispatch requires an HR-approved driver (authoritative fleet ownership under HR)
        $employee = Employee::create([
            'employee_number' => 'EMP-DISP-' . uniqid(),
            'first_name'      => 'Dispatch',
            'last_name'       => 'Driver',
            'status'          => 'active',
            'hire_date'       => now()->toDateString(),
        ]);

        $approvedDriver = ApprovedDriver::create([
            'employee_id'          => $employee->id,
            'authorization_status' => 'approved',
            'license_number'       => 'LIC-' . uniqid(),
            'license_category'     => 'C',
        ]);

        $this->wave->update([
            'approved_driver_id' => $approvedDriver->id,
            'vehicle_id'         => $vehicle->id,
        ]);

        Sanctum::actingAs($this->authorizedUser);
        $response = $this->postJson("/api/logistics/waves/{$this->wave->id}/dispatch");
        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | PickingList Authorization Tests
    |--------------------------------------------------------------------------
    */

    

    

    

    

    

    

    

    

    /*
    |--------------------------------------------------------------------------
    | DeliveryNote Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_unauthorized_user_cannot_list_delivery_notes(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson('/api/logistics/delivery-notes');
        $response->assertForbidden();
    }

    public function test_authorized_user_can_list_delivery_notes(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson('/api/logistics/delivery-notes');
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_show_delivery_note(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson("/api/logistics/delivery-notes/{$this->deliveryNote->id}");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_show_delivery_note(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson("/api/logistics/delivery-notes/{$this->deliveryNote->id}");
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_sign_delivery_note(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->patchJson("/api/logistics/delivery-notes/{$this->deliveryNote->id}/sign", [
            'signature_data' => 'data:image/png;base64,...',
            'recipient_name' => 'Foulen Ben Foulen',
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_sign_delivery_note(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->patchJson("/api/logistics/delivery-notes/{$this->deliveryNote->id}/sign", [
            'signature_data' => 'data:image/png;base64,...',
            'recipient_name' => 'Foulen Ben Foulen',
        ]);
        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | ShipmentTracking Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_unauthorized_user_cannot_view_tracking_history(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson("/api/logistics/dwos/{$this->dwo->id}/tracking");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_tracking_history(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson("/api/logistics/dwos/{$this->dwo->id}/tracking");
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_add_tracking_event(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->postJson("/api/logistics/dwos/{$this->dwo->id}/tracking", [
            'event_type'   => 'checkpoint',
            'status_label' => 'Passing checkpoint 1',
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_add_tracking_event(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->postJson("/api/logistics/dwos/{$this->dwo->id}/tracking", [
            'event_type'   => 'checkpoint',
            'status_label' => 'Passing checkpoint 1',
        ]);
        $response->assertCreated();
    }

    /*
    |--------------------------------------------------------------------------
    | ReturnOrder Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_unauthorized_user_cannot_list_returns(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson('/api/logistics/returns');
        $response->assertForbidden();
    }

    public function test_authorized_user_can_list_returns(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson('/api/logistics/returns');
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_create_return(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->postJson('/api/logistics/returns', [
            'client_id'     => $this->client->id,
            'dwo_id'        => $this->dwo->id,
            'return_reason' => 'Defective goods',
        ]);
        $response->assertForbidden();
    }

    public function test_authorized_user_can_create_return(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->postJson('/api/logistics/returns', [
            'client_id'     => $this->client->id,
            'dwo_id'        => $this->dwo->id,
            'return_reason' => 'Defective goods',
        ]);
        $response->assertCreated();
    }

    public function test_unauthorized_user_cannot_show_return(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->getJson("/api/logistics/returns/{$this->returnOrder->id}");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_show_return(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->getJson("/api/logistics/returns/{$this->returnOrder->id}");
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_validate_return(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->patchJson("/api/logistics/returns/{$this->returnOrder->id}/validate");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_validate_return(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->patchJson("/api/logistics/returns/{$this->returnOrder->id}/validate");
        // Status 200 or processed
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_delete_return(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $response = $this->deleteJson("/api/logistics/returns/{$this->returnOrder->id}");
        $response->assertForbidden();
    }

    public function test_authorized_user_can_delete_return(): void
    {
        Sanctum::actingAs($this->authorizedUser);
        $response = $this->deleteJson("/api/logistics/returns/{$this->returnOrder->id}");
        $response->assertNoContent();
    }

    /*
    |--------------------------------------------------------------------------
    | Super Admin Bypass Test
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_bypasses_all_logistics_authorization(): void
    {
        Sanctum::actingAs($this->superAdminUser);

        $this->getJson('/api/logistics/dwos')->assertOk();
        $this->getJson('/api/logistics/dwos/meta')->assertOk();
        $this->getJson('/api/logistics/waves')->assertOk();
        
        $this->getJson('/api/logistics/delivery-notes')->assertOk();
        $this->getJson('/api/logistics/returns')->assertOk();
    }
}
