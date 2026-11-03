<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailRecipient;
use App\Modules\Communications\Email\Models\EmailRecordLink;
use App\Modules\Communications\Email\Services\EmailAccessControlService;
use App\Modules\Communications\Email\Services\EmailAttachmentService;
use App\Modules\Communications\Email\Services\EmailSendingService;
use App\Modules\Communications\Email\Services\SenderProfileService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EmailMessageController extends Controller
{
    public function __construct(
        private readonly EmailSendingService $service,
        private readonly SenderProfileService $senderProfileService,
        private readonly EmailAttachmentService $attachmentService,
        private readonly EmailAccessControlService $accessControl,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailMessage::class);

        $filter = $request->input('filter', 'inbox');
        $label = $request->input('label');
        $mailboxId = $request->input('mailbox_id');
        $q = trim((string) $request->input('q', ''));
        $user = $request->user();

        $query = EmailMessage::with(['recipients', 'senderProfile', 'attachments', 'conversation']);

        // Apply centralized access control scope
        $this->accessControl->applyMessageScope($query, $user);

        // Apply folder filter
        match ($filter) {
            'sent' => $query->where(function ($sq) {
                $sq->where('folder', EmailMessage::FOLDER_SENT)
                    ->orWhere(fn ($q2) => $q2->where('status', EmailMessage::STATUS_SENT)->where('direction', EmailMessage::DIRECTION_OUTGOING));
            })->where('is_deleted', false),

            'draft' => $query->where(function ($sq) {
                $sq->where('folder', EmailMessage::FOLDER_DRAFT)
                    ->orWhere('status', EmailMessage::STATUS_DRAFT);
            })->where('is_deleted', false),

            'scheduled' => $query->where(function ($sq) {
                $sq->where('folder', EmailMessage::FOLDER_SCHEDULED)
                    ->orWhere(fn ($q2) => $q2->where('status', EmailMessage::STATUS_QUEUED)->where('scheduled_at', '>', Carbon::now()));
            })->where('is_deleted', false),

            'starred' => $query->where('is_starred', true)->where('is_deleted', false),

            'spam' => $query->where('folder', EmailMessage::FOLDER_SPAM)->where('is_deleted', false),

            'trashed' => $query->where(function ($sq) {
                $sq->where('is_deleted', true)
                    ->orWhere('folder', EmailMessage::FOLDER_TRASH);
            }),

            'archived' => $query->where('folder', EmailMessage::FOLDER_ARCHIVED)->where('is_deleted', false),

            'assigned' => $query->whereHas('conversation', function ($cq) use ($user) {
                $cq->where('assigned_to', $user->id);
            })->where('is_deleted', false),

            'unanswered' => $query->whereHas('conversation', fn ($cq) => $cq->unanswered())->where('is_deleted', false),

            'pending_replies' => $query->whereHas('conversation', fn ($cq) => $cq->pendingReplies())->where('is_deleted', false),

            'unassigned' => $query->whereHas('conversation', fn ($cq) => $cq->unassigned())->where('is_deleted', false),

            'recent_activity' => $query->whereHas('conversation', fn ($cq) => $cq->recentActivity(7))->where('is_deleted', false),

            default => $query->where(function ($sq) {
                $sq->where('folder', EmailMessage::FOLDER_INBOX)
                    ->orWhere('direction', EmailMessage::DIRECTION_INCOMING);
            })->where('is_deleted', false),
        };

        // Mailbox filter (for shared inboxes)
        if ($mailboxId) {
            $query->where('mailbox_id', $mailboxId);
        }

        // Search text (global q)
        if ($q !== '') {
            $query->where(function ($sq) use ($q) {
                $sq->where('subject', 'LIKE', "%{$q}%")
                    ->orWhere('body_html', 'LIKE', "%{$q}%")
                    ->orWhere('body_text', 'LIKE', "%{$q}%")
                    ->orWhereHas('recipients', function ($rq) use ($q) {
                        $rq->where('email', 'LIKE', "%{$q}%")
                            ->orWhere('name', 'LIKE', "%{$q}%");
                    });
            });
        }

        // ── Advanced Search Criteria ─────────────────────────────────────
        if ($sender = $request->input('sender') ?: $request->input('from')) {
            $query->where(function ($sq) use ($sender) {
                $sq->whereHas('senderProfile', fn ($pq) => $pq->where('email', 'LIKE', "%{$sender}%")->orWhere('name', 'LIKE', "%{$sender}%"))
                    ->orWhereHas('conversation.mailbox', fn ($mq) => $mq->where('email', 'LIKE', "%{$sender}%"));
            });
        }

        if ($recipient = $request->input('recipient') ?: $request->input('to')) {
            $query->whereHas('recipients', function ($rq) use ($recipient) {
                $rq->where('email', 'LIKE', "%{$recipient}%")
                    ->orWhere('name', 'LIKE', "%{$recipient}%");
            });
        }

        if ($subject = $request->input('subject')) {
            $query->where('subject', 'LIKE', "%{$subject}%");
        }

        if ($content = $request->input('content')) {
            $query->where(function ($sq) use ($content) {
                $sq->where('body_html', 'LIKE', "%{$content}%")
                    ->orWhere('body_text', 'LIKE', "%{$content}%");
            });
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo = $request->input('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        if ($department = $request->input('department')) {
            $query->where(function ($sq) use ($department) {
                $sq->whereHas('conversation', fn ($cq) => $cq->where('department', $department))
                    ->orWhereHas('mailbox', fn ($mq) => $mq->where('department', $department));
            });
        }

        if ($status = $request->input('status')) {
            $query->where(function ($sq) use ($status) {
                $sq->where('status', $status)
                    ->orWhereHas('conversation', fn ($cq) => $cq->where('status', $status));
            });
        }

        if ($recordType = $request->input('linked_record_type')) {
            $modelClass = EmailRecordLink::resolveModelClass($recordType);
            $recordId = $request->input('linked_record_id');
            $query->where(function ($sq) use ($modelClass, $recordType, $recordId) {
                $sq->whereHas('conversation.recordLinks', function ($lq) use ($modelClass, $recordType, $recordId) {
                    $lq->where(fn ($sub) => $sub->where('associable_type', $modelClass)->orWhere('associable_type', $recordType));
                    if ($recordId) {
                        $lq->where('associable_id', (string) $recordId);
                    }
                })->orWhereHas('recordLinks', function ($lq) use ($modelClass, $recordType, $recordId) {
                    $lq->where(fn ($sub) => $sub->where('associable_type', $modelClass)->orWhere('associable_type', $recordType));
                    if ($recordId) {
                        $lq->where('associable_id', (string) $recordId);
                    }
                });
            });
        }

        if ($request->has('has_attachments')) {
            if ($request->boolean('has_attachments')) {
                $query->whereHas('attachments');
            } else {
                $query->whereDoesntHave('attachments');
            }
        }

        if ($assignedTo = $request->input('assigned_to')) {
            $query->whereHas('conversation', fn ($cq) => $cq->where('assigned_to', $assignedTo));
        }

        $paginated = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 50));

        // Format for Vuexy Email Center
        $formattedEmails = collect($paginated->items())->map(fn (EmailMessage $m) => $this->formatForUi($m))->all();

        // Calculate metadata counts scoped to accessible departments/mailboxes
        $scopedMsg = fn () => $this->accessControl->applyMessageScope(EmailMessage::query(), $user);
        $scopedConv = fn () => $this->accessControl->applyConversationScope(EmailConversation::query(), $user);

        $emailsMeta = [
            'inbox' => $scopedMsg()->where(fn ($sq) => $sq->where('folder', 'inbox')->orWhere('direction', 'incoming'))->where('is_deleted', false)->where('is_read', false)->count(),
            'assigned' => $scopedMsg()->whereHas('conversation', fn ($cq) => $cq->where('assigned_to', $user->id))->where('is_deleted', false)->count(),
            'sent' => $scopedMsg()->where(fn ($sq) => $sq->where('folder', 'sent')->orWhere('status', 'sent'))->where('is_deleted', false)->count(),
            'draft' => $scopedMsg()->where(fn ($sq) => $sq->where('folder', 'draft')->orWhere('status', 'draft'))->where('is_deleted', false)->count(),
            'scheduled' => $scopedMsg()->where(fn ($sq) => $sq->where('folder', 'scheduled')->orWhere('status', 'queued'))->where('is_deleted', false)->count(),
            'star' => $scopedMsg()->where('is_starred', true)->where('is_deleted', false)->count(),
            'spam' => $scopedMsg()->where('folder', 'spam')->where('is_deleted', false)->count(),
            'trash' => $scopedMsg()->where(fn ($sq) => $sq->where('is_deleted', true)->orWhere('folder', 'trash'))->count(),
            'archived' => $scopedMsg()->where('folder', 'archived')->where('is_deleted', false)->count(),
            'unanswered' => $scopedConv()->unanswered()->count(),
            'pending_replies' => $scopedConv()->pendingReplies()->count(),
            'unassigned' => $scopedConv()->unassigned()->count(),
            'recent_activity' => $scopedConv()->recentActivity(7)->count(),
        ];

        return response()->json([
            'emails' => $formattedEmails,
            'emailsMeta' => $emailsMeta,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Create a draft (or create-and-queue with send_immediately). Accepts JSON or multipart.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', EmailMessage::class);

        $validated = $request->validate($this->rules());

        $profile = $this->senderProfileService->resolveAuthorizedProfile(
            $request->user(),
            $request->filled('sender_profile_id') ? (int) $request->input('sender_profile_id') : null
        );

        $payload = array_merge($validated, [
            'sender_profile_id' => $profile->id,
            'created_by' => $request->user()->id,
        ]);

        // Files / document references / associations are persisted by dedicated services, never raw in createDraft().
        unset($payload['attachments'], $payload['document_attachments'], $payload['associations'], $payload['primary_context_type'], $payload['primary_context_id']);

        $message = DB::transaction(function () use ($payload, $request) {
            $message = $this->service->createDraft($payload);
            $this->attachmentService->sync(
                $message,
                $request->user(),
                $request->file('attachments', []),
                $request->input('document_attachments', []),
            );

            $this->syncMessageAssociations($message, $request);

            return $message;
        });

        if ($request->boolean('send_immediately')) {
            $message = $this->queueOrFail($message);
        }

        return response()->json([
            'message' => 'Email message created.',
            'data' => $message->load(['recipients', 'attachments', 'senderProfile']),
        ], 201);
    }

    public function show(EmailMessage $message): JsonResponse
    {
        Gate::authorize('view', $message);

        $message->load(['recipients', 'attachments', 'senderProfile', 'template', 'associations', 'conversation']);

        return response()->json([
            'data' => $message,
            'email' => $this->formatForUi($message),
        ]);
    }

    /**
     * Replace the content of a draft. Multipart is supported via POST + _method=PUT.
     */
    public function update(Request $request, EmailMessage $message): JsonResponse
    {
        Gate::authorize('update', $message);

        if ($message->status !== EmailMessage::STATUS_DRAFT) {
            return response()->json(['message' => "Only drafts can be edited (status '{$message->status}')."], 422);
        }

        $validated = $request->validate(array_merge($this->rules(partial: true), [
            'sync_attachments' => 'boolean',
            'keep_attachment_ids' => 'nullable|array',
            'keep_attachment_ids.*' => 'integer',
        ]));

        if ($request->filled('sender_profile_id')) {
            $validated['sender_profile_id'] = $this->senderProfileService
                ->resolveAuthorizedProfile($request->user(), (int) $request->input('sender_profile_id'))->id;
        }

        $draftData = $validated;
        unset($draftData['attachments'], $draftData['document_attachments'], $draftData['associations'], $draftData['primary_context_type'], $draftData['primary_context_id'], $draftData['sync_attachments'], $draftData['keep_attachment_ids']);

        $removedPaths = DB::transaction(function () use ($message, $draftData, $validated, $request) {
            $this->service->updateDraft($message, $draftData);

            $paths = $this->attachmentService->sync(
                $message,
                $request->user(),
                $request->file('attachments', []),
                $request->input('document_attachments', []),
                $request->boolean('sync_attachments') ? ($validated['keep_attachment_ids'] ?? []) : null,
            );

            $this->syncMessageAssociations($message, $request);

            return $paths;
        });

        $this->attachmentService->deleteFiles($removedPaths);

        if ($request->boolean('send_immediately')) {
            $message = $this->queueOrFail($message);
        }

        return response()->json([
            'message' => 'Email message updated.',
            'data' => $message->refresh()->load(['recipients', 'attachments', 'senderProfile']),
        ]);
    }

    public function destroy(EmailMessage $message): JsonResponse
    {
        Gate::authorize('delete', $message);

        $paths = $message->attachments()->pluck('storage_path')->filter()->all();
        $message->delete();
        $this->attachmentService->deleteFiles($paths);

        return response()->json(['message' => 'Email message deleted.']);
    }

    public function send(EmailMessage $message): JsonResponse
    {
        Gate::authorize('send', $message);

        if (! $message->isSendable()) {
            return response()->json([
                'message' => "Cannot send message with status '{$message->status}'.",
            ], 422);
        }

        $message = $this->queueOrFail($message);

        return response()->json([
            'message' => 'Email queued for sending.',
            'data' => $message,
        ]);
    }

    public function retry(EmailMessage $message): JsonResponse
    {
        Gate::authorize('retry', $message);

        try {
            $message = $this->service->retry($message);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Email retry queued.',
            'data' => $message,
        ]);
    }

    /**
     * Batch actions on multiple email messages (mark read/unread, star, trash, move).
     */
    public function batch(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailMessage::class);

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string|max:26',
            'data' => 'required|array',
            'data.isRead' => 'nullable|boolean',
            'data.isStarred' => 'nullable|boolean',
            'data.isDeleted' => 'nullable|boolean',
            'data.folder' => 'nullable|string|in:inbox,sent,draft,scheduled,archived,trash,spam',
        ]);

        $ids = $validated['ids'];
        $updates = [];

        if (array_key_exists('isRead', $validated['data'])) {
            $updates['is_read'] = $validated['data']['isRead'];
        }
        if (array_key_exists('isStarred', $validated['data'])) {
            $updates['is_starred'] = $validated['data']['isStarred'];
        }
        if (array_key_exists('isDeleted', $validated['data'])) {
            $updates['is_deleted'] = $validated['data']['isDeleted'];
        }
        if (array_key_exists('folder', $validated['data'])) {
            $updates['folder'] = $validated['data']['folder'];
        }

        if (! empty($updates)) {
            $batchQuery = EmailMessage::whereIn('id', $ids);
            $this->accessControl->applyMessageScope($batchQuery, $request->user());
            $batchQuery->update($updates);
        }

        return response()->json([
            'message' => 'Emails updated successfully.',
            'count' => count($ids),
        ]);
    }

    private function queueOrFail(EmailMessage $message): EmailMessage
    {
        try {
            return $this->service->queue($message);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['message' => [$e->getMessage()]]);
        }
    }

    /**
     * Format an EmailMessage model into the structure consumed by Vuexy Email View & List.
     *
     * @return array<string, mixed>
     */
    private function formatForUi(EmailMessage $message): array
    {
        $sender = $message->senderProfile;
        $fromName = $sender?->name ?: ($sender?->display_name ?: 'System');
        $fromEmail = $sender?->email ?: 'system@waveplus.xyz';

        if ($message->direction === EmailMessage::DIRECTION_INCOMING) {
            $fromParticipant = $message->recipients->firstWhere('type', EmailRecipient::TYPE_FROM);
            $fromName = $message->sender_name ?: ($fromParticipant?->name ?: ($message->sender_email ?: ($fromParticipant?->email ?: 'Sender')));
            $fromEmail = $message->sender_email ?: ($fromParticipant?->email ?: 'incoming@waveplus.xyz');
        }

        $toRecipients = $message->recipients
            ->where('type', EmailRecipient::TYPE_TO)
            ->map(fn ($r) => [
                'name' => $r->name ?: $r->email,
                'email' => $r->email,
                'recipient_type' => $r->recipient_type,
                'recipient_id' => $r->recipient_id,
                'contact_id' => $r->contact_id,
                'email_source' => $r->email_source,
            ])
            ->values()
            ->all();

        $ccRecipients = $message->recipients
            ->where('type', EmailRecipient::TYPE_CC)
            ->map(fn ($r) => [
                'name' => $r->name ?: $r->email,
                'email' => $r->email,
                'recipient_type' => $r->recipient_type,
                'recipient_id' => $r->recipient_id,
                'contact_id' => $r->contact_id,
                'email_source' => $r->email_source,
            ])
            ->values()
            ->all();

        $bccRecipients = $message->recipients
            ->where('type', EmailRecipient::TYPE_BCC)
            ->map(fn ($r) => [
                'name' => $r->name ?: $r->email,
                'email' => $r->email,
                'recipient_type' => $r->recipient_type,
                'recipient_id' => $r->recipient_id,
                'contact_id' => $r->contact_id,
                'email_source' => $r->email_source,
            ])
            ->values()
            ->all();

        $attachments = $message->attachments->map(fn ($att) => [
            'id' => $att->id,
            'filename' => $att->filename,
            'fileName' => $att->filename,
            'size' => $att->size_bytes,
            'size_bytes' => $att->size_bytes,
            'formatted_size' => \App\Modules\Communications\Email\Services\EmailDocumentSearchService::formatBytes((int) $att->size_bytes),
            'mime_type' => $att->mime_type,
            'source' => $att->source,
            'source_type' => $att->source_type,
            'source_id' => $att->source_id,
            'downloadUrl' => '/api/communications/email/attachments/'.$att->id.'/download',
            'download_url' => '/api/communications/email/attachments/'.$att->id.'/download',
            'thumbnail' => '',
        ])->all();

        $links = [];
        $primaryContext = null;
        if ($message->conversation) {
            try {
                $user = auth()->user() ?? $message->senderUser ?? User::first();
                if ($user) {
                    $links = app(\App\Modules\Communications\Email\Services\EmailRecordLinkService::class)
                        ->getLinksForConversation($message->conversation, $user)
                        ->values()
                        ->all();

                    if (! empty($links)) {
                        $primary = $links[0];
                        $primaryContext = [
                            'type' => $primary['record_type'],
                            'record_id' => $primary['record_id'],
                            'reference' => $primary['record_reference'],
                        ];
                    }
                }
            } catch (\Throwable) {
                // Non-blocking
            }
        }

        return [
            'id' => $message->id,
            'from' => [
                'name' => $fromName,
                'email' => $fromEmail,
                'avatar' => '',
            ],
            'sender_profile_id' => $message->sender_profile_id,
            'template_id' => $message->template_id,
            'to' => $toRecipients,
            'cc' => $ccRecipients,
            'bcc' => $bccRecipients,
            'subject' => $message->subject,
            'message' => $message->body_html,
            'body_html' => $message->body_html,
            'body_text' => $message->body_text,
            'time' => ($message->sent_at ?: $message->created_at)->toIso8601String(),
            'folder' => $message->folder,
            'isRead' => (bool) $message->is_read,
            'isStarred' => (bool) $message->is_starred,
            'isDeleted' => (bool) $message->is_deleted,
            'labels' => [$message->direction === 'incoming' ? 'company' : 'personal'],
            'attachments' => $attachments,
            'associations' => $links,
            'primary_context' => $primaryContext,
            'conversationId' => $message->conversation_id,
            'status' => $message->status,
            'scheduledAt' => $message->scheduled_at?->toIso8601String(),
        ];
    }

    /**
     * Synchronize recipient-derived contact associations and explicit associations onto the message conversation.
     * Documents and attachments are strictly excluded from EmailRecordLink.
     */
    private function syncMessageAssociations(EmailMessage $message, Request $request): void
    {
        if (! $message->conversation) {
            return;
        }

        $linkService = app(\App\Modules\Communications\Email\Services\EmailRecordLinkService::class);
        $user = $request->user();

        // 1. Process explicit associations array (excluding any document records)
        if ($request->filled('associations')) {
            foreach ($request->input('associations') as $assoc) {
                if (empty($assoc['type']) || empty($assoc['id'])) {
                    continue;
                }
                // Never link document records to conversations
                if (in_array(strtolower((string) $assoc['type']), ['bp_document', 'document'], true)) {
                    continue;
                }
                try {
                    $linkService->linkRecord(
                        $message->conversation,
                        (string) $assoc['type'],
                        (string) $assoc['id'],
                        $user,
                        'manual',
                        true,
                        1.00,
                        'Linked recipient contact',
                        $message->id
                    );
                } catch (\Throwable) {
                    // Non-blocking for unresolvable or unauthorized record links
                }
            }
        }

        // 2. Automatically link contacts from all recipients
        foreach ($message->recipients as $recipient) {
            if ($recipient->type === EmailRecipient::TYPE_FROM) {
                continue;
            }

            $rType = strtolower((string) ($recipient->recipient_type ?? ''));
            $rId = (string) ($recipient->recipient_id ?? '');
            $contactId = (string) ($recipient->contact_id ?? '');

            // 2a. Business Partners (Client, Supplier, Organisation, Service Provider)
            if (in_array($rType, ['client', 'supplier', 'organisation', 'organization', 'service_provider', 'business_partner'], true) && $rId !== '') {
                $source = $recipient->email_source ? " ({$recipient->email_source})" : '';
                try {
                    $linkService->linkRecord(
                        $message->conversation,
                        $rType,
                        $rId,
                        $user,
                        'contact',
                        true,
                        1.00,
                        "Associated Business Partner{$source}",
                        $message->id
                    );
                } catch (\Throwable) {
                    // Non-blocking
                }

                // If specific BP contact, also associate the specific BpContact
                if ($contactId !== '' && $recipient->email_source === 'contact') {
                    try {
                        $linkService->linkRecord(
                            $message->conversation,
                            'bp_contact',
                            $contactId,
                            $user,
                            'contact',
                            true,
                            1.00,
                            'Associated BP Contact',
                            $message->id
                        );
                    } catch (\Throwable) {
                        // Non-blocking
                    }
                }
            }

            // 2b. Employees
            if ($rType === 'employee' && $rId !== '') {
                try {
                    $linkService->linkRecord(
                        $message->conversation,
                        'employee',
                        $rId,
                        $user,
                        'contact',
                        true,
                        1.00,
                        'Associated Employee',
                        $message->id
                    );
                } catch (\Throwable) {
                    // Non-blocking
                }
            }

            // 2c. Manual recipient through Others -> never create artificial BP or Employee association
        }

        // 3. Process primary business context if specified (excluding document records)
        $pType = $request->input('primary_context_type');
        $pId = $request->input('primary_context_id');
        if ($pType && $pId && $pType !== 'none' && ! in_array(strtolower((string) $pType), ['bp_document', 'document'], true)) {
            try {
                $linkService->linkRecord(
                    $message->conversation,
                    (string) $pType,
                    (string) $pId,
                    $user,
                    'manual',
                    true,
                    1.00,
                    'Primary business context',
                    $message->id
                );
            } catch (\Throwable) {
                // Non-blocking
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes|' : '';

        return [
            'to' => $req.'required|array|min:1|max:50',
            'to.*.email' => 'required|email:rfc|max:150',
            'to.*.name' => 'nullable|string|max:150',
            'to.*.recipient_type' => 'nullable|string|max:50',
            'to.*.recipient_id' => 'nullable|string|max:36',
            'to.*.contact_id' => 'nullable|string|max:36',
            'to.*.email_source' => 'nullable|string|max:30',
            'cc' => 'nullable|array|max:50',
            'cc.*.email' => 'required|email:rfc|max:150',
            'cc.*.name' => 'nullable|string|max:150',
            'cc.*.recipient_type' => 'nullable|string|max:50',
            'cc.*.recipient_id' => 'nullable|string|max:36',
            'cc.*.contact_id' => 'nullable|string|max:36',
            'cc.*.email_source' => 'nullable|string|max:30',
            'bcc' => 'nullable|array|max:50',
            'bcc.*.email' => 'required|email:rfc|max:150',
            'bcc.*.name' => 'nullable|string|max:150',
            'bcc.*.recipient_type' => 'nullable|string|max:50',
            'bcc.*.recipient_id' => 'nullable|string|max:36',
            'bcc.*.contact_id' => 'nullable|string|max:36',
            'bcc.*.email_source' => 'nullable|string|max:30',
            'subject' => $req.'required|string|max:255',
            'body_html' => $req.'required|string|max:500000',
            'body_text' => 'nullable|string|max:500000',
            'sender_profile_id' => 'nullable|integer',
            'template_id' => 'nullable|integer|exists:email_templates,id',
            'conversation_id' => 'nullable|string|max:26',
            'mailbox_id' => 'nullable|integer|exists:email_mailboxes,id',
            'in_reply_to' => 'nullable|string|max:255',
            'references' => 'nullable|string|max:1000',
            'scheduled_at' => 'nullable|date',
            'send_immediately' => 'boolean',
            'attachments' => 'nullable|array|max:'.EmailAttachmentService::MAX_FILES,
            'attachments.*' => 'file|max:'.(EmailAttachmentService::MAX_FILE_BYTES / 1024).'|extensions:'.implode(',', EmailAttachmentService::ALLOWED_EXTENSIONS),
            'document_attachments' => 'nullable|array|max:'.EmailAttachmentService::MAX_FILES,
            'document_attachments.*.source' => 'required|string|in:bp_document',
            'document_attachments.*.id' => 'required|string|max:36',
            'associations' => 'nullable|array|max:20',
            'associations.*.type' => 'required|string|max:50',
            'associations.*.id' => 'required|string|max:36',
            'primary_context_type' => 'nullable|string|max:50',
            'primary_context_id' => 'nullable|string|max:36',
        ];
    }
}
