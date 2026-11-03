<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwoOperationalCost extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'swo_operational_costs';

    protected $fillable = [
        'swo_id',
        'cost_type',
        'description',
        'planned_amount',
        'actual_amount',
        'currency',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'planned_amount' => 'decimal:3',
        'actual_amount' => 'decimal:3',
    ];

    public function serviceWorkOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkOrder::class, 'swo_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
