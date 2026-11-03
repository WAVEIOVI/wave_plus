<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Crm\Enums\PriceComputationMethod;
use App\Modules\Crm\Enums\PriceListType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property PriceListType $type
 * @property int|null $client_id
 * @property string|null $segment
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_to
 * @property string $currency
 * @property PriceComputationMethod $price_computation
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Client|null $client
 * @property-read \Illuminate\Database\Eloquent\Collection|PriceListLine[] $lines
 * @property-read \Illuminate\Database\Eloquent\Collection|Contract[] $contracts
 */
class PriceList extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'client_id',
        'segment',
        'valid_from',
        'valid_to',
        'currency',
        'price_computation',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PriceListType::class,
            'price_computation' => PriceComputationMethod::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PriceListLine::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function status(): string
    {
        if (! $this->is_active) {
            return 'draft';
        }

        if ($this->valid_to?->isBefore(today())) {
            return 'expired';
        }

        return 'active';
    }
}
