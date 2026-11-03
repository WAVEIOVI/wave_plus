<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkwearAssignment extends Model
{
    use HasUlids;

    protected $table = 'workwear_assignments';

    protected $fillable = [
        'item_id', 'employee_id', 'assigned_by',
        'assigned_at', 'returned_at',
        'condition_at_assign', 'condition_at_return',
        'return_action', 'returned_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(WorkwearItem::class, 'item_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(WorkwearEmployee::class, 'employee_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function returnedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}
