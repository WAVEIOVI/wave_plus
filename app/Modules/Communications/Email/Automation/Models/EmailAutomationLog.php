<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Models;

use App\Modules\Communications\Email\Models\EmailMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmailAutomationLog extends Model
{
    protected $table = 'email_automation_logs';

    protected $fillable = [
        'rule_id',
        'trigger',
        'record_type',
        'record_id',
        'execution_mode',
        'status',
        'idempotency_key',
        'email_message_id',
        'approval_request_id',
        'follow_up_task_id',
        'executed_at',
        'attempts',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
        'attempts' => 'integer',
        'metadata' => 'array',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EmailAutomationRule::class, 'rule_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }

    public function approvalRequest(): HasOne
    {
        return $this->hasOne(EmailApprovalRequest::class, 'automation_log_id');
    }

    public function followUpTask(): HasOne
    {
        return $this->hasOne(EmailFollowUpTask::class, 'automation_log_id');
    }

    public function scopeExecuted(Builder $query): Builder
    {
        return $query->where('status', 'executed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }
}
