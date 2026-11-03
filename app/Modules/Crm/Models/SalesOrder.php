<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Crm\Enums\FeasibilityStatus;
use App\Modules\Crm\Enums\PaymentTerms;
use App\Modules\Crm\Enums\SalesOrderStatus;
use App\Modules\Crm\Enums\SalesOrderType;
use App\Modules\Crm\Enums\ServiceFrequency;
use App\Modules\Crm\Enums\SoCreationPath;
use App\Modules\Crm\Enums\SoFulfillmentMode;
use App\Modules\Logistics\Models\DeliveryWorkOrder;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $reference
 * @property SalesOrderType $so_type
 * @property SoCreationPath|null $creation_path
 * @property string|null $quotation_id
 * @property string|null $contract_id
 * @property string $client_id
 * @property string|null $client_po_reference
 * @property string|null $client_po_document_path
 * @property \Illuminate\Support\Carbon|null $requested_delivery_date_from
 * @property \Illuminate\Support\Carbon|null $requested_delivery_date_to
 * @property \Illuminate\Support\Carbon|null $committed_delivery_date_from
 * @property \Illuminate\Support\Carbon|null $committed_delivery_date_to
 * @property SoFulfillmentMode|null $requested_fulfillment_mode
 * @property ServiceFrequency|null $service_frequency
 * @property int|null $service_frequency_custom_value
 * @property string|null $service_frequency_custom_unit
 * @property int|null $planned_mission_count
 * @property SalesOrderStatus $status
 * @property string $currency
 * @property PaymentTerms|null $payment_terms
 * @property string|null $price_list_id
 * @property \Illuminate\Support\Carbon|null $pricing_locked_at
 * @property FeasibilityStatus $feasibility_status
 * @property string|null $credit_check_result
 * @property string|null $stock_check_result
 * @property string|null $capacity_check_result
 * @property bool $requires_secondary_approval
 * @property string|null $rejection_reason
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property float $total_ht
 * @property float $total_tva
 * @property float $total_ttc
 * @property bool $has_service_lines
 * @property bool $has_goods_lines
 * @property string|null $lsa_id (read-only; set by system, not user)
 * @property string|null $gcs_id (read-only; set by system, not user)
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property int|null $confirmed_by
 * @property string|null $internal_notes
 * @property string|null $client_notes
 * @property string|null $parent_so_id
 * @property int $version_number
 * @property bool $is_current_version
 * @property \Illuminate\Support\Carbon|null $starter_locked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Client $client
 * @property-read Quotation|null $quotation
 * @property-read Contract|null $contract
 * @property-read PriceList|null $priceList
 * @property-read User|null $confirmedByUser
 * @property-read User|null $reviewedByUser
 * @property-read LsaDocument|null $lsaDocument
 * @property-read GcsDocument|null $gcsDocument
 * @property-read SalesOrder|null $parentSalesOrder
 * @property-read \Illuminate\Database\Eloquent\Collection|SalesOrder[] $revisions
 * @property-read \Illuminate\Database\Eloquent\Collection|SalesOrderLine[] $lines
 * @property-read \Illuminate\Database\Eloquent\Collection|DeliveryWorkOrder[] $deliveryWorkOrders
 * @property-read \Illuminate\Database\Eloquent\Collection|ServiceWorkOrder[] $serviceWorkOrders
 */
class SalesOrder extends Model
{
    use \App\Traits\GeneratesReference, HasUlids, SoftDeletes;

    protected $fillable = [
        'reference',
        'so_type',
        'creation_path',
        'quotation_id',
        'contract_id',
        'client_id',
        'client_po_reference',
        'client_po_document_path',
        // Date intervals (replacing old single-date fields)
        'requested_delivery_date_from',
        'requested_delivery_date_to',
        'committed_delivery_date_from',
        'committed_delivery_date_to',
        'requested_fulfillment_mode',
        'workshop_unit_id',
        // Requested resources (planning only - operational assignment on DWO/SWO)
        'requested_delivery_provider_id',
        'requested_vehicle_type',
        'delivery_requirements',
        'preferred_service_mode',
        'preferred_vehicle_type',
        'requires_mobile_workshop',
        'requires_mobile_warehouse',
        'service_requirements',
        // Service frequency (for Service-type SOs)
        'service_frequency',
        'service_frequency_custom_value',
        'service_frequency_custom_unit',
        'planned_mission_count',
        'status',
        'currency',
        'payment_terms',
        'price_list_id',
        'pricing_locked_at',
        'feasibility_status',
        'credit_check_result',
        'stock_check_result',
        'capacity_check_result',
        'requires_secondary_approval',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'total_ht',
        'total_tva',
        'total_ttc',
        'has_service_lines',
        'has_goods_lines',
        // lsa_id / gcs_id intentionally excluded from user-fillable list.
        // They are set only by DocumentGenerationService (system-managed).
        'confirmed_at',
        'confirmed_by',
        'internal_notes',
        'client_notes',
        'parent_so_id',
        'version_number',
        'is_current_version',
        'starter_locked_at',
    ];

    protected $attributes = [
        'status' => 'draft',
        'feasibility_status' => 'pending',
        'version_number' => 1,
        'is_current_version' => true,
    ];

    protected function casts(): array
    {
        return [
            'so_type' => SalesOrderType::class,
            'creation_path' => SoCreationPath::class,
            'status' => SalesOrderStatus::class,
            'payment_terms' => PaymentTerms::class,
            'feasibility_status' => FeasibilityStatus::class,
            'requested_fulfillment_mode' => SoFulfillmentMode::class,
            'service_frequency' => ServiceFrequency::class,
            'service_frequency_custom_unit' => 'string',
            'requires_secondary_approval' => 'boolean',
            'requested_delivery_date_from' => 'date',
            'requested_delivery_date_to' => 'date',
            'committed_delivery_date_from' => 'date',
            'committed_delivery_date_to' => 'date',
            'pricing_locked_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'starter_locked_at' => 'datetime',
            'total_ht' => 'decimal:3',
            'total_tva' => 'decimal:3',
            'total_ttc' => 'decimal:3',
            'has_service_lines' => 'boolean',
            'has_goods_lines' => 'boolean',
            'confirmed_at' => 'datetime',
            'version_number' => 'integer',
            'is_current_version' => 'boolean',
            'planned_mission_count' => 'integer',
            'delivery_requirements' => 'array',
            'service_requirements' => 'array',
            'requires_mobile_workshop' => 'boolean',
            'requires_mobile_warehouse' => 'boolean',
        ];
    }

    protected function getDocumentSequenceName(): string
    {
        $prefix = $this->so_type === SalesOrderType::Service ? 'SO-V' : 'SO-S';

        return $prefix.'-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    // ─── Relationships ───────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(SalesOrderLocation::class, 'sales_order_id');
    }

    public function primaryLocation(): HasOne
    {
        return $this->hasOne(SalesOrderLocation::class, 'sales_order_id')->where('is_primary', true);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function gcsDocument(): HasOne
    {
        return $this->hasOne(GcsDocument::class);
    }

    public function lsaDocument(): HasOne
    {
        return $this->hasOne(LsaDocument::class);
    }

    public function deliveryWorkOrders(): HasMany
    {
        return $this->hasMany(DeliveryWorkOrder::class);
    }

    public function serviceWorkOrders(): HasMany
    {
        return $this->hasMany(ServiceWorkOrder::class);
    }

    public function workshopUnit(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\OperationalHub\Models\WorkshopUnit::class, 'workshop_unit_id');
    }

    public function requestedDeliveryProvider(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Logistics\Models\DeliveryServiceProvider::class, 'requested_delivery_provider_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isSales(): bool
    {
        return $this->so_type === SalesOrderType::Sales;
    }

    public function isService(): bool
    {
        return $this->so_type === SalesOrderType::Service;
    }

    public function isApproved(): bool
    {
        return $this->status === SalesOrderStatus::Accepted;
    }

    public function isAccepted(): bool
    {
        return $this->status === SalesOrderStatus::Accepted;
    }

    public function canBeEdited(): bool
    {
        return $this->status === SalesOrderStatus::Draft;
    }

    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, [
            SalesOrderStatus::Completed,
            SalesOrderStatus::Fulfilled,
            SalesOrderStatus::Cancelled,
        ], true);
    }

    /**
     * Returns true when wizard starter fields are locked.
     * After the SO is first created via the wizard, starter fields
     * (client, so_type, creation_path, quotation_id, contract_id,
     * locations, requested_fulfillment_mode, date windows) cannot be
     * changed through normal update. Use createAmendment() for intentional changes.
     */
    public function isStarterLocked(): bool
    {
        return $this->starter_locked_at !== null;
    }

    /**
     * Calculate and return fulfillment state based on downstream realization.
     * Delegates to SalesOrderFulfillmentService (authoritative).
     */
    public function calculateFulfillmentStatus(): string
    {
        return app(\App\Modules\Crm\Services\SalesOrderFulfillmentService::class)
            ->calculateFulfillmentStatus($this);
    }
}
