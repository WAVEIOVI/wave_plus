<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Services\EmailRecordLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmailRecordLinkController extends Controller
{
    public function __construct(
        protected EmailRecordLinkService $linkService
    ) {}

    /**
     * Get all business records associated with a conversation.
     * GET /api/communications/email/conversations/{conversation}/links
     */
    public function index(EmailConversation $conversation, Request $request): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $links = $this->linkService->getLinksForConversation(
            $conversation,
            $request->user(),
            $request->boolean('include_unconfirmed', true)
        );

        return response()->json([
            'data' => $links,
        ]);
    }

    /**
     * Link a conversation to a business record.
     * POST /api/communications/email/conversations/{conversation}/links
     */
    public function store(EmailConversation $conversation, Request $request): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $validated = $request->validate([
            'record_type' => 'required|string|max:50',
            'record_id' => 'required|string|max:36',
            'reason' => 'nullable|string|max:255',
            'message_id' => 'nullable|string|max:26|exists:email_messages,id',
        ]);

        $link = $this->linkService->linkRecord(
            $conversation,
            $validated['record_type'],
            $validated['record_id'],
            $request->user(),
            'manual',
            true,
            1.00,
            $validated['reason'] ?? null,
            $validated['message_id'] ?? null
        );

        return response()->json([
            'message' => 'Record linked successfully.',
            'data' => $link,
        ], 201);
    }

    /**
     * Unlink a conversation from a business record.
     * DELETE /api/communications/email/conversations/{conversation}/links
     */
    public function destroy(EmailConversation $conversation, Request $request): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $validated = $request->validate([
            'record_type' => 'required|string|max:50',
            'record_id' => 'required|string|max:36',
        ]);

        $unlinked = $this->linkService->unlinkRecord(
            $conversation,
            $validated['record_type'],
            $validated['record_id'],
            $request->user()
        );

        return response()->json([
            'message' => $unlinked ? 'Record unlinked successfully.' : 'Record link not found.',
            'unlinked' => $unlinked,
        ]);
    }

    /**
     * Confirm an ambiguous suggested record association.
     * POST /api/communications/email/record-links/{link}/confirm
     */
    public function confirm(int $link, Request $request): JsonResponse
    {
        $linkModel = $this->linkService->confirmSuggestion($link, $request->user());

        return response()->json([
            'message' => 'Suggested association confirmed.',
            'data' => $linkModel,
        ]);
    }

    /**
     * Dismiss an ambiguous suggested record association.
     * POST /api/communications/email/record-links/{link}/dismiss
     */
    public function dismiss(int $link, Request $request): JsonResponse
    {
        $dismissed = $this->linkService->dismissSuggestion($link, $request->user());

        return response()->json([
            'message' => 'Suggested association dismissed.',
            'dismissed' => $dismissed,
        ]);
    }

    /**
     * Get conversations associated with a specific business record.
     * GET /api/communications/email/records/{recordType}/{recordId}/conversations
     */
    public function getRecordConversations(string $recordType, string|int $recordId, Request $request): JsonResponse
    {
        $paginated = $this->linkService->getConversationsForRecord(
            $recordType,
            $recordId,
            $request->user(),
            $request->integer('per_page', 15)
        );

        return response()->json($paginated);
    }

    /**
     * Run auto-matching for a conversation on demand.
     * POST /api/communications/email/conversations/{conversation}/auto-match
     */
    public function autoMatch(EmailConversation $conversation, Request $request): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $created = $this->linkService->autoMatchAndLink($conversation);

        return response()->json([
            'message' => count($created).' association(s) matched.',
            'data' => $this->linkService->getLinksForConversation($conversation, $request->user(), true),
        ]);
    }
}
