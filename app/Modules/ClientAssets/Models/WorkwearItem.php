<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\ClientAssets\Enums\WorkwearItemCondition;
use App\Modules\ClientAssets\Enums\WorkwearItemStatus;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class WorkwearItem extends Model implements HasMedia
{
    use GeneratesReference, HasUlids, InteractsWithMedia, SoftDeletes;

    protected $table = 'workwear_items';

    public const DOCUMENT_TYPE_PREFIX = 'WW';

    protected $fillable = [
        'item_code', 'uuid', 'sku', 'model_id', 'client_id', 'garment_type', 'size', 'color', 'supplier_id',
        'status', 'item_condition', 'is_stolen', 'stolen_data', 'retired_data', 'unit_cost',
        'sales_order_id', 'delivery_note_id', 'notes', 'option_values',
    ];

    protected function casts(): array
    {
        return [
            'is_stolen' => 'boolean',
            'stolen_data' => 'array',
            'retired_data' => 'array',
            'option_values' => 'array',
            'unit_cost' => 'decimal:3',
            'status' => WorkwearItemStatus::class,
            'item_condition' => WorkwearItemCondition::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->uuid)) {
                $m->uuid = (string) Str::uuid();
            }

            // Variant SKU defaults to <MODEL_REF>-<COLOR>-<SIZE> when not supplied.
            if (empty($m->sku) && $m->model_id) {
                $m->sku = self::defaultSku($m->model_id, $m->color, $m->size);
            }
        });
    }

    protected function getReferenceColumn(): string
    {
        return 'item_code';
    }

    protected function formatReference(string $t, int $n): string
    {
        return sprintf('WW-%s-%04d', now()->format('ymd'), $n);
    }

    // ─── Relationships ───────────────────────────────────────────

    public static function defaultSku(string $modelId, ?string $color, ?string $size): ?string
    {
        $ref = WorkwearModel::query()->whereKey($modelId)->value('model_ref');
        if (! is_string($ref) || $ref === '') {
            return null;
        }

        $parts = array_filter([
            $ref,
            $color ? Str::upper(Str::slug($color, '')) : null,
            $size ? Str::upper(Str::slug($size, '')) : null,
        ]);

        return Str::limit(implode('-', $parts), 80, '');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->performOnCollections('photos')
            ->nonQueued();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function workwearModel(): BelongsTo
    {
        return $this->belongsTo(WorkwearModel::class, 'model_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Crm\Models\SalesOrder::class, 'sales_order_id');
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Logistics\Models\DeliveryNote::class, 'delivery_note_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkwearAssignment::class, 'item_id')->orderByDesc('assigned_at');
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(WorkwearAssignment::class, 'item_id')->whereNull('returned_at')->latestOfMany('assigned_at');
    }

    public function itemOptionValues(): HasMany
    {
        return $this->hasMany(WorkwearItemOptionValue::class, 'item_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────

    /**
     * Items that are eligible for assignment.
     * Includes both 'in_stock' (brand new) and 'available' (returned & inspected).
     */
    public function scopeAvailable($q)
    {
        return $q->whereIn('status', WorkwearItemStatus::assignableStatuses());
    }

    public function scopeStolen($q)
    {
        return $q->where('is_stolen', true);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Whether this item can be assigned to an employee.
     */
    public function isAvailableForAssignment(): bool
    {
        $status = $this->status instanceof WorkwearItemStatus
            ? $this->status
            : WorkwearItemStatus::tryFrom($this->status);

        return $status !== null && in_array($status->value, WorkwearItemStatus::assignableStatuses(), true);
    }
}
