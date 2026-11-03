<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Modules\Communications\Email\Contracts\MailboxReaderInterface;
use App\Modules\Communications\Email\Models\EmailMailbox;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

class ImapMailboxReader implements MailboxReaderInterface
{
    private ?Client $client = null;

    private ?string $lastError = null;

    public function connect(EmailMailbox $mailbox): bool
    {
        $this->lastError = null;

        if (! $mailbox->imap_host || ! $mailbox->decrypted_password) {
            $this->lastError = "Cannot connect to mailbox [{$mailbox->id}] {$mailbox->email}: Missing host or password.";
            Log::warning($this->lastError);

            return false;
        }

        try {
            $cm = new ClientManager;
            $this->client = $cm->make([
                'host' => $mailbox->imap_host,
                'port' => $mailbox->imap_port ?? 993,
                'encryption' => $mailbox->imap_encryption ?? 'ssl',
                'validate_cert' => config('app.env') === 'production',
                'username' => $mailbox->imap_username ?: $mailbox->email,
                'password' => $mailbox->decrypted_password,
                'protocol' => 'imap',
            ]);

            $this->client->connect();

            return $this->client->isConnected();
        } catch (\Throwable $e) {
            $this->lastError = "IMAP connection failed for mailbox [{$mailbox->id}] {$mailbox->email}: ".$e->getMessage();
            Log::error($this->lastError);
            $this->client = null;

            return false;
        }
    }

    public function fetchNewMessages(EmailMailbox $mailbox, int $limit = 50): array
    {
        if (! $this->client || ! $this->client->isConnected()) {
            if (! $this->connect($mailbox)) {
                return [];
            }
        }

        try {
            $folderName = $mailbox->imap_folder ?: 'INBOX';
            $folder = $this->client->getFolder($folderName);
            if (! $folder) {
                $this->lastError = "Folder [{$folderName}] not found in mailbox [{$mailbox->id}].";
                Log::warning($this->lastError);

                return [];
            }

            $query = $folder->query();
            if ($mailbox->last_synced_uid) {
                $query = $query->where('UID', '>', $mailbox->last_synced_uid);
            }

            $rawMessages = $query->limit($limit)->get();
            $results = [];

            foreach ($rawMessages as $msg) {
                $results[] = $this->normalizeMessage($msg);
            }

            return $results;
        } catch (\Throwable $e) {
            $this->lastError = "Failed fetching IMAP messages for mailbox [{$mailbox->id}]: ".$e->getMessage();
            Log::error($this->lastError);

            return [];
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getFolderUidValidity(EmailMailbox $mailbox): ?int
    {
        if (! $this->client || ! $this->client->isConnected()) {
            if (! $this->connect($mailbox)) {
                return null;
            }
        }

        try {
            $folderName = $mailbox->imap_folder ?: 'INBOX';
            $folder = $this->client->getFolder($folderName);
            if (! $folder) {
                return null;
            }

            $status = $folder->status();
            if (is_array($status) && isset($status['uidvalidity'])) {
                return (int) $status['uidvalidity'];
            }
        } catch (\Throwable $e) {
            Log::warning("Could not fetch UIDVALIDITY for mailbox [{$mailbox->id}]: ".$e->getMessage());
        }

        return null;
    }

    public function disconnect(): void
    {
        if ($this->client && $this->client->isConnected()) {
            try {
                $this->client->disconnect();
            } catch (\Throwable) {
                // Ignore disconnect exceptions
            }
        }
        $this->client = null;
    }

    /**
     * @param  \Webklex\PHPIMAP\Message  $msg
     * @return array<string, mixed>
     */
    private function normalizeMessage($msg): array
    {
        $fromObj = $msg->getFrom();
        $fromItem = is_array($fromObj) ? reset($fromObj) : $fromObj;
        $fromEmail = is_object($fromItem) ? ($fromItem->mail ?? '') : (string) $fromItem;
        $fromName = is_object($fromItem) ? ($fromItem->personal ?? null) : null;

        $to = [];
        $toObj = $msg->getTo();
        if (is_iterable($toObj)) {
            foreach ($toObj as $item) {
                $to[] = [
                    'email' => is_object($item) ? ($item->mail ?? '') : (string) $item,
                    'name' => is_object($item) ? ($item->personal ?? null) : null,
                ];
            }
        }

        $cc = [];
        $ccObj = $msg->getCc();
        if (is_iterable($ccObj)) {
            foreach ($ccObj as $item) {
                $cc[] = [
                    'email' => is_object($item) ? ($item->mail ?? '') : (string) $item,
                    'name' => is_object($item) ? ($item->personal ?? null) : null,
                ];
            }
        }

        $attachments = [];
        $rawAttachments = $msg->getAttachments();
        if (is_iterable($rawAttachments)) {
            foreach ($rawAttachments as $att) {
                $content = $att->getContent();
                $attachments[] = [
                    'filename' => $att->getName() ?: 'attachment.bin',
                    'mime_type' => $att->getMimeType() ?: 'application/octet-stream',
                    'content' => $content,
                    'size_bytes' => strlen($content),
                ];
            }
        }

        $rawMessageId = $msg->getMessageId();
        $messageId = is_array($rawMessageId) ? (string) reset($rawMessageId) : (string) $rawMessageId;
        if (! $messageId || trim($messageId) === '') {
            $messageId = '<'.sha1($msg->getUid().($msg->getSubject() ?? '')).'@waveplus.xyz>';
        }

        $inReplyTo = $msg->getInReplyTo();
        if (is_array($inReplyTo)) {
            $inReplyTo = (string) reset($inReplyTo);
        }

        $references = $msg->getReferences();
        if (is_array($references)) {
            $references = implode(' ', $references);
        }

        return [
            'uid' => (int) $msg->getUid(),
            'message_id' => trim($messageId),
            'in_reply_to' => $inReplyTo ? trim((string) $inReplyTo) : null,
            'references' => $references ? trim((string) $references) : null,
            'subject' => (string) ($msg->getSubject() ?? '(No subject)'),
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'to' => $to,
            'cc' => $cc,
            'bcc' => [],
            'body_html' => (string) ($msg->getHTMLBody() ?: nl2br(htmlspecialchars((string) $msg->getTextBody()))),
            'body_text' => (string) $msg->getTextBody(),
            'date' => (string) $msg->getDate(),
            'attachments' => $attachments,
        ];
    }
}
