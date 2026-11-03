<?php

namespace App\Modules\BusinessPartners\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * @property \Illuminate\Support\Carbon|null $partner_since
 * @property \Illuminate\Support\Carbon|null $registration_date
 * @property \Illuminate\Support\Carbon|null $partnership_start_date
 * @property \Illuminate\Support\Carbon|null $partnership_end_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Organisation extends Model implements HasMedia
{
    use \App\Traits\GeneratesReference, HasTranslations, SoftDeletes;
    use HasUlids, InteractsWithMedia;

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

    protected function getReferenceColumn(): string
    {
        return 'organisation_id';
    }

    protected function getDocumentSequenceName(): string
    {
        return 'OR';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return $sequenceType.str_pad((string) $nextValue, 5, '0', STR_PAD_LEFT);
    }

    protected $table = 'organisations';

    public array $translatable = ['legal_name', 'commercial_name', 'notes', 'hq_address', 'hq_city', 'hq_country'];

    protected $fillable = [
        'organisation_id',
        'legal_name',
        'commercial_name',
        'tax_id',
        'rne_id',
        'primary_phone',
        'primary_email',
        'hq_address',
        'hq_city',
        'hq_postcode',
        'hq_country',
        'account_manager_id',
        'partner_since',
        'organisation_type',
        'registration_number',
        'registration_date',
        'accreditation_body',
        'regulatory_authority',
        'parent_organisation',
        'member_count',
        'scope',
        'partnership_start_date',
        'partnership_end_date',
        'status',
        'notes',
        'logo_path',
        'logo_source',
        'logo_domain',
        'logo_external_url',
    ];

    protected function casts(): array
    {
        return [
            'partner_since' => 'date',
            'registration_date' => 'date',
            'partnership_start_date' => 'date',
            'partnership_end_date' => 'date',
            'member_count' => 'integer',
        ];
    }

    /**
     * Resolve the logo consistently with Client/Supplier/ServiceProvider:
     * uploaded media first, then a provider-hosted hotlink, then nothing.
     */
    public function getLogoUrl(): ?string
    {
        $mediaUrl = $this->getFirstMediaUrl('logo');

        if ($mediaUrl) {
            $appUrl = rtrim(config('app.url'), '/');
            $requestBase = rtrim(url('/'), '/');

            return $appUrl !== $requestBase ? str_replace($appUrl, $requestBase, $mediaUrl) : $mediaUrl;
        }

        return $this->logo_external_url ?: null;
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

    // ─── Scopes ──────────────────────────────────────────────────

    /**
     * Scope to only active organisations.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to search organisations by name, tax ID, or organisation ID.
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
                ->orWhere('organisation_id', 'like', "%{$term}%");
        });
    }
}
