<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class EmailMailbox extends Model
{
    protected $fillable = [
        'name',
        'email',
        'department',
        'sender_profile_id',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'imap_folder',
        'is_active',
        'last_synced_at',
        'last_synced_uid',
        'last_synced_uidvalidity',
    ];

    /**
     * Passwords must NEVER be leaked in API responses or logs.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'imap_password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'imap_port' => 'integer',
        'last_synced_uid' => 'integer',
        'last_synced_uidvalidity' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    // ── Encrypted password accessors ────────────────────────────────────────

    public function setImapPasswordAttribute(?string $value): void
    {
        $this->attributes['imap_password'] = ($value && $value !== '')
            ? Crypt::encryptString($value)
            : null;
    }

    public function getDecryptedPasswordAttribute(): ?string
    {
        $encrypted = $this->attributes['imap_password'] ?? null;
        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function senderProfile(): BelongsTo
    {
        return $this->belongsTo(EmailSenderProfile::class, 'sender_profile_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(EmailConversation::class, 'mailbox_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'mailbox_id');
    }

    // ── Access Authorization Helper ─────────────────────────────────────────

    public function isAuthorizedForUser(User $user): bool
    {
        return app(\App\Modules\Communications\Email\Services\EmailAccessControlService::class)->canAccessMailbox($user, $this);
    }
}
