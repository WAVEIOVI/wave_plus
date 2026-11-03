<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailMessage extends Model
{
    use HasUlids;

    // ── Status constants ───────────────────────────────────────────────
    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_FAILED = 'failed';

    // ── Direction constants ────────────────────────────────────────────
    public const DIRECTION_OUTGOING = 'outgoing';

    public const DIRECTION_INCOMING = 'incoming';

    // ── Folder constants ───────────────────────────────────────────────
    public const FOLDER_INBOX = 'inbox';

    public const FOLDER_SENT = 'sent';

    public const FOLDER_DRAFT = 'draft';

    public const FOLDER_SCHEDULED = 'scheduled';

    public const FOLDER_ARCHIVED = 'archived';

    public const FOLDER_TRASH = 'trash';

    public const FOLDER_SPAM = 'spam';

    protected $fillable = [
        'conversation_id',
        'mailbox_id',
        'direction',
        'folder',
        'message_id',
        'in_reply_to',
        'references',
        'scheduled_at',
        'is_read',
        'is_starred',
        'is_deleted',
        'uid',
        'sender_profile_id',
        'sender_email',
        'sender_name',
        'template_id',
        'subject',
        'body_html',
        'body_text',
        'status',
        'idempotency_key',
        'sent_at',
        'failed_at',
        'last_error',
        'attempts',
        'max_attempts',
        'created_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'is_read' => 'boolean',
        'is_starred' => 'boolean',
        'is_deleted' => 'boolean',
        'uid' => 'integer',
        'status' => 'string',
        'direction' => 'string',
        'folder' => 'string',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(EmailConversation::class, 'conversation_id');
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(EmailMailbox::class, 'mailbox_id');
    }

    public function senderProfile(): BelongsTo
    {
        return $this->belongsTo(EmailSenderProfile::class, 'sender_profile_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailRecipient::class, 'email_message_id');
    }

    public function fromParticipant(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmailRecipient::class, 'email_message_id')->where('type', EmailRecipient::TYPE_FROM);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class, 'email_message_id');
    }

    public function associations(): HasMany
    {
        return $this->hasMany(EmailAssociation::class, 'email_message_id');
    }

    public function recordLinks(): HasMany
    {
        return $this->hasMany(EmailRecordLink::class, 'email_message_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeQueued($query)
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeFolder($query, string $folder)
    {
        return $query->where('folder', $folder)->where('is_deleted', false);
    }

    public function scopeStarred($query)
    {
        return $query->where('is_starred', true)->where('is_deleted', false);
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', self::DIRECTION_INCOMING);
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', self::DIRECTION_OUTGOING);
    }

    // ── Helper methods ──────────────────────────────────────────────────

    public function isSendable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_FAILED], true);
    }

    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED && $this->attempts < $this->max_attempts;
    }
}
