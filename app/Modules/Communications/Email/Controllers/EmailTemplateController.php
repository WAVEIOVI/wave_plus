<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\Email\Models\EmailTemplate;
use App\Modules\Communications\Email\Services\EmailBusinessContextService;
use App\Modules\Communications\Email\Services\EmailSignatureService;
use App\Modules\Communications\Email\Services\EmailTemplateRendererService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EmailTemplateController extends Controller
{
    public function __construct(
        private readonly EmailTemplateRendererService $rendererService,
        private readonly EmailSignatureService $signatureService,
        private readonly EmailBusinessContextService $contextService,
    ) {}

    /**
     * List system-managed email templates.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EmailTemplate::class);

        $templates = QueryBuilder::for(EmailTemplate::class)
            ->allowedFilters([
                AllowedFilter::exact('category'),
                AllowedFilter::exact('language'),
                AllowedFilter::partial('name'),
            ])
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('language')
            ->get();

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Search context records (e.g. Quotations, Invoices, Purchase Orders)
     * for the Primary Business Context selector.
     */
    public function contextRecords(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'query' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $records = $this->contextService->searchContextRecords(
            $request->user(),
            $validated['type'],
            $validated['query'] ?? '',
            (int) ($validated['limit'] ?? 15)
        );

        return response()->json([
            'data' => $records,
        ]);
    }

    /**
     * Get dynamic variable catalog and resolved context values if record specified.
     */
    public function variables(Request $request): JsonResponse
    {
        $recordType = $request->query('record_type');
        $recordId = $request->query('record_id');

        $resolvedVariables = [];
        $summary = null;

        if ($recordType && $recordId) {
            $resolved = $this->contextService->resolveContext(
                $request->user(),
                $recordType,
                $recordId
            );
            $resolvedVariables = $resolved['variables'];
            $summary = $resolved['summary'];
        }

        return response()->json([
            'data' => [
                'catalog' => $this->contextService->getVariableCatalog($recordType),
                'resolved' => $resolvedVariables,
                'summary' => $summary,
            ],
        ]);
    }

    /**
     * Show single email template.
     */
    public function show(EmailTemplate $template): JsonResponse
    {
        Gate::authorize('view', $template);

        return response()->json([
            'data' => $template,
        ]);
    }

    /**
     * Render a preview of the template with context variables and signature.
     * The original template is strictly unchanged.
     */
    public function render(Request $request, EmailTemplate $template): JsonResponse
    {
        Gate::authorize('view', $template);

        $validated = $request->validate([
            'record_type' => 'nullable|string',
            'record_id' => 'nullable|string',
            'recipient' => 'nullable|array',
            'sender_profile_id' => 'nullable|integer',
            'signature_type' => 'nullable|string|in:personal,company,none',
            'context' => 'nullable|array',
        ]);

        $signatureType = $validated['signature_type'] ?? EmailSignatureService::TYPE_PERSONAL;
        $recordType = $validated['record_type'] ?? null;
        $recordId = $validated['record_id'] ?? null;
        $recipient = $validated['recipient'] ?? ($validated['context']['recipient'] ?? []);
        $senderProfileId = ! empty($validated['sender_profile_id']) ? (int) $validated['sender_profile_id'] : null;

        // Resolve business context securely from backend models
        $resolvedContextData = $this->contextService->resolveContext(
            $request->user(),
            $recordType,
            $recordId,
            $recipient,
            $senderProfileId
        );

        // Merge any user-provided context overrides using array_replace_recursive
        $mergedContext = array_replace_recursive(
            $resolvedContextData['context'],
            $validated['context'] ?? []
        );

        $preview = $this->rendererService->renderPreview(
            $template,
            $mergedContext,
            $signatureType,
            $request->user()
        );

        $missingVariables = $preview['variables']['missing'] ?? [];
        $requiresRecordContext = (bool) $template->requires_record_context;
        $hasMissingRecordContext = $requiresRecordContext && empty($recordId) && empty($validated['context']['document']);

        return response()->json([
            'data' => $preview,
            'summary' => $resolvedContextData['summary'],
            'resolved_variables' => $resolvedContextData['variables'],
            'missing_variables' => $missingVariables,
            'requires_record_context' => $requiresRecordContext,
            'has_missing_record_context' => $hasMissingRecordContext,
        ]);
    }

    /**
     * Get signature choices available for the authenticated user.
     */
    public function signatures(Request $request): JsonResponse
    {
        $signatures = $this->signatureService->getSignaturesFor($request->user());

        return response()->json([
            'data' => $signatures,
        ]);
    }
}
