<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property string $supplier_id
 * @property string $legal_name
 * @property array $commercial_name
 * @property string|null $tax_id
 * @property string|null $rne_id
 * @property string|null $legal_status
 * @property string|null $company_size
 * @property string|null $industry
 * @property int|null $founding_year
 * @property string|null $website
 * @property string|null $logo_path
 * @property string|null $primary_phone
 * @property string|null $primary_email
 * @property array|null $hq_address
 * @property array|null $hq_city
 * @property string|null $hq_postcode
 * @property string|null $hq_country
 * @property int|null $account_manager_id
 * @property \Illuminate\Support\Carbon|null $partner_since
 * @property float|null $satisfaction_score
 * @property string|null $supplier_category
 * @property int|null $lead_time_days
 * @property string|null $payment_terms
 * @property float|null $minimum_order_value
 * @property string $status
 * @property array|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string $name
 * @property-read User|null $accountManager
 * @property-read \Illuminate\Database\Eloquent\Collection|BpAddress[] $addresses
 * @property-read \Illuminate\Database\Eloquent\Collection|BpContact[] $contacts
 * @property-read \Illuminate\Database\Eloquent\Collection|BpBankAccount[] $bankAccounts
 */
class Supplier extends Model implements HasMedia
{
    use \App\Traits\GeneratesReference, HasTranslations, InteractsWithMedia, SoftDeletes;
    use HasUlids;

    protected $table = 'suppliers';

    public array $translatable = ['legal_name', 'commercial_name', 'notes', 'hq_address', 'hq_city', 'hq_country'];

    protected $fillable = [
        'supplier_id',
        'legal_name',
        'commercial_name',
        'tax_id',
        'rne_id',
        'legal_status',
        'company_size',
        'industry',
        'founding_year',
        'website',
        'logo_path',
        'logo_source',
        'logo_domain',
        'logo_external_url',
        'primary_phone',
        'primary_email',
        'hq_address',
        'hq_city',
        'hq_postcode',
        'hq_country',
        'account_manager_id',
        'partner_since',
        'satisfaction_score',
        'supplier_category',
        'lead_time_days',
        'payment_terms',
        'minimum_order_value',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'partner_since' => 'date',
            'satisfaction_score' => 'decimal:1',
            'lead_time_days' => 'integer',
            'minimum_order_value' => 'decimal:3',
        ];
    }

    protected function getReferenceColumn(): string
    {
        return 'supplier_id';
    }

    protected function getDocumentSequenceName(): string
    {
        return 'SU';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return $sequenceType.str_pad((string) $nextValue, 5, '0', STR_PAD_LEFT);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->useDisk('public')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->nonQueued();

        $this->addMediaConversion('preview')
            ->width(400)
            ->height(400)
            ->nonQueued();
    }

    /**
     * Virtual name attribute for easier access.
     */
    public function getNameAttribute(): string
    {
        return $this->commercial_name ?: $this->legal_name;
    }

    // ─── Relationships ───────────────────────────────────────────

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(BpAddress::class, 'addressable');
    }

    public function mainAddress(): MorphOne
    {
        return $this->morphOne(BpAddress::class, 'addressable')
            ->ofMany(
                ['is_primary' => 'max', 'id' => 'max'],
                fn ($query) => $query->where('is_primary', true)->orWhere('type', 'headquarters')
            );
    }

    public function primaryAddress(): MorphOne
    {
        return $this->morphOne(BpAddress::class, 'addressable')
            ->where('is_primary', true);
    }

    public function contacts(): MorphMany
    {
        return $this->morphMany(BpContact::class, 'contactable');
    }

    public function bankAccounts(): MorphMany
    {
        return $this->morphMany(BpBankAccount::class, 'bankable');
    }

    public function vatCertificates(): MorphMany
    {
        return $this->morphMany(BpVatCertificate::class, 'certifiable');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(BpDocument::class, 'documentable');
    }

    // ─── Procurement Relations ───────────────────────────────────

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(\App\Modules\Procurement\Models\PurchaseOrder::class);
    }

    public function goodsReceiptNotes(): HasMany
    {
        return $this->hasMany(\App\Modules\Procurement\Models\GoodsReceiptNote::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to only active suppliers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to search suppliers by name, tax ID, or supplier ID.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('legal_name', 'like', "%{$term}%")
                ->orWhere('commercial_name', 'like', "%{$term}%")
                ->orWhere('tax_id', 'like', "%{$term}%")
                ->orWhere('supplier_id', 'like', "%{$term}%");
        });
    }
}
