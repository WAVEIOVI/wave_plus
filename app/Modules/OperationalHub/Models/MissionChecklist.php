<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissionChecklist extends Model
{
    use HasUlids;

    protected $table = 'mission_checklists';

    protected $fillable = [
        'mission_id',
        'title',
        'notes',
        'created_by',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MissionChecklistItem::class, 'checklist_id')->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
