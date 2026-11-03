<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Compliance and administrative documents attached to a fleet vehicle
 * (insurance, registration, technical certificates, etc.).
 */
class VehicleDocument extends Model implements HasMedia
{
    use HasUlids;
    use InteractsWithMedia;
    use SoftDeletes;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('document')
            ->useDisk('local')
            ->singleFile();
    }

    public function getMediaDocument(): ?Media
    {
        return $this->getFirstMedia('document');
    }

    public function getDownloadUrl(): string
    {
        return url("/api/hr/fleet/documents/{$this->id}/download");
    }


    protected $fillable = [
        'vehicle_id',
        'document_type',
        'title',
        'document_number',
        'issuer',
        'issue_date',
        'expiry_date',
        'reminder_date',
        'status',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'reminder_date' => 'date',
            'metadata' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expiry_date', '<=', now()->addDays($days))
            ->where('status', 'active');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeByVehicle($query, string $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    // ─── Accessors ───────────────────────────────────────────────

    public function getIsExpiredAttribute(): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return \Illuminate\Support\Carbon::parse($this->expiry_date)->isPast();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        $expiry = \Illuminate\Support\Carbon::parse($this->expiry_date);

        return $expiry->isFuture() && $expiry->lte(now()->addDays(30));
    }
}
