<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Models;

use App\Modules\Communications\Email\Models\EmailMessage;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BpBulkEmailBatchItem extends Model
{
    use HasUlids;

    protected $table = 'bp_bulk_email_batch_items';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const SKIP_REASON_NO_EMAIL = 'no_valid_email';
    public const SKIP_REASON_UNAUTHORIZED = 'unauthorized';
    public const SKIP_REASON_DUPLICATE = 'duplicate';

    protected $fillable = [
        'batch_id',
        'partner_type',
        'partner_id',
        'partner_name',
        'partner_code',
        'resolved_email',
        'resolved_name',
        'contact_id',
        'email_source',
        'email_message_id',
        'status',
        'skip_reason',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BpBulkEmailBatch::class, 'batch_id');
    }

    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
