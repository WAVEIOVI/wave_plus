<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Modules\Hr\Models\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwoEmployee extends Model
{
    use HasUlids;

    protected $table = 'swo_employees';

    protected $fillable = [
        'swo_id',
        'employee_id',
        'is_lead',
    ];

    protected $casts = [
        'is_lead' => 'boolean',
    ];

    public function serviceWorkOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkOrder::class, 'swo_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
