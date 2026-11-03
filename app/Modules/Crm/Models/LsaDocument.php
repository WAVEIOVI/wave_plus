<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Modules\Crm\Enums\DocumentStatus;
use App\Modules\OperationalHub\Models\ServiceWorkOrder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $reference
 * @property string $sales_order_id
 * @property string|null $contract_id
 * @property DocumentStatus $status
 * @property int|null $sla_response_hours
 * @property int|null $sla_resolution_hours
 * @property \Illuminate\Support\Carbon|null $scheduled_start_date
 * @property \Illuminate\Support\Carbon|null $scheduled_end_date
 * @property array|null $assigned_team
 * @property array|null $equipment_scope
 * @property array|null $service_scope
 * @property \Illuminate\Support\Carbon|null $client_signed_at
 * @property string|null $client_signatory
 * @property string|null $client_signature_path
 * @property string|null $document_path
 * @property string|null $signed_document_path
 * @property string|null $completion_report_path
 * @property \Illuminate\Support\Carbon|null $client_acknowledgement_at
 * @property string|null $special_terms
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read SalesOrder $salesOrder
 * @property-read Contract|null $contract
 * @property-read \Illuminate\Database\Eloquent\Collection|ServiceWorkOrder[] $serviceWorkOrders
 */
class LsaDocument extends Model
{
    use \App\Traits\GeneratesReference, HasUlids, SoftDeletes;

    protected $table = 'lsa_documents';

    protected $fillable = [
        'reference',
        'sales_order_id',
        'contract_id',
        'status',
        'sla_response_hours',
        'sla_resolution_hours',
        'scheduled_start_date',
        'scheduled_end_date',
        'assigned_team',
        'equipment_scope',
        'service_scope',
        'client_signed_at',
        'client_signatory',
        'client_signature_path',
        'document_path',
        'signed_document_path',
        'completion_report_path',
        'client_acknowledgement_at',
        'special_terms',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'sla_response_hours' => 'integer',
            'sla_resolution_hours' => 'integer',
            'scheduled_start_date' => 'date',
            'scheduled_end_date' => 'date',
            'assigned_team' => 'array',
            'equipment_scope' => 'array',
            'service_scope' => 'array',
            'client_signed_at' => 'datetime',
            'client_acknowledgement_at' => 'datetime',
        ];
    }

    protected function getDocumentSequenceName(): string
    {
        return 'LSA-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    // ─── Relationships ───────────────────────────────────────────

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function serviceWorkOrders(): HasMany
    {
        return $this->hasMany(ServiceWorkOrder::class, 'lsa_id');
    }
}
