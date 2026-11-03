<?php

namespace App\Modules\BusinessPartners\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property Model|Client|Supplier|ServiceProvider|Organisation $bankable
 */
class BpBankAccount extends Model
{
    use HasTranslations;
    use HasUlids;

    protected static function booted(): void
    {
        static::saved(function (self $account) {
            if ($account->is_primary) {
                static::where('bankable_id', $account->bankable_id)
                    ->where('bankable_type', $account->bankable_type)
                    ->where('id', '!=', $account->id)
                    ->update(['is_primary' => false]);
            }
        });
    }

    protected $table = 'bp_bank_accounts';

    public array $translatable = ['notes'];

    protected $fillable = [
        'bankable_type',
        'bankable_id',
        'bank_name',
        'bank_code',
        'branch',
        'account_holder',
        'rib',
        'iban',
        'swift_bic',
        'account_number',
        'currency',
        'is_primary',
        'is_verified',
        'usage',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function bankable(): MorphTo
    {
        return $this->morphTo();
    }
}
