<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\Email\Models\EmailMailbox;
use App\Modules\Communications\Email\Services\EmailSynchronizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmailMailboxController extends Controller
{
    public function __construct(
        private readonly EmailSynchronizationService $syncService,
    ) {}

    /**
     * List all shared company inboxes accessible by the user.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailMailbox::class);

        $user = $request->user();
        $mailboxes = EmailMailbox::where('is_active', true)
            ->withCount(['conversations as open_conversations_count' => function ($q) {
                $q->where('status', 'open');
            }])
            ->get()
            ->filter(fn (EmailMailbox $mb) => $mb->isAuthorizedForUser($user))
            ->values();

        return response()->json([
            'data' => $mailboxes,
        ]);
    }

    /**
     * Trigger manual IMAP synchronization for a specific shared mailbox.
     */
    public function sync(Request $request, EmailMailbox $mailbox): JsonResponse
    {
        Gate::authorize('sync', $mailbox);

        $limit = $request->integer('limit', 50);
        $result = $this->syncService->syncMailbox($mailbox, $limit);

        return response()->json([
            'message' => "Mailbox synced. {$result['synced']} new message(s) imported.",
            'data' => [
                'mailbox' => $mailbox->refresh(),
                'sync_result' => $result,
            ],
        ]);
    }
}
