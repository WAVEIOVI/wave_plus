<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Commands;

use App\Modules\Communications\Email\Models\EmailMailbox;
use App\Modules\Communications\Email\Services\EmailSynchronizationService;
use Illuminate\Console\Command;

class SyncEmailMailboxesCommand extends Command
{
    protected $signature = 'email:sync-mailboxes
                            {--mailbox= : Specific mailbox ID to sync}
                            {--limit=50 : Maximum number of new messages to fetch per mailbox}';

    protected $description = 'Synchronize incoming emails from configured company IMAP mailboxes';

    public function handle(EmailSynchronizationService $syncService): int
    {
        $mailboxId = $this->option('mailbox');
        $limit = (int) $this->option('limit');

        $query = EmailMailbox::where('is_active', true);
        if ($mailboxId) {
            $query->where('id', $mailboxId);
        }

        $mailboxes = $query->get();

        if ($mailboxes->isEmpty()) {
            $this->info('No active company mailboxes found to synchronize.');

            return self::SUCCESS;
        }

        $this->info("Starting synchronization for {$mailboxes->count()} mailbox(es)...");

        $totalSynced = 0;
        $totalSkipped = 0;

        foreach ($mailboxes as $mailbox) {
            $this->line("Syncing [{$mailbox->name}] <{$mailbox->email}> (Dept: {$mailbox->department})...");

            $result = $syncService->syncMailbox($mailbox, $limit);

            $this->info(" -> Synced: {$result['synced']}, Skipped: {$result['skipped']}");

            if (! empty($result['errors'])) {
                foreach ($result['errors'] as $err) {
                    $this->error("    - {$err}");
                }
            }

            $totalSynced += $result['synced'];
            $totalSkipped += $result['skipped'];
        }

        $this->info("Completed. Total messages synced: {$totalSynced}, skipped: {$totalSkipped}.");

        return self::SUCCESS;
    }
}
