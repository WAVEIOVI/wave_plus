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
 * @property Model|Client|Supplier|ServiceProvider|Organisation $contactable
 */
class BpContact extends Model
{
    use HasTranslations;
    use HasUlids;

    protected static function booted(): void
    {
        static::saved(function (self $contact) {
            if ($contact->is_primary) {
                static::where('contactable_id', $contact->contactable_id)
                    ->where('contactable_type', $contact->contactable_type)
                    ->where('id', '!=', $contact->id)
                    ->update(['is_primary' => false]);
            }
        });
    }

    protected $table = 'bp_contacts';

    public array $translatable = ['first_name', 'last_name', 'notes'];

    protected $fillable = [
        'contactable_type',
        'contactable_id',
        'first_name',
        'last_name',
        'gender',
        'avatar_path',
        'job_title',
        'department',
        'phone',
        'mobile',
        'whatsapp_number',
        'email',
        'linkedin_url',
        'is_primary',
        'is_decision_maker',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_decision_maker' => 'boolean',
        ];
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // ─── Relationships ───────────────────────────────────────────

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function addresses(): BelongsToMany
    {
        return $this->belongsToMany(BpAddress::class, 'bp_contact_address', 'bp_contact_id', 'bp_address_id')
            ->using(BpContactAddress::class)
            ->withPivot('is_primary_location')
            ->withTimestamps();
    }

    public function purchaseOrders()
    {
        return $this->hasMany(\App\Modules\Procurement\Models\PurchaseOrder::class, 'supplier_contact_id');
    }
}
