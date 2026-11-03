<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\Crm\Models\SalesOrder;
use App\Modules\OperationalHub\Enums\WorkshopReceptionStatus;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class WorkshopReception extends Model implements HasMedia
{
    use GeneratesReference, HasFactory, InteractsWithMedia, SoftDeletes;
    use HasUlids;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('public');

        $this->addMediaCollection('documents')
            ->useDisk('local');
    }

    protected $table = 'workshop_receptions';

    protected $fillable = [
        'reception_number',
        'workshop_unit_id',
        'equipment_id',
        'sales_order_id',
        'swo_id',
        'source_mission_id',
        'exit_pass_id',
        'client_id',
        'received_by',
        'reception_date',
        'condition',
        'visible_damages',
        'accessories_received',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => WorkshopReceptionStatus::class,
        'reception_date' => 'date',
    ];

    protected function getReferenceColumn(): string
    {
        return 'reception_number';
    }

    protected function getDocumentSequenceName(): string
    {
        return 'WR-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    public function workshopUnit(): BelongsTo
    {
        return $this->belongsTo(WorkshopUnit::class, 'workshop_unit_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClientEquipment::class, 'equipment_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function swo(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkOrder::class, 'swo_id');
    }

    public function sourceMission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'source_mission_id');
    }

    public function exitPass(): BelongsTo
    {
        return $this->belongsTo(WorkshopExitPass::class, 'exit_pass_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
