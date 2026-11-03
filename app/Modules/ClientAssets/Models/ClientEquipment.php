<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Catalog\Models\CatalogBrand;
use App\Modules\Catalog\Models\CatalogItem;
use App\Modules\Catalog\Models\CatalogVariant;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\Catalog\Models\EquipmentClass;
use App\Modules\ClientAssets\Enums\ComplianceStatus;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $equipment_code
 * @property string $uuid
 * @property string $client_id
 * @property string|null $site_id
 * @property string|null $area_id
 * @property string|null $equipment_class_id
 * @property string|null $blueprint_id
 * @property string|null $catalog_item_id
 * @property string|null $catalog_variant_id
 * @property string|null $brand_id
 * @property string|null $brand
 * @property string|null $model_name
 * @property string|null $serial_number
 * @property string|null $internal_number
 * @property string|null $manufacturer_part_number
 * @property string|null $asset_tag
 * @property \Illuminate\Support\Carbon|null $manufacture_date
 * @property \Illuminate\Support\Carbon|null $install_date
 * @property \Illuminate\Support\Carbon|null $commissioning_date
 * @property \Illuminate\Support\Carbon|null $decommissioning_date
 * @property \Illuminate\Support\Carbon|null $warranty_start_date
 * @property \Illuminate\Support\Carbon|null $warranty_end_date
 * @property \Illuminate\Support\Carbon|null $last_inspection_date
 * @property \Illuminate\Support\Carbon|null $next_inspection_date
 * @property int|null $inspection_freq_months
 * @property string $status
 * @property string $lifecycle_status
 * @property string $compliance_status
 * @property string $condition_status
 * @property string $criticality
 * @property string|null $description
 * @property array|null $specs
 * @property array|null $certifications
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Modules\BusinessPartners\Models\Client|null $client
 * @property-read \App\Modules\ClientAssets\Models\ClientSite|null $site
 * @property-read \App\Modules\ClientAssets\Models\ClientSiteArea|null $area
 * @property-read \App\Modules\Catalog\Models\EquipmentClass|null $equipmentClass
 * @property-read \App\Modules\Catalog\Models\EquipmentBlueprint|null $blueprint
 * @property-read \App\Modules\ClientAssets\Models\EquipmentQrCode|null $currentQrCode
 */
class ClientEquipment extends Model implements HasMedia
{
    use GeneratesReference, HasUlids, InteractsWithMedia, SoftDeletes;

    protected $table = 'client_equipment';

    public const DOCUMENT_TYPE_PREFIX = 'EQ';

    protected $fillable = [
        'equipment_code',
        'uuid',
        'client_id',
        'site_id',
        'area_id',
        'equipment_class_id',
        'blueprint_id',
        'catalog_item_id',
        'catalog_variant_id',
        'brand_id',
        'brand',
        'model_name',
        'serial_number',
        'internal_number',
        'manufacturer_part_number',
        'asset_tag',
        'manufacture_date',
        'install_date',
        'commissioning_date',
        'decommissioning_date',
        'warranty_start_date',
        'warranty_end_date',
        'last_inspection_date',
        'next_inspection_date',
        'inspection_freq_months',
        'status',
        'lifecycle_status',
        'compliance_status',
        'condition_status',
        'criticality',
        'description',
        'specs',
        'certifications',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'manufacture_date' => 'date',
            'install_date' => 'date',
            'commissioning_date' => 'date',
            'decommissioning_date' => 'date',
            'warranty_start_date' => 'date',
            'warranty_end_date' => 'date',
            'last_inspection_date' => 'date',
            'next_inspection_date' => 'date',
            'inspection_freq_months' => 'integer',
            'specs' => 'array',
            'certifications' => 'array',
        ];
    }

    // ─── Boot ────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = 'active';
            }
            if (empty($model->lifecycle_status)) {
                $model->lifecycle_status = 'operational';
            }
            if (empty($model->condition_status)) {
                $model->condition_status = 'good';
            }
            if (empty($model->criticality)) {
                $model->criticality = 'medium';
            }
        });

        static::saving(function (self $model) {
            $model->compliance_status = $model->computeComplianceStatus()->value;
        });
    }

    // ─── Reference generation ────────────────────────────────────

    protected function getReferenceColumn(): string
    {
        return 'equipment_code';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('EQ-%s-%04d', now()->format('ymd'), $nextValue);
    }

    // ─── Compliance Auto-Compute ────────────────────────────────

    public function computeComplianceStatus(): ComplianceStatus
    {
        $earliestDue = $this->next_inspection_date;

        if ($this->relationLoaded('interventionCycles') && $this->interventionCycles->isNotEmpty()) {
            $nextCycleDue = $this->interventionCycles->where('enabled', true)->pluck('next_due_date')->filter()->sort()->first();
            if ($nextCycleDue) {
                $earliestDue = $nextCycleDue;
            }
        }

        if (! $earliestDue) {
            return ComplianceStatus::NOT_APPLICABLE;
        }

        $nextInspection = Carbon::parse($earliestDue);
        $now = Carbon::now();

        if ($nextInspection->isPast()) {
            return ComplianceStatus::OVERDUE;
        }

        // Due within 30 days
        if ($nextInspection->diffInDays($now) <= 30) {
            return ComplianceStatus::DUE_SOON;
        }

        return ComplianceStatus::COMPLIANT;
    }

    // ─── Media Collections ──────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('public');

        $this->addMediaCollection('certificates')
            ->useDisk('local');

        $this->addMediaCollection('documents')
            ->useDisk('local');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->performOnCollections('photos')
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(1200)
            ->height(1200)
            ->performOnCollections('photos')
            ->nonQueued();
    }

    public function getMainPhoto(): ?Media
    {
        $photos = $this->getMedia('photos');
        $primary = $photos->first(fn (Media $m) => $m->getCustomProperty('is_main') === true);

        return $primary ?: $photos->first();
    }

    // ─── Relationships ───────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'site_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(ClientSiteArea::class, 'area_id');
    }

    public function equipmentClass(): BelongsTo
    {
        return $this->belongsTo(EquipmentClass::class, 'equipment_class_id');
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(EquipmentBlueprint::class, 'blueprint_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function catalogVariant(): BelongsTo
    {
        return $this->belongsTo(CatalogVariant::class, 'catalog_variant_id');
    }

    public function catalogBrand(): BelongsTo
    {
        return $this->belongsTo(CatalogBrand::class, 'brand_id');
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(EquipmentQrCode::class, 'equipment_id')->orderByDesc('assigned_at');
    }

    public function currentQrCode(): HasOne
    {
        return $this->hasOne(EquipmentQrCode::class, 'equipment_id')->where('is_current', true);
    }

    public function interventionCycles(): HasMany
    {
        return $this->hasMany(EquipmentInterventionCycle::class, 'equipment_id');
    }

    public function locationHistories(): HasMany
    {
        return $this->hasMany(EquipmentLocationHistory::class, 'equipment_id')->orderByDesc('started_at');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(EquipmentStatusHistory::class, 'equipment_id')->orderByDesc('started_at');
    }

    public function optionValues(): HasMany
    {
        return $this->hasMany(EquipmentOptionValue::class, 'equipment_id');
    }

    public function complianceRecords(): HasMany
    {
        return $this->hasMany(EquipmentComplianceRecord::class, 'equipment_id')->orderByDesc('valid_until');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(ClientEquipmentTransfer::class, 'equipment_id')
            ->orderByDesc('transfer_date');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForClient($query, string $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeOverdue($query)
    {
        return $query->where('compliance_status', 'overdue');
    }
}
