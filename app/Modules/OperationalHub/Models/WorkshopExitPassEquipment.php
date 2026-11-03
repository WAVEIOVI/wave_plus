<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Modules\ClientAssets\Models\ClientEquipment;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopExitPassEquipment extends Model
{
    use HasUlids;

    protected $table = 'workshop_exit_pass_equipment';

    protected $fillable = [
        'exit_pass_id',
        'equipment_id',
        'condition',
        'notes',
    ];

    public function exitPass(): BelongsTo
    {
        return $this->belongsTo(WorkshopExitPass::class, 'exit_pass_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClientEquipment::class, 'equipment_id');
    }
}
