<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Services\BusinessPartnerContextualEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessPartnerEmailController extends Controller
{
    public function __construct(
        private readonly BusinessPartnerContextualEmailService $contextualEmailService
    ) {}

    /**
     * Get initial pre-populated data for contextual quick send dialog.
     */
    public function prepare(Request $request, string $id, ?string $partnerType = null): JsonResponse
    {
        $language = $request->query('language', app()->getLocale() ?: 'fr');
        $resolvedType = $partnerType ?: ($request->route('partnerType') ?: 'client');

        $data = $this->contextualEmailService->getPreparationData(
            user: $request->user(),
            partnerType: $resolvedType,
            id: $id,
            language: $language
        );

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Send contextual email for the business partner.
     */
    public function send(Request $request, string $id, ?string $partnerType = null): JsonResponse
    {
        $resolvedType = $partnerType ?: ($request->route('partnerType') ?: 'client');

        $validated = $request->validate([
            'sender_profile_id' => ['nullable', 'integer'],
            'to' => ['required', 'array', 'min:1'],
            'to.*.email' => ['required', 'email'],
            'to.*.name' => ['nullable', 'string'],
            'to.*.recipient_type' => ['nullable', 'string'],
            'to.*.recipient_id' => ['nullable', 'string'],
            'to.*.contact_id' => ['nullable', 'string'],
            'to.*.email_source' => ['nullable', 'string'],
            'cc' => ['nullable', 'array'],
            'cc.*.email' => ['required', 'email'],
            'cc.*.name' => ['nullable', 'string'],
            'bcc' => ['nullable', 'array'],
            'bcc.*.email' => ['required', 'email'],
            'bcc.*.name' => ['nullable', 'string'],
            'subject' => ['required', 'string', 'max:500'],
            'body_html' => ['required', 'string'],
            'body_text' => ['nullable', 'string'],
            'include_pdf' => ['nullable', 'boolean'],
        ]);

        $message = $this->contextualEmailService->send(
            user: $request->user(),
            partnerType: $resolvedType,
            id: $id,
            data: $validated
        );

        return response()->json([
            'message' => 'Email queued for sending successfully.',
            'data' => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'subject' => $message->subject,
                'status' => $message->status,
                'sent_at' => $message->sent_at,
                'has_attachments' => $message->has_attachments,
            ],
        ], 201);
    }

    /**
     * Preview bulk contextual emails (pre-flight check).
     */
    public function bulkPreview(Request $request, ?string $partnerType = null): JsonResponse
    {
        $resolvedType = $partnerType ?: ($request->route('partnerType') ?: 'client');

        $validated = $request->validate([
            'partner_ids' => ['required', 'array', 'min:1'],
            'partner_ids.*' => ['required', 'string'],
            'recipient_rule' => ['nullable', 'string', 'in:primary_email_first,primary_contact_first,partner_email_only,contacts_only'],
            'language' => ['nullable', 'string', 'in:fr,en,ar'],
            'sender_profile_id' => ['nullable', 'integer'],
        ]);

        $data = $this->contextualEmailService->prepareBulkPreview(
            user: $request->user(),
            partnerType: $resolvedType,
            partnerIds: $validated['partner_ids'],
            rule: $validated['recipient_rule'] ?? \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::RULE_PRIMARY_EMAIL_FIRST,
            language: $validated['language'] ?? 'fr',
            senderProfileId: $validated['sender_profile_id'] ?? null
        );

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Launch bulk contextual email batch.
     */
    public function bulkSend(Request $request, ?string $partnerType = null): JsonResponse
    {
        $resolvedType = $partnerType ?: ($request->route('partnerType') ?: 'client');

        $validated = $request->validate([
            'partner_ids' => ['required', 'array', 'min:1', 'max:250'],
            'partner_ids.*' => ['required', 'string'],
            'recipient_rule' => ['nullable', 'string', 'in:primary_email_first,primary_contact_first,partner_email_only,contacts_only'],
            'language' => ['nullable', 'string', 'in:fr,en,ar'],
            'sender_profile_id' => ['nullable', 'integer'],
            'include_pdf' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        $batch = $this->contextualEmailService->createBulkBatch(
            user: $request->user(),
            partnerType: $resolvedType,
            partnerIds: $validated['partner_ids'],
            options: $validated
        );

        return response()->json([
            'message' => 'Bulk email batch initiated successfully.',
            'data' => $batch,
        ], 201);
    }

    /**
     * Get batch status and progress.
     */
    public function showBatch(Request $request, string $batchId): JsonResponse
    {
        $batch = \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::with(['items', 'senderProfile', 'template'])
            ->findOrFail($batchId);

        return response()->json([
            'data' => $batch,
        ]);
    }

    /**
     * List bulk email batches history.
     */
    public function listBatches(Request $request): JsonResponse
    {
        $query = \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::with(['senderProfile'])
            ->latest('id');

        if ($request->filled('partner_type')) {
            $query->where('partner_type', $request->query('partner_type'));
        }

        $batches = $query->paginate($request->integer('per_page', 15));

        return response()->json($batches);
    }
}
