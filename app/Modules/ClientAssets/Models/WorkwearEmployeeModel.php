<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model entitlement: "this employee is assigned this workwear model" (planned
 * requirement — quantity and size). It is NOT an issuance: physical garments
 * handed over are recorded as WorkwearAssignment rows against WorkwearItem.
 *
 * Ending an entitlement sets ended_at (history is preserved).
 *
 * @property string $id
 * @property string $employee_id
 * @property string $model_id
 * @property int $quantity
 * @property string|null $size
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $assigned_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property string|null $assigned_by
 */
class WorkwearEmployeeModel extends Model
{
    use HasUlids;

    protected $table = 'workwear_employee_models';

    protected $fillable = [
        'employee_id', 'model_id', 'quantity', 'size', 'notes',
        'assigned_at', 'ended_at', 'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(WorkwearEmployee::class, 'employee_id');
    }

    public function workwearModel(): BelongsTo
    {
        return $this->belongsTo(WorkwearModel::class, 'model_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }
}
