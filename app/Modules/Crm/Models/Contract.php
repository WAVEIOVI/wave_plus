<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\Crm\Enums\ContractStatus;
use App\Modules\Crm\Enums\ContractType;
use App\Modules\Crm\Enums\PaymentTerms;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $reference
 * @property int $version
 * @property string|null $parent_contract_id
 * @property bool $is_current_version
 * @property string|null $supersedes_contract_id
 * @property string|null $amendment_reason
 * @property ContractType $type
 * @property string $client_id
 * @property string $title
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property bool $auto_renewal
 * @property int|null $renewal_notice_days
 * @property int|null $renewal_duration_months
 * @property int|null $termination_notice_days
 * @property float|null $contract_value_ht
 * @property string $currency
 * @property PaymentTerms|null $payment_terms
 * @property string|null $payment_method
 * @property float|null $credit_limit
 * @property float|null $late_payment_penalty_rate
 * @property string|null $price_list_id
 * @property int|null $sla_response_hours
 * @property int|null $sla_resolution_hours
 * @property float|null $sla_penalty_per_hour_ht
 * @property array|null $coverage_sites
 * @property array|null $covered_equipment_types
 * @property float|null $volume_commitment_value
 * @property float|null $volume_commitment_qty
 * @property float|null $min_order_value
 * @property float|null $min_call_off_qty
 * @property string|null $price_revision_clause
 * @property string|null $price_revision_frequency
 * @property int|null $standard_lead_time_days
 * @property int|null $delivery_location_default
 * @property string|null $packaging_requirements
 * @property int|null $product_warranty_period_months
 * @property string|null $product_conformity_standards
 * @property int|null $nonconformity_return_window_days
 * @property array|null $covered_catalog_categories
 * @property string|null $scope_description
 * @property array|null $authorized_requesters
 * @property string $governing_law
 * @property string|null $dispute_resolution_method
 * @property float|null $liability_cap_value
 * @property string|null $liability_cap_basis
 * @property bool $confidentiality_applies
 * @property bool $exclusivity_applies
 * @property string|null $originating_quotation_id
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property string|null $internal_notes
 * @property ContractStatus $status
 * @property string|null $renewal_status
 * @property string|null $renewed_from_id
 * @property \Illuminate\Support\Carbon|null $signed_at
 * @property string|null $signed_by_client
 * @property string|null $document_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Client $client
 * @property-read PriceList|null $priceList
 * @property-read Contract|null $parentContract
 * @property-read Contract|null $supersedesContract
 * @property-read Quotation|null $originatingQuotation
 * @property-read ClientSite|null $defaultDeliveryLocation
 * @property-read User|null $creator
 * @property-read User|null $approver
 * @property-read Contract|null $renewedFrom
 * @property-read \Illuminate\Database\Eloquent\Collection|Contract[] $renewals
 * @property-read \Illuminate\Database\Eloquent\Collection|ContractLine[] $lines
 * @property-read \Illuminate\Database\Eloquent\Collection|SalesOrder[] $salesOrders
 * @property-read \Illuminate\Database\Eloquent\Collection|Quotation[] $quotations
 */
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Contract extends Model implements HasMedia
{
    use \App\Traits\GeneratesReference, HasUlids, InteractsWithMedia, SoftDeletes;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('contract_document')
            ->useDisk('local')
            ->singleFile();
    }


    protected $fillable = [
        'reference',
        'version',
        'parent_contract_id',
        'is_current_version',
        'supersedes_contract_id',
        'amendment_reason',
        'type',
        'client_id',
        'title',
        'term_model',
        'scope_covers',
        'provider_representative_id',
        'provider_representative_name',
        'provider_representative_title',
        'client_representative_id',
        'client_representative_name',
        'client_representative_title',
        'start_date',
        'end_date',
        'auto_renewal',
        'renewal_notice_days',
        'renewal_duration_months',
        'termination_notice_days',
        'contract_value_ht',
        'currency',
        'payment_terms',
        'payment_method',
        'credit_limit',
        'late_payment_penalty_rate',
        'volume_commitment_value',
        'min_order_value',
        'price_list_id',
        'sla_response_hours',
        'sla_resolution_hours',
        'sla_penalty_per_hour_ht',
        'standard_response_time_hours',
        'service_quality_standard',
        'coverage_sites',
        'covered_equipment_types',

        'volume_commitment_qty',
        'min_call_off_qty',
        'commitment_type',
        'price_revision_applicable',
        'price_revision_clause',
        'price_revision_frequency',
        'standard_lead_time_days',
        'delivery_location_default',
        'packaging_requirements',
        'product_warranty_period_months',
        'product_conformity_standards',
        'nonconformity_return_window_days',
        'covered_catalog_categories',

        'scope_description',
        'authorized_requesters',
        'feasibility_review_sla_days',

        'governing_law',
        'dispute_resolution_method',
        'dispute_forum',
        'arbitration_body',
        'liability_capped',
        'liability_cap_value',
        'liability_cap_basis',
        'confidentiality_applies',
        'confidentiality_survival_years',
        'exclusivity_applies',
        'exclusivity_scope',
        'exclusivity_notes',
        'insurance_required',
        'insurance_minimum_coverage',
        'insurance_policy_reference',
        'cure_period_days',
        'negotiation_period_days',

        'originating_quotation_id',
        'created_by',
        'approved_by',
        'internal_notes',

        'status',
        'renewal_status',
        'renewed_from_id',
        'signed_at',
        'signed_by_client',
        'document_path',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_current_version' => 'boolean',
            'type' => ContractType::class,
            'status' => ContractStatus::class,
            'payment_terms' => PaymentTerms::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'auto_renewal' => 'boolean',
            'renewal_notice_days' => 'integer',
            'renewal_duration_months' => 'integer',
            'termination_notice_days' => 'integer',

            'contract_value_ht' => 'decimal:3',
            'credit_limit' => 'decimal:3',
            'late_payment_penalty_rate' => 'decimal:2',
            'volume_commitment_value' => 'decimal:3',
            'min_order_value' => 'decimal:3',
            'volume_commitment_qty' => 'decimal:3',
            'min_call_off_qty' => 'decimal:3',
            'liability_cap_value' => 'decimal:3',
            'insurance_minimum_coverage' => 'decimal:3',

            'sla_response_hours' => 'integer',
            'sla_resolution_hours' => 'integer',
            'sla_penalty_per_hour_ht' => 'decimal:3',
            'feasibility_review_sla_days' => 'integer',
            'cure_period_days' => 'integer',
            'negotiation_period_days' => 'integer',
            'confidentiality_survival_years' => 'integer',

            'standard_lead_time_days' => 'integer',
            'product_warranty_period_months' => 'integer',
            'nonconformity_return_window_days' => 'integer',

            'price_revision_applicable' => 'boolean',
            'liability_capped' => 'boolean',
            'confidentiality_applies' => 'boolean',
            'exclusivity_applies' => 'boolean',
            'insurance_required' => 'boolean',

            'scope_covers' => 'array',
            'coverage_sites' => 'array',
            'covered_equipment_types' => 'array',
            'covered_catalog_categories' => 'array',
            'authorized_requesters' => 'array',

            'signed_at' => 'datetime',
        ];
    }

    protected function getDocumentSequenceName(): string
    {
        return 'CT-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    // ─── Relationships ───────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function providerRepresentative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_representative_id');
    }

    public function clientRepresentative(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\BusinessPartners\Models\BpContact::class, 'client_representative_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function parentContract(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_contract_id');
    }

    public function supersedesContract(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_contract_id');
    }

    public function originatingQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'originating_quotation_id');
    }

    public function defaultDeliveryLocation(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'delivery_location_default');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'renewed_from_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ContractLine::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }
}
