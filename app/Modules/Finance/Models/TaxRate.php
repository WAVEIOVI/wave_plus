<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRate extends Model
{
    use HasUlids;

    protected $fillable = [
        'code',
        'name',
        'type',
        'rate',
        'is_recoverable',
        'is_active',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rate'           => 'decimal:4',
            'is_recoverable' => 'boolean',
            'is_active'      => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ─────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ─── Helpers ─────────────────────────────────────────

    public function calculateTax(float $amountHt): float
    {
        return round($amountHt * ($this->rate / 100), 3);
    }
}
