<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Models\EmailConversationNote;
use App\Modules\Communications\Email\Services\EmailAccessControlService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EmailConversationController extends Controller
{
    public function __construct(
        private readonly EmailAccessControlService $accessControl,
    ) {}

    /**
     * List shared conversations.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailConversation::class);

        $baseQuery = EmailConversation::query();
        $this->accessControl->applyConversationScope($baseQuery, $request->user());

        $conversations = QueryBuilder::for($baseQuery)
            ->with(['mailbox', 'assignedTo'])
            ->withCount('messages')
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('department'),
                AllowedFilter::exact('mailbox_id'),
                AllowedFilter::exact('assigned_to'),
                AllowedFilter::exact('is_starred'),
                AllowedFilter::exact('is_archived'),
                AllowedFilter::partial('subject'),
            ])
            ->allowedSorts(['last_message_at', 'created_at', 'status'])
            ->defaultSort('-last_message_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $conversations->items(),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Show a single conversation thread with all messages and notes.
     */
    public function show(EmailConversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $conversation->load([
            'mailbox',
            'assignedTo',
            'messages.senderProfile',
            'messages.recipients',
            'messages.attachments',
            'notes.author',
        ]);

        return response()->json([
            'data' => $conversation,
        ]);
    }

    /**
     * Assign conversation to an employee/user.
     */
    public function assign(Request $request, EmailConversation $conversation): JsonResponse
    {
        Gate::authorize('assign', $conversation);

        $validated = $request->validate([
            'assigned_to' => 'nullable|string|exists:users,id',
        ]);

        $targetUser = ! empty($validated['assigned_to']) ? User::find($validated['assigned_to']) : null;
        if (! $this->accessControl->canAssignConversation($request->user(), $conversation, $targetUser)) {
            return response()->json([
                'message' => 'The selected employee is not authorized to access this department or mailbox.',
            ], 422);
        }

        $conversation->update([
            'assigned_to' => $validated['assigned_to'] ?? null,
            'assigned_by' => $validated['assigned_to'] ? $request->user()->id : null,
            'assigned_at' => $validated['assigned_to'] ? Carbon::now() : null,
        ]);

        return response()->json([
            'message' => 'Conversation assignment updated.',
            'data' => $conversation->fresh(['assignedTo', 'assignedBy']),
        ]);
    }

    /**
     * Update conversation status (open, pending, resolved, closed).
     */
    public function updateStatus(Request $request, EmailConversation $conversation): JsonResponse
    {
        Gate::authorize('changeStatus', $conversation);

        $validated = $request->validate([
            'status' => 'required|string|in:open,pending,resolved,closed',
        ]);

        $conversation->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => "Conversation status updated to '{$conversation->status}'.",
            'data' => $conversation->fresh(),
        ]);
    }

    /**
     * Add an internal team note to the conversation.
     */
    public function addNote(Request $request, EmailConversation $conversation): JsonResponse
    {
        Gate::authorize('addNote', $conversation);

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $note = EmailConversationNote::create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);

        return response()->json([
            'message' => 'Internal note added.',
            'data' => $note->load('author'),
        ], 201);
    }
}
