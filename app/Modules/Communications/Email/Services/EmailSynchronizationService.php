<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Modules\Communications\Email\Contracts\MailboxReaderInterface;
use App\Modules\Communications\Email\Models\EmailAttachment;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Models\EmailMailbox;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailRecipient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmailSynchronizationService
{
    public function __construct(
        private readonly MailboxReaderInterface $reader,
        private readonly ?EmailSanitizationService $sanitizer = null,
    ) {}

    /**
     * Synchronize a specific company mailbox.
     *
     * @return array{ synced: int, skipped: int, errors: array<string> }
     */
    public function syncMailbox(EmailMailbox $mailbox, int $limit = 50): array
    {
        if (! $mailbox->is_active) {
            return ['synced' => 0, 'skipped' => 0, 'errors' => ['Mailbox is inactive']];
        }

        $synced = 0;
        $skipped = 0;
        $errors = [];
        $highestUid = (int) ($mailbox->last_synced_uid ?? 0);

        try {
            // ── 0. Check UIDVALIDITY (RFC 3501) ──────────────────────────
            $folderUidValidity = $this->reader->getFolderUidValidity($mailbox);
            if ($folderUidValidity !== null) {
                if ($mailbox->last_synced_uidvalidity !== null && $folderUidValidity !== (int) $mailbox->last_synced_uidvalidity) {
                    Log::warning("Mailbox [{$mailbox->id}] UIDVALIDITY changed from {$mailbox->last_synced_uidvalidity} to {$folderUidValidity}. Resetting cursor to 0.");
                    $mailbox->update([
                        'last_synced_uidvalidity' => $folderUidValidity,
                        'last_synced_uid' => 0,
                    ]);
                    $highestUid = 0;
                } elseif ($mailbox->last_synced_uidvalidity === null) {
                    $mailbox->update(['last_synced_uidvalidity' => $folderUidValidity]);
                }
            }

            // ── 1. Fetch raw messages from IMAP ──────────────────────────
            $rawMessages = $this->reader->fetchNewMessages($mailbox, $limit);
            $readerError = $this->reader->getLastError();
            if ($readerError !== null) {
                $errors[] = $readerError;
            }

            // If connection/reader error and no messages returned, abort to avoid recording false success
            if ($readerError !== null && empty($rawMessages)) {
                return [
                    'synced' => 0,
                    'skipped' => 0,
                    'errors' => $errors,
                ];
            }

            foreach ($rawMessages as $item) {
                try {
                    $uid = (int) ($item['uid'] ?? 0);

                    // ── 2. Duplicate check by Message-ID or (mailbox_id, uid) ──
                    $messageId = trim($item['message_id'] ?? '');
                    $existing = EmailMessage::query()
                        ->where(function ($q) use ($messageId, $mailbox, $uid) {
                            if ($messageId !== '') {
                                $q->where('message_id', $messageId);
                            }
                            if ($uid > 0) {
                                $q->orWhere(function ($sub) use ($mailbox, $uid) {
                                    $sub->where('mailbox_id', $mailbox->id)
                                        ->where('uid', $uid);
                                });
                            }
                        })
                        ->first();

                    if ($existing) {
                        $skipped++;
                        if ($uid > $highestUid) {
                            $highestUid = $uid;
                        }

                        continue;
                    }

                    // ── 3. Match or Create Conversation Thread ──
                    $conversation = $this->resolveConversationForMessage($mailbox, $item);

                    // ── 4. Persist Incoming EmailMessage atomically ──
                    $message = DB::transaction(function () use ($mailbox, $conversation, $item, $uid, $messageId) {
                        $sentAt = ! empty($item['date']) ? Carbon::parse($item['date']) : Carbon::now();

                        $rawBodyHtml = $item['body_html'] ?: nl2br(e($item['body_text'] ?? ''));
                        $bodyHtml = $this->sanitizer
                            ? $this->sanitizer->sanitizeIncoming($rawBodyHtml)
                            : $rawBodyHtml;

                        $createdMessage = EmailMessage::create([
                            'conversation_id' => $conversation->id,
                            'mailbox_id' => $mailbox->id,
                            'direction' => EmailMessage::DIRECTION_INCOMING,
                            'folder' => EmailMessage::FOLDER_INBOX,
                            'message_id' => $messageId,
                            'in_reply_to' => $item['in_reply_to'] ?? null,
                            'references' => $item['references'] ?? null,
                            'subject' => $item['subject'] ?: '(No subject)',
                            'sender_email' => $item['from_email'],
                            'sender_name' => $item['from_name'] ?? null,
                            'body_html' => $bodyHtml,
                            'body_text' => $item['body_text'] ?? null,
                            'status' => EmailMessage::STATUS_RECEIVED,
                            'sent_at' => $sentAt,
                            'uid' => $uid,
                            'is_read' => false,
                            'is_starred' => false,
                            'is_deleted' => false,
                        ]);

                        // Persist From participant
                        EmailRecipient::create([
                            'email_message_id' => $createdMessage->id,
                            'type' => EmailRecipient::TYPE_FROM,
                            'email' => $item['from_email'],
                            'name' => $item['from_name'] ?? null,
                        ]);

                        // Persist To recipients
                        foreach ($item['to'] ?? [] as $toRec) {
                            if (! empty($toRec['email'])) {
                                EmailRecipient::create([
                                    'email_message_id' => $createdMessage->id,
                                    'type' => EmailRecipient::TYPE_TO,
                                    'email' => $toRec['email'],
                                    'name' => $toRec['name'] ?? null,
                                ]);
                            }
                        }

                        // Persist Cc recipients
                        foreach ($item['cc'] ?? [] as $ccRec) {
                            if (! empty($ccRec['email'])) {
                                EmailRecipient::create([
                                    'email_message_id' => $createdMessage->id,
                                    'type' => EmailRecipient::TYPE_CC,
                                    'email' => $ccRec['email'],
                                    'name' => $ccRec['name'] ?? null,
                                ]);
                            }
                        }

                        // Persist Attachments securely on private local storage
                        foreach ($item['attachments'] ?? [] as $att) {
                            $safeFilename = Str::slug(pathinfo($att['filename'], PATHINFO_FILENAME))
                                .'.'.(pathinfo($att['filename'], PATHINFO_EXTENSION) ?: 'bin');
                            $storagePath = 'communications/email/attachments/'.$createdMessage->id.'/'.Str::random(12).'_'.$safeFilename;

                            Storage::disk('local')->put($storagePath, $att['content']);

                            EmailAttachment::create([
                                'email_message_id' => $createdMessage->id,
                                'source' => 'incoming',
                                'filename' => $att['filename'],
                                'mime_type' => $att['mime_type'],
                                'size_bytes' => $att['size_bytes'] ?? strlen($att['content']),
                                'storage_disk' => 'local',
                                'storage_path' => $storagePath,
                            ]);
                        }

                        // Update conversation timestamps & reopen if closed/resolved
                        $conversationUpdate = [
                            'last_message_at' => $sentAt,
                        ];
                        if (in_array($conversation->status, [EmailConversation::STATUS_RESOLVED, EmailConversation::STATUS_CLOSED], true)) {
                            $conversationUpdate['status'] = EmailConversation::STATUS_OPEN;
                        }
                        $conversation->update($conversationUpdate);

                        return $createdMessage;
                    });

                    // Auto-match business records
                    try {
                        app(EmailRecordLinkService::class)->autoMatchAndLink($conversation, $message);
                    } catch (\Throwable) {
                        // Non-blocking auto-match
                    }

                    // Only advance cursor when successfully persisted
                    if ($uid > $highestUid) {
                        $highestUid = $uid;
                    }
                    $synced++;
                } catch (\Throwable $e) {
                    Log::error("Failed syncing IMAP message UID [{$item['uid']}]: ".$e->getMessage());
                    $errors[] = "UID {$item['uid']}: ".$e->getMessage();
                    // Stop batch processing immediately so cursor does not skip past this failing message
                    break;
                }
            }

            // Update mailbox cursor and sync timestamp
            $mailbox->update([
                'last_synced_uid' => $highestUid,
                'last_synced_at' => Carbon::now(),
            ]);
        } finally {
            $this->reader->disconnect();
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Match an incoming message to an existing conversation thread or create a new one.
     *
     * @param  array<string, mixed>  $item
     */
    public function resolveConversationForMessage(EmailMailbox $mailbox, array $item): EmailConversation
    {
        // 1. Match via in_reply_to
        if (! empty($item['in_reply_to'])) {
            $parent = EmailMessage::where('message_id', trim($item['in_reply_to']))
                ->whereNotNull('conversation_id')
                ->first();
            if ($parent && $parent->conversation) {
                return $parent->conversation;
            }
        }

        // 2. Match via references
        if (! empty($item['references'])) {
            $refIds = preg_split('/\s+/', trim($item['references'])) ?: [];
            foreach ($refIds as $refId) {
                if ($refId === '') {
                    continue;
                }
                $parent = EmailMessage::where('message_id', trim($refId))
                    ->whereNotNull('conversation_id')
                    ->first();
                if ($parent && $parent->conversation) {
                    return $parent->conversation;
                }
            }
        }

        // 3. Match via normalized subject in same mailbox
        $normSubject = $this->normalizeSubject($item['subject'] ?? '');
        if ($normSubject !== '') {
            $recentConversation = EmailConversation::where('mailbox_id', $mailbox->id)
                ->where(function ($q) use ($normSubject) {
                    $q->where('subject', $normSubject)
                        ->orWhere('subject', 'LIKE', "%{$normSubject}%");
                })
                ->where('last_message_at', '>=', Carbon::now()->subDays(30))
                ->latest('last_message_at')
                ->first();

            if ($recentConversation) {
                return $recentConversation;
            }
        }

        // 4. Create new conversation
        $senderName = $item['from_name'] ?: $item['from_email'];

        return EmailConversation::create([
            'mailbox_id' => $mailbox->id,
            'department' => $mailbox->department,
            'subject' => $item['subject'] ?: '(No subject)',
            'status' => EmailConversation::STATUS_OPEN,
            'first_message_at' => Carbon::now(),
            'last_message_at' => Carbon::now(),
            'participant_names' => $senderName,
        ]);
    }

    /**
     * Strip prefixes like "Re:", "Fwd:", "Tr:" from subject.
     */
    public function normalizeSubject(string $subject): string
    {
        $cleaned = preg_replace('/^(re|fwd|fw|tr|réf)\s*:\s*/i', '', trim($subject)) ?? '';

        return trim(preg_replace('/\s+/', ' ', $cleaned) ?? '');
    }
}
