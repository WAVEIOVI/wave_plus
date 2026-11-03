<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Traits\GeneratesReference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceDeliveryNote extends Model
{
    use GeneratesReference, HasFactory, SoftDeletes;
    use HasUlids;

    protected $table = 'service_delivery_notes';

    protected $fillable = [
        'sdn_number',
        'swo_id',
        'mission_id',
        'client_id',
        'service_site_id',
        'issued_date',
        'period_start',
        'period_end',
        'issued_by',
        'signed_at',
        'client_signer_name',
        'client_signature',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issued_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'signed_at' => 'datetime',
    ];

    protected function getReferenceColumn(): string
    {
        return 'sdn_number';
    }

    protected function getDocumentSequenceName(): string
    {
        return 'SDN-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    public function swo(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkOrder::class, 'swo_id');
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class, 'mission_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function serviceSite(): BelongsTo
    {
        return $this->belongsTo(ClientSite::class, 'service_site_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ServiceDeliveryNoteLine::class, 'sdn_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
