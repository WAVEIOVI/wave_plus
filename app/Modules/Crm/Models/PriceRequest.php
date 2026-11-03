<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Crm\Enums\RfqSource;
use App\Modules\Crm\Enums\RfqStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $reference
 * @property string $client_id
 * @property RfqSource $source
 * @property \Illuminate\Support\Carbon $received_at
 * @property \Illuminate\Support\Carbon|null $response_deadline
 * @property string|null $client_reference
 * @property string|null $site_id
 * @property int|null $assigned_to
 * @property RfqStatus $status
 * @property string|null $client_note
 * @property string|null $internal_note
 * @property string|null $converted_to_quotation_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Client $client
 * @property-read User|null $assignedToUser
 * @property-read Quotation|null $convertedToQuotation
 * @property-read \Illuminate\Database\Eloquent\Collection|PriceRequestLine[] $lines
 */
class PriceRequest extends Model
{
    use \App\Traits\GeneratesReference, HasUlids, SoftDeletes;

    protected $fillable = [
        'reference',
        'client_id',
        'source',
        'received_at',
        'response_deadline',
        'client_reference',
        'site_id',
        'assigned_to',
        'status',
        'client_note',
        'internal_note',
        'converted_to_quotation_id',
    ];

    protected function casts(): array
    {
        return [
            'source' => RfqSource::class,
            'status' => RfqStatus::class,
            'received_at' => 'datetime',
            'response_deadline' => 'date',
        ];
    }

    protected function getDocumentSequenceName(): string
    {
        return 'PR-'.date('Y');
    }

    protected function formatReference(string $sequenceType, int $nextValue): string
    {
        return sprintf('%s-%04d', $sequenceType, $nextValue);
    }

    // ─── Relationships ───────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedToQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'converted_to_quotation_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PriceRequestLine::class);
    }
}
