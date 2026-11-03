<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\Hr\Models\Employee;
use App\Modules\OperationalHub\Enums\FindingType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Finding extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;
    use HasUlids;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('public');

        $this->addMediaCollection('documents')
            ->useDisk('local');
    }

    protected $table = 'findings';

    protected $fillable = [
        'findable_type',
        'findable_id',
        'equipment_id',
        'site_id',
        'type',
        'category',
        'title',
        'description',
        'severity',
        'recommendation',
        'corrective_action',
        'requires_workshop_transfer',
        'status',
        'assigned_to',
        'resolved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => FindingType::class,
        'requires_workshop_transfer' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function findable(): MorphTo
    {
        return $this->morphTo();
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClientEquipment::class, 'equipment_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'site_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
