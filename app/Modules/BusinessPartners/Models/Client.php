<?php

namespace App\Modules\BusinessPartners\Models;

use App\Models\User;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\Crm\Models\Contract;
use App\Modules\Crm\Models\Quotation;
use App\Modules\Crm\Models\SalesOrder;
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
 * @property \Illuminate\Support\Carbon|null $partner_since
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Client extends Model implements HasMedia
{
    use \App\Traits\GeneratesReference, HasTranslations, InteractsWithMedia, SoftDeletes;
    use HasUlids;

    protected function getReferenceColumn(): string
    {
        return 'client_id';
    }

    protected function getDocumentSequenceName(): string
    {
        return 'CL';
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

    protected $table = 'clients';

    public array $translatable = ['legal_name', 'commercial_name', 'notes', 'hq_address', 'hq_city', 'hq_country'];

    protected $fillable = [
        'client_id',
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
        'client_category',
        'credit_limit',
        'payment_terms',
        'default_currency',
        'discount_rate',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'partner_since' => 'date',
            'satisfaction_score' => 'decimal:1',
            'credit_limit' => 'decimal:3',
            'discount_rate' => 'decimal:2',
        ];
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

    public function sites(): HasMany
    {
        return $this->hasMany(ClientSite::class, 'client_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to only active clients.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to search clients by name, tax ID, or client ID.
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
                ->orWhere('client_id', 'like', "%{$term}%");
        });
    }
}
