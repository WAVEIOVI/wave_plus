<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailSenderProfile extends Model
{
    protected $fillable = [
        'name',
        'email',
        'reply_to',
        'department',
        'is_system',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'status',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'sender_profile_id');
    }

    /**
     * Virtual status string ('active' | 'inactive') for UI consistency.
     */
    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'active' : 'inactive';
    }

    /**
     * Scope to active profiles.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to default profile.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to interactive profiles (usable by real users, excluding system profile).
     */
    public function scopeInteractive(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope to automated system profiles.
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /**
     * Returns the default active interactive sender profile, falling back to any active interactive profile.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)
            ->where('is_active', true)
            ->where('is_system', false)
            ->first()
            ?? static::where('is_active', true)
                ->where('is_system', false)
                ->first();
    }

    /**
     * Returns the automated system sender profile (system@waveplus.xyz).
     */
    public static function getSystemProfile(): ?self
    {
        return static::where('is_system', true)
            ->where('is_active', true)
            ->first()
            ?? static::where('email', 'system@waveplus.xyz')->first();
    }
}
