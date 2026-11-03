<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    use HasUlids;

    protected $table = 'budget_lines';

    protected $fillable = [
        'budget_id',
        'category',
        'description',
        'budgeted_amount',
        'actual_amount',
        'variance',
    ];

    protected $casts = [
        'budgeted_amount' => 'decimal:3',
        'actual_amount' => 'decimal:3',
        'variance' => 'decimal:3',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }
}
