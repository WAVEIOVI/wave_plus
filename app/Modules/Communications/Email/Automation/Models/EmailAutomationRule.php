<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Models;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use App\Modules\Communications\Email\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailAutomationRule extends Model
{
    protected $table = 'email_automation_rules';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'trigger',
        'is_active',
        'execution_mode',
        'template_slug',
        'sender_profile_id',
        'recipient_type',
        'fixed_recipient_email',
        'conditions',
        'actions',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'conditions' => 'array',
        'actions' => 'array',
    ];

    public function senderProfile(): BelongsTo
    {
        return $this->belongsTo(EmailSenderProfile::class, 'sender_profile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_slug', 'slug');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(EmailAutomationLog::class, 'rule_id')->latest();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTrigger(Builder $query, string $trigger): Builder
    {
        return $query->where('trigger', $trigger);
    }
}
