<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Modules\Communications\Email\Jobs\SendEmailMessageJob;
use App\Modules\Communications\Email\Models\EmailAttachment;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmailSendingService
{
    public function __construct(
        private readonly ?EmailSanitizationService $sanitizer = null,
    ) {}

    /**
     * Create an EmailMessage record with all recipients and optional attachments.
     * Returns the message in 'draft' status without dispatching.
     *
     * @param array{
     *   to: array<array{email: string, name?: string}|string>,
     *   cc?: array<array{email: string, name?: string}|string>,
     *   bcc?: array<array{email: string, name?: string}|string>,
     *   subject: string,
     *   body_html: string,
     *   body_text?: string,
     *   sender_profile_id?: int|null,
     *   template_id?: int|null,
     *   created_by?: string|null,
     *   attachments?: array<array{filename: string, mime_type: string, content_b64?: string, storage_disk?: string, storage_path?: string, size_bytes?: int}>,
     *   associations?: array<array{type: string, id: string|int}>,
     * } $payload
     */
    public function createDraft(array $payload): EmailMessage
    {
        return DB::transaction(function () use ($payload) {
            $scheduledAt = ! empty($payload['scheduled_at']) ? \Carbon\Carbon::parse($payload['scheduled_at']) : null;

            $conversationId = $payload['conversation_id'] ?? null;
            if (! $conversationId) {
                $conversation = EmailConversation::create([
                    'mailbox_id' => $payload['mailbox_id'] ?? null,
                    'subject' => $payload['subject'],
                    'status' => EmailConversation::STATUS_OPEN,
                    'first_message_at' => now(),
                    'last_message_at' => now(),
                ]);
                $conversationId = $conversation->id;
            }

            $bodyHtml = $this->sanitizer ? $this->sanitizer->sanitizeOutgoing($payload['body_html'] ?? '') : ($payload['body_html'] ?? '');
            $bodyText = $payload['body_text'] ?? null;
            if (empty($bodyText) && ! empty($bodyHtml)) {
                $bodyText = self::htmlToPlainText($bodyHtml);
            }

            $message = EmailMessage::create([
                'conversation_id' => $conversationId,
                'mailbox_id' => $payload['mailbox_id'] ?? null,
                'direction' => EmailMessage::DIRECTION_OUTGOING,
                'folder' => $scheduledAt && $scheduledAt->isFuture() ? EmailMessage::FOLDER_SCHEDULED : EmailMessage::FOLDER_DRAFT,
                'sender_profile_id' => $payload['sender_profile_id'] ?? null,
                'template_id' => $payload['template_id'] ?? null,
                'subject' => $payload['subject'],
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'status' => EmailMessage::STATUS_DRAFT,
                'scheduled_at' => $scheduledAt,
                'message_id' => $payload['message_id'] ?? null,
                'in_reply_to' => $payload['in_reply_to'] ?? null,
                'references' => $payload['references'] ?? null,
                'created_by' => $payload['created_by'] ?? null,
                'max_attempts' => 3,
            ]);

            $this->attachRecipients($message, $payload);
            $this->attachAttachments($message, $payload['attachments'] ?? []);
            $this->attachAssociations($message, $payload['associations'] ?? []);

            return $message;
        });
    }

    /**
     * Create a draft and immediately queue it for sending.
     * Uses after-commit dispatch so the job only fires after the transaction commits.
     *
     * @param  array  $payload  — same shape as createDraft
     */
    public function createAndQueue(array $payload): EmailMessage
    {
        $message = DB::transaction(function () use ($payload) {
            $message = $this->createDraft($payload);
            $message->update([
                'status' => EmailMessage::STATUS_QUEUED,
                'idempotency_key' => (string) Str::uuid(),
            ]);

            return $message;
        });

        // Dispatch AFTER the transaction commits to avoid the job running before the DB row is committed
        dispatch(new SendEmailMessageJob($message->id))->afterCommit();

        return $message;
    }

    /**
     * Replace the content of an existing DRAFT (fields + recipients). Attachments are handled by EmailAttachmentService.
     *
     * @param  array  $payload  — same recipient/subject/body keys as createDraft
     */
    public function updateDraft(EmailMessage $message, array $payload): EmailMessage
    {
        if ($message->status !== EmailMessage::STATUS_DRAFT) {
            throw new \RuntimeException("Only draft messages can be edited (status: {$message->status}).");
        }

        $bodyHtml = isset($payload['body_html'])
            ? ($this->sanitizer ? $this->sanitizer->sanitizeOutgoing($payload['body_html']) : $payload['body_html'])
            : $message->body_html;

        $bodyText = array_key_exists('body_text', $payload)
            ? $payload['body_text']
            : (isset($payload['body_html']) ? self::htmlToPlainText($bodyHtml) : $message->body_text);

        $message->update([
            'sender_profile_id' => $payload['sender_profile_id'] ?? $message->sender_profile_id,
            'template_id' => array_key_exists('template_id', $payload) ? $payload['template_id'] : $message->template_id,
            'subject' => $payload['subject'] ?? $message->subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ]);

        if (isset($payload['to'])) {
            $message->recipients()->delete();
            $this->attachRecipients($message, $payload);
        }

        return $message;
    }

    /**
     * Move a draft (or failed) message to the queue. Dispatch happens after commit.
     */
    public function queue(EmailMessage $message): EmailMessage
    {
        if (! $message->isSendable()) {
            throw new \RuntimeException("Cannot send message with status '{$message->status}'.");
        }

        if (! $message->recipients()->where('type', 'to')->exists()) {
            throw new \RuntimeException('The message has no recipients.');
        }

        // Guard: never silently send unresolved template placeholders
        $resolver = app(\App\Modules\Communications\Email\Services\TemplateVariableResolver::class);
        $unresolvedTokens = $resolver->extractTokens((string) $message->subject, (string) $message->body_html);
        if (! empty($unresolvedTokens)) {
            throw new \RuntimeException('Cannot send email with unresolved template variables: '.implode(', ', $unresolvedTokens));
        }

        $messageId = $message->message_id ?: "<{$message->id}@waveplus.xyz>";
        $isScheduled = $message->scheduled_at && $message->scheduled_at->isFuture();

        DB::transaction(function () use ($message, $messageId, $isScheduled) {
            $message->update([
                'status' => EmailMessage::STATUS_QUEUED,
                'folder' => $isScheduled ? EmailMessage::FOLDER_SCHEDULED : $message->folder,
                'message_id' => $messageId,
                'idempotency_key' => (string) Str::uuid(),
            ]);
        });

        if ($isScheduled) {
            $delaySeconds = max(1, \Carbon\Carbon::now()->diffInSeconds($message->scheduled_at, false));
            dispatch(new SendEmailMessageJob($message->id))->delay($delaySeconds)->afterCommit();
        } else {
            dispatch(new SendEmailMessageJob($message->id))->afterCommit();
        }

        return $message->refresh();
    }

    /**
     * Re-queue a failed message for retry.
     * Respects max_attempts and idempotency.
     */
    public function retry(EmailMessage $message): EmailMessage
    {
        if (! $message->canRetry()) {
            throw new \RuntimeException("Email message {$message->id} cannot be retried (status: {$message->status}, attempts: {$message->attempts}/{$message->max_attempts})");
        }

        DB::transaction(function () use ($message) {
            $message->update([
                'status' => EmailMessage::STATUS_QUEUED,
                'idempotency_key' => (string) Str::uuid(), // new key on retry
            ]);
        });

        dispatch(new SendEmailMessageJob($message->id))->afterCommit();

        return $message->refresh();
    }

    /**
     * Send synchronously (for system notifications where you cannot queue).
     * Prefer createAndQueue() in all normal paths.
     */
    public function sendNow(array $payload): EmailMessage
    {
        $message = $this->createDraft($payload);

        $message->update([
            'status' => EmailMessage::STATUS_QUEUED,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        // Run the job inline
        (new SendEmailMessageJob($message->id))->handle();

        return $message->refresh();
    }

    // ── Private helpers ────────────────────────────────────────────────

    private function attachRecipients(EmailMessage $message, array $payload): void
    {
        $seenEmails = [];
        $senderEmail = $message->senderProfile?->email ? strtolower(trim($message->senderProfile->email)) : null;

        foreach (['to', 'cc', 'bcc'] as $type) {
            foreach ($payload[$type] ?? [] as $recipient) {
                if (is_string($recipient)) {
                    $email = trim($recipient);
                    $name = null;
                    $recipientType = null;
                    $recipientId = null;
                    $contactId = null;
                    $emailSource = null;
                } else {
                    $email = trim($recipient['email'] ?? '');
                    $name = $recipient['name'] ?? null;
                    $recipientType = $recipient['recipient_type'] ?? ($recipient['entity_type'] ?? null);
                    $recipientId = $recipient['recipient_id'] ?? ($recipient['entity_id'] ?? null);
                    $contactId = $recipient['contact_id'] ?? null;
                    $emailSource = $recipient['email_source'] ?? ($recipient['source'] ?? null);
                }

                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $lowerEmail = strtolower($email);

                // Prevent duplicate recipients across To, CC, and BCC
                if (in_array($lowerEmail, $seenEmails, true)) {
                    continue;
                }

                // Do not automatically add sender to CC or BCC
                if ($type !== 'to' && $senderEmail && $lowerEmail === $senderEmail) {
                    continue;
                }

                $seenEmails[] = $lowerEmail;

                EmailRecipient::create([
                    'email_message_id' => $message->id,
                    'type' => $type,
                    'email' => $email,
                    'name' => $name,
                    'recipient_type' => $recipientType ? (string) $recipientType : null,
                    'recipient_id' => $recipientId ? (string) $recipientId : null,
                    'contact_id' => $contactId ? (string) $contactId : null,
                    'email_source' => $emailSource ? (string) $emailSource : null,
                ]);
            }
        }
    }

    private function attachAttachments(EmailMessage $message, array $attachments): void
    {
        foreach ($attachments as $att) {
            EmailAttachment::create([
                'email_message_id' => $message->id,
                'filename' => $att['filename'],
                'mime_type' => $att['mime_type'],
                'size_bytes' => $att['size_bytes'] ?? 0,
                'storage_disk' => $att['storage_disk'] ?? null,
                'storage_path' => $att['storage_path'] ?? null,
                'content_b64' => $att['content_b64'] ?? null,
            ]);
        }
    }

    private function attachAssociations(EmailMessage $message, array $associations): void
    {
        foreach ($associations as $assoc) {
            $modelClass = \App\Modules\Communications\Email\Models\EmailRecordLink::resolveModelClass($assoc['type']) ?: $assoc['type'];

            $message->associations()->firstOrCreate([
                'conversation_id' => $message->conversation_id,
                'associable_type' => $modelClass,
                'associable_id' => (string) $assoc['id'],
            ], [
                'link_type' => 'manual',
                'is_confirmed' => true,
                'confidence_score' => 1.00,
            ]);
        }

        if ($message->conversation) {
            try {
                app(EmailRecordLinkService::class)->autoMatchAndLink($message->conversation, $message);
            } catch (\Throwable) {
                // Non-blocking
            }
        }
    }

    public static function htmlToPlainText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $text = preg_replace('/<(?:br|br\s*\/)>/i', "\n", $html);
        $text = preg_replace('/<\/(?:p|div|h[1-6]|tr|li)>/i', "\n", $text);
        $text = preg_replace('/<(?:li)>/i', '• ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
