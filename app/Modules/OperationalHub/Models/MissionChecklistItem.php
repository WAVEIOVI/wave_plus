<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionChecklistItem extends Model
{
    use HasUlids;

    protected $table = 'mission_checklist_items';

    protected $fillable = [
        'checklist_id',
        'title',
        'answer',
        'notes',
        'is_completed',
        'evidence_path',
        'sort_order',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(MissionChecklist::class, 'checklist_id');
    }
}
