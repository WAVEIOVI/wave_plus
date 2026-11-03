<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailConversation extends Model
{
    use HasUlids;

    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_PENDING,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    protected $fillable = [
        'mailbox_id',
        'department',
        'subject',
        'status',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'first_message_at',
        'last_message_at',
        'is_starred',
        'is_archived',
        'participant_names',
    ];

    protected $casts = [
        'status' => 'string',
        'is_starred' => 'boolean',
        'is_archived' => 'boolean',
        'assigned_at' => 'datetime',
        'first_message_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(EmailMailbox::class, 'mailbox_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(EmailConversationNote::class, 'conversation_id')->latest();
    }

    public function recordLinks(): HasMany
    {
        return $this->hasMany(EmailRecordLink::class, 'conversation_id');
    }

    public function confirmedRecordLinks(): HasMany
    {
        return $this->hasMany(EmailRecordLink::class, 'conversation_id')->where('is_confirmed', true);
    }

    public function suggestedRecordLinks(): HasMany
    {
        return $this->hasMany(EmailRecordLink::class, 'conversation_id')->where('is_confirmed', false);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeResolved($query)
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    public function scopeAssignedToUser($query, string $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to')->where('status', self::STATUS_OPEN);
    }

    public function scopeUnanswered($query)
    {
        return $query->where('status', self::STATUS_OPEN)
            ->whereHas('messages', function ($q) {
                $q->where('direction', 'incoming');
            })
            ->whereDoesntHave('messages', function ($q) {
                $q->where('direction', 'outgoing')->where('status', 'sent');
            });
    }

    public function scopePendingReplies($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRecentActivity($query, int $days = 7)
    {
        return $query->where('last_message_at', '>=', now()->subDays($days))
            ->orderBy('last_message_at', 'desc');
    }

    public function scopeDepartment($query, string $dept)
    {
        return $query->where('department', $dept);
    }

    public function scopeArchived($query, bool $archived = true)
    {
        return $query->where('is_archived', $archived);
    }
}
