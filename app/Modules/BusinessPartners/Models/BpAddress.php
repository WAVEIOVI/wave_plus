<?php

namespace App\Modules\BusinessPartners\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property Model|Client|Supplier|ServiceProvider|Organisation $addressable
 */
class BpAddress extends Model
{
    use HasTranslations;
    use HasUlids;

    protected static function booted(): void
    {
        static::saved(function (self $address) {
            if ($address->is_primary) {
                static::where('addressable_id', $address->addressable_id)
                    ->where('addressable_type', $address->addressable_type)
                    ->where('id', '!=', $address->id)
                    ->update(['is_primary' => false]);
            }
            if ($address->is_billing_default) {
                static::where('addressable_id', $address->addressable_id)
                    ->where('addressable_type', $address->addressable_type)
                    ->where('id', '!=', $address->id)
                    ->update(['is_billing_default' => false]);
            }
            if ($address->is_shipping_default) {
                static::where('addressable_id', $address->addressable_id)
                    ->where('addressable_type', $address->addressable_type)
                    ->where('id', '!=', $address->id)
                    ->update(['is_shipping_default' => false]);
            }

            if ($address->addressable) {
                app(\App\Modules\BusinessPartners\Services\AddressManagementService::class)->syncOwnerHeadquarters($address->addressable);
            }
        });

        static::deleted(function (self $address) {
            if ($address->addressable) {
                app(\App\Modules\BusinessPartners\Services\AddressManagementService::class)->syncOwnerHeadquarters($address->addressable);
            }
        });
    }

    protected $table = 'bp_addresses';

    public array $translatable = ['label', 'notes', 'address_street', 'address_street2', 'city', 'state', 'country'];

    protected $fillable = [
        'addressable_type',
        'addressable_id',
        'type',
        'is_primary',
        'is_billing_default',
        'is_shipping_default',
        'label',
        'address_street',
        'address_street2',
        'city',
        'state',
        'postcode',
        'country',
        'phone',
        'email',
        'responsible',
        'nearest_fire_station',
        'work_team',
        'latitude',
        'longitude',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_billing_default' => 'boolean',
            'is_shipping_default' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(BpContact::class, 'bp_contact_address', 'bp_address_id', 'bp_contact_id')
            ->using(BpContactAddress::class)
            ->withPivot('is_primary_location')
            ->withTimestamps();
    }

    public function clientSites(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\ClientAssets\Models\ClientSite::class, 'bp_address_id');
    }
}
