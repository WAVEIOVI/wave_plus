<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_DOCUMENT = 'document';

    protected $fillable = [
        'email_message_id',
        'filename',
        'mime_type',
        'source',
        'source_type',
        'source_id',
        'size_bytes',
        'storage_disk',
        'storage_path',
        'content_b64',
    ];

    /**
     * Private storage details and raw content must never reach API responses.
     */
    protected $hidden = [
        'storage_disk',
        'storage_path',
        'content_b64',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
