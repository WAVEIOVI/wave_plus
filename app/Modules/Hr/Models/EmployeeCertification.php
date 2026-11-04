<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class EmployeeCertification extends Model implements HasMedia
{
    use HasUlids, InteractsWithMedia;

    protected $table = 'hr_employee_certifications';

    protected $fillable = [
        'employee_id',
        'certification_id',
        'certificate_number',
        'issuing_organization',
        'issue_date',
        'expiry_date',
        'status',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('certificate_files')
            ->useDisk('local');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class, 'certification_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getIsValidAttribute(): bool
    {
        if ($this->status !== 'valid') {
            return false;
        }

        if ($this->expiry_date && $this->expiry_date->isPast()) {
            return false;
        }

        return true;
    }

    public function getComputedStatusAttribute(): string
    {
        if ($this->status === 'pending_verification') {
            return 'pending_verification';
        }

        if ($this->expiry_date) {
            if ($this->expiry_date->isPast()) {
                return 'expired';
            }
            if (now()->diffInDays($this->expiry_date, false) <= 60) {
                return 'expiring';
            }
        }

        return $this->status ?? 'valid';
    }

    public function scopeExpiring($query, int $days = 60)
    {
        return $query->where('status', '!=', 'pending_verification')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'valid')
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now()->toDateString());
            });
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'expired')
                ->orWhere(function ($sub) {
                    $sub->whereNotNull('expiry_date')
                        ->where('expiry_date', '<', now()->toDateString());
                });
        });
    }
}
