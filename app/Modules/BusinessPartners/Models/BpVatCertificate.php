<?php

namespace App\Modules\BusinessPartners\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Translatable\HasTranslations;

/**
 * @property \Illuminate\Support\Carbon|null $issue_date
 * @property \Illuminate\Support\Carbon|null $expiration_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property Model|Client|Supplier|ServiceProvider|Organisation $certifiable
 */
class BpVatCertificate extends Model
{
    use HasTranslations;
    use HasUlids;

    protected $table = 'bp_vat_certificates';

    public array $translatable = ['notes'];

    protected $fillable = [
        'certifiable_type',
        'certifiable_id',
        'certificate_number',
        'issuing_authority',
        'issue_date',
        'expiration_date',
        'status',
        'document_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiration_date' => 'date',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────

    public function certifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
