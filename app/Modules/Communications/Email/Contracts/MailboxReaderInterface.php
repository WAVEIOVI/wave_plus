<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Contracts;

use App\Modules\Communications\Email\Models\EmailMailbox;

interface MailboxReaderInterface
{
    /**
     * Connect to the given company mailbox.
     */
    public function connect(EmailMailbox $mailbox): bool;

    /**
     * Fetch newly arrived messages since the mailbox's last_synced_uid.
     *
     * @return array<int, array{
     *   uid: int,
     *   message_id: string,
     *   in_reply_to: ?string,
     *   references: ?string,
     *   subject: string,
     *   from_email: string,
     *   from_name: ?string,
     *   to: array<int, array{ email: string, name: ?string }>,
     *   cc: array<int, array{ email: string, name: ?string }>,
     *   bcc: array<int, array{ email: string, name: ?string }>,
     *   body_html: string,
     *   body_text: ?string,
     *   date: ?string,
     *   attachments: array<int, array{ filename: string, mime_type: string, content: string, size_bytes: int }>
     * }>
     */
    public function fetchNewMessages(EmailMailbox $mailbox, int $limit = 50): array;

    /**
     * Gracefully close the connection.
     */
    public function disconnect(): void;

    /**
     * Get the last error encountered during connection or fetching.
     */
    public function getLastError(): ?string;

    /**
     * Get the UIDVALIDITY of the mailbox folder if supported.
     */
    public function getFolderUidValidity(EmailMailbox $mailbox): ?int;
}
