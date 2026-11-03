<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Models;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Services\EmailSendingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailApprovalRequest extends Model
{
    protected $table = 'email_approval_requests';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'automation_log_id',
        'email_message_id',
        'record_type',
        'record_id',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function automationLog(): BelongsTo
    {
        return $this->belongsTo(EmailAutomationLog::class, 'automation_log_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Approve the email and queue it for sending.
     */
    public function approve(User $approver, ?string $notes = null): self
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);

        if ($this->automationLog) {
            $this->automationLog->update([
                'status' => 'approved',
                'executed_at' => now(),
            ]);
        }

        // Send/Queue the email message
        if ($this->message && $this->message->status === EmailMessage::STATUS_DRAFT) {
            app(EmailSendingService::class)->queue($this->message);
        }

        return $this;
    }

    /**
     * Reject the approval request.
     */
    public function reject(User $approver, string $reason): self
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        if ($this->automationLog) {
            $this->automationLog->update([
                'status' => 'rejected',
                'error_message' => "Rejected by {$approver->name}: {$reason}",
            ]);
        }

        return $this;
    }
}
