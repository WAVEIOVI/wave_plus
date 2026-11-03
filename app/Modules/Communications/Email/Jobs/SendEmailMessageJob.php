<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Jobs;

use App\Modules\Communications\Email\Mail\WpEmailMailable;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly string $messageId,
    ) {}

    public function handle(): void
    {
        $message = EmailMessage::with(['senderProfile', 'recipients', 'attachments'])
            ->find($this->messageId);

        if (! $message) {
            Log::warning('SendEmailMessageJob: EmailMessage not found', ['id' => $this->messageId]);

            return;
        }

        // Idempotency: if already sent, skip silently
        if ($message->status === EmailMessage::STATUS_SENT) {
            Log::info('SendEmailMessageJob: message already sent, skipping', ['id' => $this->messageId]);

            return;
        }

        // Mark as sending (prevents concurrent dispatch of same message)
        DB::transaction(function () use ($message) {
            $message->refresh(); // re-read inside transaction
            if ($message->status === EmailMessage::STATUS_SENT) {
                return; // double-checked inside transaction
            }
            $message->update([
                'status' => EmailMessage::STATUS_SENDING,
                'attempts' => $message->attempts + 1,
            ]);
        });

        // Re-read after transaction
        $message->refresh();
        if ($message->status !== EmailMessage::STATUS_SENDING) {
            return;
        }

        try {
            $this->sendViaMail($message);

            $message->update([
                'status' => EmailMessage::STATUS_SENT,
                'folder' => EmailMessage::FOLDER_SENT,
                'sent_at' => now(),
                'last_error' => null,
            ]);

            if ($message->conversation_id) {
                $message->conversation()->update(['last_message_at' => now()]);
            }

            Log::info('SendEmailMessageJob: email sent', [
                'id' => $this->messageId,
                'subject' => $message->subject,
            ]);

        } catch (\Throwable $e) {
            $message->update([
                'status' => EmailMessage::STATUS_FAILED,
                'failed_at' => now(),
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            Log::error('SendEmailMessageJob: failed to send email', [
                'id' => $this->messageId,
                'subject' => $message->subject,
                'error' => $e->getMessage(),
                'attempts' => $message->attempts,
            ]);

            throw $e; // Re-throw so Laravel retries up to $tries
        }
    }

    private function sendViaMail(EmailMessage $message): void
    {
        $toRecipients = $message->recipients
            ->where('type', EmailRecipient::TYPE_TO)
            ->values();
        $ccRecipients = $message->recipients
            ->where('type', EmailRecipient::TYPE_CC)
            ->values();
        $bccRecipients = $message->recipients
            ->where('type', EmailRecipient::TYPE_BCC)
            ->values();

        if ($toRecipients->isEmpty()) {
            throw new \InvalidArgumentException('No TO recipients on email message '.$message->id);
        }

        // Use ['email' => ..., 'name' => ...] format — normalizeRecipient() casts
        // associative arrays to (object) so ->email and ->name are accessible.
        $mapAddresses = fn ($r) => ['email' => $r->email, 'name' => $r->name ?? ''];

        $pending = Mail::to($toRecipients->map($mapAddresses)->toArray());

        if ($ccRecipients->isNotEmpty()) {
            $pending->cc($ccRecipients->map($mapAddresses)->toArray());
        }

        if ($bccRecipients->isNotEmpty()) {
            $pending->bcc($bccRecipients->map($mapAddresses)->toArray());
        }

        $pending->send(new WpEmailMailable($message));
    }

    public function failed(\Throwable $e): void
    {
        // This is called after all retries are exhausted
        $message = EmailMessage::find($this->messageId);
        if ($message && $message->status !== EmailMessage::STATUS_SENT) {
            $message->update([
                'status' => EmailMessage::STATUS_FAILED,
                'failed_at' => now(),
                'last_error' => mb_substr('All retry attempts exhausted: '.$e->getMessage(), 0, 1000),
            ]);
        }

        Log::error('SendEmailMessageJob: all retries exhausted', [
            'id' => $this->messageId,
            'error' => $e->getMessage(),
        ]);
    }
}
