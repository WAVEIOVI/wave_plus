<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailRecipient extends Model
{
    public $timestamps = false;

    // ── Type constants ─────────────────────────────────────────────────
    public const TYPE_FROM = 'from';

    public const TYPE_TO = 'to';

    public const TYPE_CC = 'cc';

    public const TYPE_BCC = 'bcc';

    protected $fillable = [
        'email_message_id',
        'type',
        'name',
        'email',
        'recipient_type',
        'recipient_id',
        'contact_id',
        'email_source',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
