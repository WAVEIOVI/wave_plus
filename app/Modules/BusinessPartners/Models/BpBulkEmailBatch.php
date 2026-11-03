<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Models;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use App\Modules\Communications\Email\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BpBulkEmailBatch extends Model
{
    use HasUlids;

    protected $table = 'bp_bulk_email_batches';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL_FAILURE = 'partial_failure';
    public const STATUS_FAILED = 'failed';

    public const RULE_PRIMARY_EMAIL_FIRST = 'primary_email_first';
    public const RULE_PRIMARY_CONTACT_FIRST = 'primary_contact_first';
    public const RULE_PARTNER_EMAIL_ONLY = 'partner_email_only';
    public const RULE_CONTACTS_ONLY = 'contacts_only';

    protected $fillable = [
        'partner_type',
        'sender_profile_id',
        'template_id',
        'language',
        'recipient_rule',
        'include_pdf',
        'total_count',
        'sent_count',
        'failed_count',
        'skipped_count',
        'status',
        'idempotency_key',
        'created_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'include_pdf' => 'boolean',
        'total_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'skipped_count' => 'integer',
        'metadata' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BpBulkEmailBatchItem::class, 'batch_id');
    }

    public function senderProfile(): BelongsTo
    {
        return $this->belongsTo(EmailSenderProfile::class, 'sender_profile_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Recalculate counts and update batch status.
     */
    public function updateProgress(): void
    {
        $counts = $this->items()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $sent = $counts[BpBulkEmailBatchItem::STATUS_SENT] ?? 0;
        $failed = $counts[BpBulkEmailBatchItem::STATUS_FAILED] ?? 0;
        $skipped = $counts[BpBulkEmailBatchItem::STATUS_SKIPPED] ?? 0;
        $pending = $counts[BpBulkEmailBatchItem::STATUS_PENDING] ?? 0;
        $processing = $counts[BpBulkEmailBatchItem::STATUS_PROCESSING] ?? 0;

        $this->sent_count = $sent;
        $this->failed_count = $failed;
        $this->skipped_count = $skipped;

        $terminalCount = $sent + $failed + $skipped;

        if ($pending === 0 && $processing === 0 && $terminalCount >= $this->total_count) {
            if ($failed > 0 && $sent > 0) {
                $this->status = self::STATUS_PARTIAL_FAILURE;
            } elseif ($failed > 0 && $sent === 0) {
                $this->status = self::STATUS_FAILED;
            } else {
                $this->status = self::STATUS_COMPLETED;
            }
        } elseif ($processing > 0 || $sent > 0) {
            $this->status = self::STATUS_PROCESSING;
        }

        $this->save();
    }
}
