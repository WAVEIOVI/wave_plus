<?php

namespace App\Models;

use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegistrationRequest extends Model
{
    use GeneratesReference, HasFactory, HasUlids, SoftDeletes;

    protected $table = 'registration_requests';

    protected $fillable = [
        'reference',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company_name',
        'tax_id',
        'industry',
        'address',
        'city',
        'country',
        'website',
        'requested_user_type',
        'requested_scope_details',
        'notes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
        'rejection_reason',
        'created_user_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'requested_scope_details' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    protected function getReferenceColumn(): string
    {
        return 'reference';
    }

    protected function getDocumentSequenceName(): string
    {
        return 'REG';
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return $sequenceType . '-' . str_pad((string) $nextValue, 5, '0', STR_PAD_LEFT);
    }

    // ─── Accessors ──────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // ─── Relationships ──────────────────────────────────────────────────

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }
}
