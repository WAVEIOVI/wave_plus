<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialPeriod extends Model
{
    use HasUlids;

    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'name',
        'year',
        'month',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'closed_at'  => 'datetime',
            'year'       => 'integer',
            'month'      => 'integer',
        ];
    }

    // ─── Relationships ──────────────────────────────────

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'financial_period_id');
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class, 'financial_period_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'financial_period_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'financial_period_id');
    }

    // ─── Scopes ─────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    // ─── Accessors ────────────────────────────────────────

    public function getIsOpenAttribute(): bool
    {
        return $this->status === 'open';
    }

    public function getIsClosedAttribute(): bool
    {
        return in_array($this->status, ['closed', 'locked'], true);
    }

    // ─── Helpers ─────────────────────────────────────────

    public static function findOrCreateForDate(\DateTimeInterface $date): self
    {
        $year  = (int) $date->format('Y');
        $month = (int) $date->format('n');

        return static::firstOrCreate(
            ['year' => $year, 'month' => $month],
            [
                'name'       => $date->format('F Y'),
                'start_date' => $date->format('Y-m-01'),
                'end_date'   => $date->format('Y-m-t'),
                'status'     => 'open',
            ]
        );
    }
}
