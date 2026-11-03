<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeClockEvent extends Model
{
    use HasUlids;

    protected $table = 'hr_time_clock_events';

    protected $fillable = [
        'employee_id',
        'event_type',
        'event_time',
        'source',
        'ip_address',
        'latitude',
        'longitude',
        'device_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
