<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Services;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpContact;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\ServiceProvider;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Communications\Email\Jobs\SendEmailMessageJob;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailRecipient;
use App\Modules\Communications\Email\Models\EmailTemplate;
use App\Modules\Communications\Email\Services\EmailAttachmentService;
use App\Modules\Communications\Email\Services\EmailBusinessContextService;
use App\Modules\Communications\Email\Services\EmailRecordLinkService;
use App\Modules\Communications\Email\Services\EmailTemplateRendererService;
use App\Modules\Communications\Email\Services\SenderProfileService;
use App\Modules\Shared\Services\DocumentRenderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BusinessPartnerContextualEmailService
{
    public function __construct(
        private readonly DocumentRenderService $documentRender,
        private readonly EmailTemplateRendererService $templateRenderer,
        private readonly EmailBusinessContextService $contextService,
        private readonly SenderProfileService $senderProfileService,
        private readonly EmailRecordLinkService $recordLinkService,
        private readonly EmailAttachmentService $attachmentService,
    ) {}

    /**
     * Resolve the normalized partner type ('client', 'supplier', 'organisation', 'service_provider').
     */
    public function normalizePartnerType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'client', 'clients' => 'client',
            'supplier', 'suppliers' => 'supplier',
            'organisation', 'organisations', 'organization', 'organizations' => 'organisation',
            'service_provider', 'service-providers', 'service_providers', 'serviceprovider' => 'service_provider',
            default => throw new \InvalidArgumentException("Unsupported business partner type [{$type}]."),
        };
    }

    /**
     * Resolve partner Eloquent model class.
     */
    public function resolveModelClass(string $normalizedType): string
    {
        return match ($normalizedType) {
            'client' => Client::class,
            'supplier' => Supplier::class,
            'organisation' => Organisation::class,
            'service_provider' => ServiceProvider::class,
            default => throw new \InvalidArgumentException("Unsupported partner type [{$normalizedType}]."),
        };
    }

    /**
     * Find partner record with full relationships.
     */
    public function findPartner(string $normalizedType, string|int $id): Model
    {
        $class = $this->resolveModelClass($normalizedType);

        $record = $class::with([
            'accountManager',
            'addresses',
            'contacts',
            'bankAccounts',
            'vatCertificates',
            'documents',
        ])->find($id);

        if (! $record) {
            throw new NotFoundHttpException("Business partner of type [{$normalizedType}] with ID [{$id}] not found.");
        }

        return $record;
    }

    /**
     * Check user permissions for partner view and email creation.
     */
    public function authorizeUser(User $user, Model $record): void
    {
        Gate::forUser($user)->authorize('view', $record);

        if ($user->hasRole('Super Admin')) {
            return;
        }

        $canEmail = $user->can('create-email-messages')
            || $user->can('create', EmailMessage::class);

        if (! $canEmail) {
            abort(403, 'You are not authorized to compose or send emails.');
        }
    }

    /**
     * Prepare initial contextual email payload for quick single-screen composer.
     *
     * @return array<string, mixed>
     */
    public function getPreparationData(User $user, string $partnerType, string|int $id, ?string $language = 'fr'): array
    {
        $normalizedType = $this->normalizePartnerType($partnerType);
        $record = $this->findPartner($normalizedType, $id);

        $this->authorizeUser($user, $record);

        // 1. Authorized sender identities
        $authorizedProfiles = $this->senderProfileService->getAuthorizedProfiles($user);
        $defaultProfile = $authorizedProfiles->firstWhere('is_default', true) ?? $authorizedProfiles->first();

        // 2. Partner identification details
        $legalName = $this->formatTranslatable($record->legal_name);
        $commercialName = $this->formatTranslatable($record->commercial_name);
        $partnerName = $legalName ?: ($commercialName ?: "Partner #{$record->id}");
        $partnerCode = match ($normalizedType) {
            'client' => $record->client_id ?? (string) $record->id,
            'supplier' => $record->supplier_id ?? (string) $record->id,
            'organisation' => $record->organisation_id ?? (string) $record->id,
            'service_provider' => $record->service_provider_id ?? (string) $record->id,
        };

        // 3. Available BP email options
        $recipientsList = [];

        // Main / General email
        if (! empty($record->primary_email)) {
            $recipientsList[] = [
                'id' => "general-{$record->id}",
                'label' => "{$partnerName} (Principal) — {$record->primary_email}",
                'email' => $record->primary_email,
                'name' => $partnerName,
                'entity_name' => $partnerName,
                'recipient_type' => $normalizedType,
                'recipient_id' => (string) $record->id,
                'contact_id' => null,
                'email_source' => 'general',
            ];
        }

        // Contact emails
        if ($record->relationLoaded('contacts') || $record->contacts) {
            foreach ($record->contacts as $contact) {
                if (empty($contact->email)) {
                    continue;
                }
                $contactFirst = $this->formatTranslatable($contact->first_name);
                $contactLast = $this->formatTranslatable($contact->last_name);
                $contactName = trim("{$contactFirst} {$contactLast}") ?: "Contact #{$contact->id}";
                $position = $this->formatTranslatable($contact->position) ?: 'Contact';

                $recipientsList[] = [
                    'id' => "contact-{$contact->id}",
                    'label' => "{$contactName} ({$position}) — {$contact->email}",
                    'email' => $contact->email,
                    'name' => "{$contactName} ({$partnerName})",
                    'contact_name' => $contactName,
                    'entity_name' => $partnerName,
                    'recipient_type' => $normalizedType,
                    'recipient_id' => (string) $record->id,
                    'contact_id' => (string) $contact->id,
                    'email_source' => 'contact',
                ];
            }
        }

        // Address emails
        if ($record->relationLoaded('addresses') || $record->addresses) {
            foreach ($record->addresses as $address) {
                if (empty($address->email)) {
                    continue;
                }
                $addrTitle = $this->formatTranslatable($address->title) ?: ($address->city ?? 'Filiale');

                $recipientsList[] = [
                    'id' => "address-{$address->id}",
                    'label' => "{$partnerName} [{$addrTitle}] — {$address->email}",
                    'email' => $address->email,
                    'name' => "{$partnerName} ({$addrTitle})",
                    'entity_name' => $partnerName,
                    'address_label' => $addrTitle,
                    'recipient_type' => $normalizedType,
                    'recipient_id' => (string) $record->id,
                    'contact_id' => null,
                    'email_source' => 'address',
                ];
            }
        }

        $selectedRecipient = $recipientsList[0] ?? [
            'id' => 'custom',
            'label' => $record->primary_email ?: 'Ajouter un destinataire',
            'email' => $record->primary_email ?: '',
            'name' => $partnerName,
            'entity_name' => $partnerName,
            'recipient_type' => $normalizedType,
            'recipient_id' => (string) $record->id,
            'contact_id' => null,
            'email_source' => 'general',
        ];

        // 4. Appropriate Predefined Template
        $lang = in_array($language, ['fr', 'en', 'ar'], true) ? $language : 'fr';
        $category = match ($normalizedType) {
            'client' => EmailTemplate::CATEGORY_CLIENT_PROFILE,
            'supplier' => EmailTemplate::CATEGORY_SUPPLIER_PROFILE,
            'organisation' => EmailTemplate::CATEGORY_ORGANISATION_PROFILE,
            'service_provider' => EmailTemplate::CATEGORY_SERVICE_PROVIDER_PROFILE,
        };

        $template = EmailTemplate::query()
            ->where('category', $category)
            ->where('language', $lang)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            $template = EmailTemplate::query()
                ->where('category', EmailTemplate::CATEGORY_GENERAL_COMMUNICATION)
                ->where('language', $lang)
                ->where('is_active', true)
                ->first();
        }

        // 5. Pre-resolve template subject and message
        $renderedSubject = '';
        $renderedBodyHtml = '';
        $renderedBodyText = '';

        if ($template) {
            $resolvedContext = $this->contextService->resolveContext(
                user: $user,
                recordType: $normalizedType,
                recordId: $record->id,
                recipient: [
                    'name' => $selectedRecipient['contact_name'] ?? $selectedRecipient['name'],
                    'email' => $selectedRecipient['email'],
                    'company' => $partnerName,
                ],
                senderProfileId: $defaultProfile?->id
            );

            $preview = $this->templateRenderer->renderPreview(
                template: $template,
                context: $resolvedContext['context'],
                signatureType: 'personal',
                user: $user
            );

            $renderedSubject = $preview['subject'];
            $renderedBodyHtml = $preview['body_html'];
            $renderedBodyText = $preview['body_text'];
        }

        // 6. PDF attachment metadata
        $pdfFilename = $this->resolvePdfFilename($normalizedType, $partnerCode);

        return [
            'partner' => [
                'id' => (string) $record->id,
                'type' => $normalizedType,
                'name' => $partnerName,
                'code' => $partnerCode,
                'primary_email' => $record->primary_email ?? '',
            ],
            'sender_profiles' => $authorizedProfiles,
            'default_sender_profile_id' => $defaultProfile?->id,
            'recipients_list' => $recipientsList,
            'selected_recipient' => $selectedRecipient,
            'template' => $template ? [
                'id' => $template->id,
                'category' => $template->category,
                'name' => $template->name,
                'language' => $template->language,
            ] : null,
            'subject' => $renderedSubject,
            'body_html' => $renderedBodyHtml,
            'body_text' => $renderedBodyText,
            'pdf_attachment' => [
                'filename' => $pdfFilename,
                'included' => true,
                'source' => 'generated_partner_pdf',
            ],
        ];
    }

    /**
     * Send contextual email for the business partner with private PDF snapshot.
     */
    public function send(User $user, string $partnerType, string|int $id, array $data): EmailMessage
    {
        $normalizedType = $this->normalizePartnerType($partnerType);
        $record = $this->findPartner($normalizedType, $id);

        $this->authorizeUser($user, $record);

        // 1. Resolve authorized sender profile
        $senderProfileId = ! empty($data['sender_profile_id']) ? (int) $data['sender_profile_id'] : null;
        $senderProfile = $this->senderProfileService->resolveAuthorizedProfile($user, $senderProfileId);

        // 2. Validate recipients
        $rawTo = $data['to'] ?? [];
        if (empty($rawTo)) {
            throw ValidationException::withMessages([
                'to' => ['At least one recipient is required.'],
            ]);
        }

        $toRecipients = is_array($rawTo) ? $rawTo : [['email' => (string) $rawTo]];
        $ccRecipients = is_array($data['cc'] ?? null) ? $data['cc'] : [];
        $bccRecipients = is_array($data['bcc'] ?? null) ? $data['bcc'] : [];

        // 3. Validate subject and body (ensure no unresolved placeholders)
        $subject = trim((string) ($data['subject'] ?? ''));
        if ($subject === '') {
            throw ValidationException::withMessages([
                'subject' => ['Subject cannot be empty.'],
            ]);
        }

        $bodyHtml = trim((string) ($data['body_html'] ?? ''));
        if ($bodyHtml === '') {
            throw ValidationException::withMessages([
                'body_html' => ['Message body cannot be empty.'],
            ]);
        }

        if (preg_match('/\{\{\s*[\w\.\-]+\s*\}\}/', $subject) || preg_match('/\{\{\s*[\w\.\-]+\s*\}\}/', $bodyHtml)) {
            throw ValidationException::withMessages([
                'body_html' => ['Please resolve or remove all template placeholders before sending.'],
            ]);
        }

        $bodyText = ! empty($data['body_text']) ? trim((string) $data['body_text']) : strip_tags($bodyHtml);

        // 4. Generate PDF bytes if requested
        $includePdf = filter_var($data['include_pdf'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $pdfBytes = null;
        $partnerCode = match ($normalizedType) {
            'client' => $record->client_id ?? (string) $record->id,
            'supplier' => $record->supplier_id ?? (string) $record->id,
            'organisation' => $record->organisation_id ?? (string) $record->id,
            'service_provider' => $record->service_provider_id ?? (string) $record->id,
        };
        $pdfFilename = $this->resolvePdfFilename($normalizedType, $partnerCode);

        if ($includePdf) {
            $pdfBytes = $this->generatePartnerPdf($normalizedType, $record);
        }

        // 5. Create EmailConversation
        $conversation = EmailConversation::create([
            'subject' => $subject,
            'status' => EmailConversation::STATUS_CLOSED,
            'folder' => EmailMessage::FOLDER_SENT,
            'last_message_at' => now(),
            'message_count' => 1,
        ]);

        // 6. Create EmailMessage
        $message = EmailMessage::create([
            'conversation_id' => $conversation->id,
            'sender_profile_id' => $senderProfile->id,
            'sender_email' => $senderProfile->email,
            'sender_name' => $senderProfile->name,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'status' => EmailMessage::STATUS_QUEUED,
            'folder' => EmailMessage::FOLDER_SENT,
            'idempotency_key' => (string) Str::uuid(),
            'has_attachments' => $includePdf && ! empty($pdfBytes),
            'sent_by_user_id' => $user->id,
        ]);

        // 7. Store PDF snapshot as private EmailAttachment (Never an EmailRecordLink)
        if ($includePdf && ! empty($pdfBytes)) {
            $this->attachmentService->storeRawBytes($message, $pdfBytes, $pdfFilename, 'application/pdf');
        }

        // 8. Store recipients
        $this->storeRecipients($message, EmailRecipient::TYPE_TO, $toRecipients);
        $this->storeRecipients($message, EmailRecipient::TYPE_CC, $ccRecipients);
        $this->storeRecipients($message, EmailRecipient::TYPE_BCC, $bccRecipients);

        // 9. Associate conversation with Business Partner and specific Contact (never manual or documents)
        $this->recordLinkService->linkRecord(
            conversation: $conversation,
            recordType: $normalizedType,
            recordId: (string) $record->id,
            user: $user,
            linkType: 'primary',
            isConfirmed: true,
            confidence: 1.0,
            reason: "Contextual email sent from {$normalizedType} view dialog",
            messageId: $message->id
        );

        // Check if any recipient has a contact_id
        foreach ($toRecipients as $rec) {
            $contactId = $rec['contact_id'] ?? null;
            if ($contactId) {
                $contact = BpContact::find($contactId);
                if ($contact && (string) $contact->contactable_id === (string) $record->id) {
                    $this->recordLinkService->linkRecord(
                        conversation: $conversation,
                        recordType: 'bp_contact',
                        recordId: (string) $contact->id,
                        user: $user,
                        linkType: 'contact',
                        isConfirmed: true,
                        confidence: 1.0,
                        reason: 'Recipient selected from partner contacts',
                        messageId: $message->id
                    );
                }
            }
        }

        // 10. Queue the sending job
        SendEmailMessageJob::dispatch($message->id);

        return $message->refresh();
    }

    /**
     * Generate partner record PDF bytes using existing Blade views and DocumentRenderService.
     */
    public function generatePartnerPdf(string $normalizedType, Model $record): string
    {
        $html = match ($normalizedType) {
            'client' => view('pdf.clients.client-record', ['client' => $record])->render(),
            'supplier' => view('pdf.suppliers.supplier-record', ['supplier' => $record])->render(),
            'organisation' => view('pdf.organisations.organisation-record', ['organisation' => $record])->render(),
            'service_provider' => view('pdf.service-providers.service-provider-record', ['serviceProvider' => $record])->render(),
            default => throw new \InvalidArgumentException("Unsupported partner type [{$normalizedType}]."),
        };

        return $this->documentRender->renderPdf($html, 'portrait');
    }

    /**
     * Resolve recipient for a business partner based on rule policy.
     *
     * @return array{email: string, name: string, contact_id: ?string, email_source: string}|null
     */
    public function resolveRecipientForRule(Model $record, string $normalizedType, string $rule): ?array
    {
        $partnerName = $this->resolvePartnerName($record);
        $primaryEmail = ! empty($record->primary_email) ? trim($record->primary_email) : null;

        // Collect valid contact
        $validContact = null;
        if ($record->relationLoaded('contacts') || $record->contacts) {
            $contacts = $record->contacts;
            $validContact = $contacts->first(fn ($c) => ! empty($c->email) && ($c->is_primary ?? false))
                ?? $contacts->first(fn ($c) => ! empty($c->email));
        }

        $contactData = null;
        if ($validContact) {
            $cFirst = $this->resolveModelTranslatable($validContact, 'first_name');
            $cLast = $this->resolveModelTranslatable($validContact, 'last_name');
            $cName = trim("{$cFirst} {$cLast}") ?: "Contact #{$validContact->id}";
            $contactData = [
                'email' => trim($validContact->email),
                'name' => "{$cName} ({$partnerName})",
                'contact_id' => (string) $validContact->id,
                'email_source' => 'contact',
            ];
        }

        $generalData = $primaryEmail ? [
            'email' => $primaryEmail,
            'name' => $partnerName,
            'contact_id' => null,
            'email_source' => 'general',
        ] : null;

        return match ($rule) {
            \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::RULE_PRIMARY_CONTACT_FIRST => $contactData ?? $generalData,
            \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::RULE_PARTNER_EMAIL_ONLY => $generalData,
            \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::RULE_CONTACTS_ONLY => $contactData,
            default => $generalData ?? $contactData, // RULE_PRIMARY_EMAIL_FIRST
        };
    }

    /**
     * Pre-flight preview calculation for bulk contextual emails.
     *
     * @param  array<int, string|int>  $partnerIds
     * @return array<string, mixed>
     */
    public function prepareBulkPreview(
        User $user,
        string $partnerType,
        array $partnerIds,
        string $rule = \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::RULE_PRIMARY_EMAIL_FIRST,
        ?string $language = 'fr',
        ?int $senderProfileId = null
    ): array {
        $normalizedType = $this->normalizePartnerType($partnerType);

        if (! $user->hasRole('Super Admin') && ! $user->can('create-email-messages')) {
            abort(403, 'You are not authorized to compose or send emails.');
        }

        $modelClass = $this->resolveModelClass($normalizedType);
        Gate::forUser($user)->authorize('viewAny', $modelClass);

        $authorizedProfiles = $this->senderProfileService->getAuthorizedProfiles($user);
        $selectedProfile = $senderProfileId
            ? $this->senderProfileService->resolveAuthorizedProfile($user, $senderProfileId)
            : ($authorizedProfiles->firstWhere('is_default', true) ?? $authorizedProfiles->first());

        $modelClass = $this->resolveModelClass($normalizedType);
        $records = $modelClass::with(['contacts'])->whereIn('id', $partnerIds)->get()->keyBy('id');

        $lang = in_array($language, ['fr', 'en', 'ar'], true) ? $language : 'fr';
        $category = match ($normalizedType) {
            'client' => EmailTemplate::CATEGORY_CLIENT_PROFILE,
            'supplier' => EmailTemplate::CATEGORY_SUPPLIER_PROFILE,
            'organisation' => EmailTemplate::CATEGORY_ORGANISATION_PROFILE,
            'service_provider' => EmailTemplate::CATEGORY_SERVICE_PROVIDER_PROFILE,
        };

        $template = EmailTemplate::query()
            ->where('category', $category)
            ->where('language', $lang)
            ->where('is_active', true)
            ->first() ?? EmailTemplate::query()
            ->where('category', EmailTemplate::CATEGORY_GENERAL_COMMUNICATION)
            ->where('language', $lang)
            ->where('is_active', true)
            ->first();

        $items = [];
        $readyCount = 0;
        $skippedCount = 0;
        $samplePreview = null;

        foreach ($partnerIds as $id) {
            $record = $records->get($id);
            if (! $record) {
                $items[] = [
                    'partner_id' => (string) $id,
                    'partner_code' => "ID #{$id}",
                    'partner_name' => "Record #{$id}",
                    'status' => \App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem::STATUS_SKIPPED,
                    'skip_reason' => 'not_found',
                    'warning' => 'Enregistrement introuvable.',
                    'recipient_email' => null,
                    'recipient_name' => null,
                    'contact_id' => null,
                    'email_source' => null,
                ];
                $skippedCount++;

                continue;
            }

            // Check authorization
            try {
                $this->authorizeUser($user, $record);
            } catch (\Throwable $e) {
                $items[] = [
                    'partner_id' => (string) $record->id,
                    'partner_code' => $this->resolvePartnerCode($normalizedType, $record),
                    'partner_name' => $this->resolvePartnerName($record),
                    'status' => \App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem::STATUS_SKIPPED,
                    'skip_reason' => \App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem::SKIP_REASON_UNAUTHORIZED,
                    'warning' => 'Accès non autorisé à cet enregistrement.',
                    'recipient_email' => null,
                    'recipient_name' => null,
                    'contact_id' => null,
                    'email_source' => null,
                ];
                $skippedCount++;

                continue;
            }

            $partnerName = $this->resolvePartnerName($record);
            $partnerCode = $this->resolvePartnerCode($normalizedType, $record);
            $recipient = $this->resolveRecipientForRule($record, $normalizedType, $rule);

            if (! $recipient || empty($recipient['email'])) {
                $items[] = [
                    'partner_id' => (string) $record->id,
                    'partner_code' => $partnerCode,
                    'partner_name' => $partnerName,
                    'status' => \App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem::STATUS_SKIPPED,
                    'skip_reason' => \App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem::SKIP_REASON_NO_EMAIL,
                    'warning' => 'Aucune adresse email configurée pour ce partenaire ou ses contacts.',
                    'recipient_email' => null,
                    'recipient_name' => null,
                    'contact_id' => null,
                    'email_source' => null,
                ];
                $skippedCount++;

                continue;
            }

            $items[] = [
                'partner_id' => (string) $record->id,
                'partner_code' => $partnerCode,
                'partner_name' => $partnerName,
                'status' => 'ready',
                'skip_reason' => null,
                'warning' => null,
                'recipient_email' => $recipient['email'],
                'recipient_name' => $recipient['name'],
                'contact_id' => $recipient['contact_id'],
                'email_source' => $recipient['email_source'],
            ];
            $readyCount++;

            // Sample preview for first ready item
            if (! $samplePreview && $template) {
                $resolvedContext = $this->contextService->resolveContext(
                    user: $user,
                    recordType: $normalizedType,
                    recordId: $record->id,
                    recipient: [
                        'name' => $recipient['name'],
                        'email' => $recipient['email'],
                        'company' => $partnerName,
                    ],
                    senderProfileId: $selectedProfile?->id
                );

                $preview = $this->templateRenderer->renderPreview(
                    template: $template,
                    context: $resolvedContext['context'],
                    signatureType: 'personal',
                    user: $user
                );

                $samplePreview = [
                    'partner_name' => $partnerName,
                    'partner_code' => $partnerCode,
                    'subject' => $preview['subject'],
                    'body_html' => $preview['body_html'],
                    'body_text' => $preview['body_text'],
                ];
            }
        }

        return [
            'partner_type' => $normalizedType,
            'total_count' => count($partnerIds),
            'ready_count' => $readyCount,
            'skipped_count' => $skippedCount,
            'recipient_rule' => $rule,
            'language' => $lang,
            'sender_profiles' => $authorizedProfiles,
            'default_sender_profile_id' => $selectedProfile?->id,
            'template' => $template ? [
                'id' => $template->id,
                'name' => $template->name,
                'language' => $template->language,
                'category' => $template->category,
            ] : null,
            'sample_preview' => $samplePreview,
            'items' => $items,
        ];
    }

    /**
     * Create bulk email batch and dispatch asynchronous jobs per partner.
     *
     * @param  array<int, string|int>  $partnerIds
     * @param  array<string, mixed>  $options
     */
    public function createBulkBatch(
        User $user,
        string $partnerType,
        array $partnerIds,
        array $options
    ): \App\Modules\BusinessPartners\Models\BpBulkEmailBatch {
        $normalizedType = $this->normalizePartnerType($partnerType);

        if (! $user->hasRole('Super Admin') && ! $user->can('create-email-messages')) {
            abort(403, 'You are not authorized to compose or send emails.');
        }

        $modelClass = $this->resolveModelClass($normalizedType);
        Gate::forUser($user)->authorize('viewAny', $modelClass);

        if (count($partnerIds) > 250) {
            throw ValidationException::withMessages([
                'partner_ids' => ['Bulk email limit is maximum 250 records per batch.'],
            ]);
        }

        $idempotencyKey = ! empty($options['idempotency_key']) ? trim((string) $options['idempotency_key']) : null;
        if ($idempotencyKey) {
            $existing = \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing->load('items');
            }
        }

        $senderProfileId = ! empty($options['sender_profile_id']) ? (int) $options['sender_profile_id'] : null;
        $senderProfile = $this->senderProfileService->resolveAuthorizedProfile($user, $senderProfileId);

        $rule = $options['recipient_rule'] ?? \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::RULE_PRIMARY_EMAIL_FIRST;
        $rawLang = $options['language'] ?? 'fr';
        $lang = in_array($rawLang, ['fr', 'en', 'ar'], true) ? $rawLang : 'fr';
        $includePdf = filter_var($options['include_pdf'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $category = match ($normalizedType) {
            'client' => EmailTemplate::CATEGORY_CLIENT_PROFILE,
            'supplier' => EmailTemplate::CATEGORY_SUPPLIER_PROFILE,
            'organisation' => EmailTemplate::CATEGORY_ORGANISATION_PROFILE,
            'service_provider' => EmailTemplate::CATEGORY_SERVICE_PROVIDER_PROFILE,
        };

        $template = ! empty($options['template_id'])
            ? EmailTemplate::find($options['template_id'])
            : (EmailTemplate::where('category', $category)->where('language', $lang)->where('is_active', true)->first()
                ?? EmailTemplate::where('category', EmailTemplate::CATEGORY_GENERAL_COMMUNICATION)->where('language', $lang)->where('is_active', true)->first());

        $modelClass = $this->resolveModelClass($normalizedType);
        $records = $modelClass::with(['contacts'])->whereIn('id', $partnerIds)->get()->keyBy('id');

        $batch = \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::create([
            'partner_type' => $normalizedType,
            'sender_profile_id' => $senderProfile->id,
            'template_id' => $template?->id,
            'language' => $lang,
            'recipient_rule' => $rule,
            'include_pdf' => $includePdf,
            'total_count' => count($partnerIds),
            'sent_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'status' => \App\Modules\BusinessPartners\Models\BpBulkEmailBatch::STATUS_PENDING,
            'idempotency_key' => $idempotencyKey,
            'created_by_user_id' => $user->id,
            'metadata' => [
                'partner_ids' => $partnerIds,
            ],
        ]);

        $dispatchItemIds = [];

        foreach ($partnerIds as $id) {
            $record = $records->get($id);
            if (! $record) {
                $batch->items()->create([
                    'partner_type' => $normalizedType,
                    'partner_id' => (string) $id,
                    'status' => \App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem::STATUS_SKIPPED,
                    'skip_reason' => 'not_found',
                    'error_message' => 'Record not found',
                ]);

                continue;
            }

            try {
                $this->authorizeUser($user, $record);
            } catch (\Throwable $e) {
                $batch->items()->create([
                    'partner_type' => $normalizedType,
                    'partner_id' => (string) $record->id,
                    'partner_code' => $this->resolvePartnerCode($normalizedType, $record),
                    'partner_name' => $this->resolvePartnerName($record),
                    'status' => \App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem::STATUS_SKIPPED,
                    'skip_reason' => \App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem::SKIP_REASON_UNAUTHORIZED,
                    'error_message' => 'Unauthorized access to record',
                ]);

                continue;
            }

            $partnerName = $this->resolvePartnerName($record);
            $partnerCode = $this->resolvePartnerCode($normalizedType, $record);
            $recipient = $this->resolveRecipientForRule($record, $normalizedType, $rule);

            if (! $recipient || empty($recipient['email'])) {
                $batch->items()->create([
                    'partner_type' => $normalizedType,
                    'partner_id' => (string) $record->id,
                    'partner_code' => $partnerCode,
                    'partner_name' => $partnerName,
                    'status' => \App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem::STATUS_SKIPPED,
                    'skip_reason' => \App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem::SKIP_REASON_NO_EMAIL,
                    'error_message' => 'No valid email found',
                ]);

                continue;
            }

            $item = $batch->items()->create([
                'partner_type' => $normalizedType,
                'partner_id' => (string) $record->id,
                'partner_code' => $partnerCode,
                'partner_name' => $partnerName,
                'resolved_email' => $recipient['email'],
                'resolved_name' => $recipient['name'],
                'contact_id' => $recipient['contact_id'],
                'email_source' => $recipient['email_source'],
                'status' => \App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem::STATUS_PENDING,
            ]);

            $dispatchItemIds[] = $item->id;
        }

        $batch->refresh()->updateProgress();

        // Dispatch background jobs for each ready partner
        foreach ($dispatchItemIds as $itemId) {
            \App\Modules\BusinessPartners\Jobs\ProcessBpBulkEmailItemJob::dispatch($itemId);
        }

        return $batch->load('items');
    }

    /**
     * Send email for a single item in a bulk batch.
     */
    public function sendSingleBulkItem(
        \App\Modules\BusinessPartners\Models\BpBulkEmailBatch $batch,
        \App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem $item,
        User $user
    ): EmailMessage {
        $record = $this->findPartner($item->partner_type, $item->partner_id);
        $this->authorizeUser($user, $record);

        $senderProfile = $batch->senderProfile
            ?? $this->senderProfileService->resolveAuthorizedProfile($user, $batch->sender_profile_id);

        $partnerName = $item->partner_name ?: $this->resolvePartnerName($record);
        $partnerCode = $item->partner_code ?: $this->resolvePartnerCode($item->partner_type, $record);

        // 1. Resolve template
        $template = $batch->template;
        if (! $template) {
            $category = match ($item->partner_type) {
                'client' => EmailTemplate::CATEGORY_CLIENT_PROFILE,
                'supplier' => EmailTemplate::CATEGORY_SUPPLIER_PROFILE,
                'organisation' => EmailTemplate::CATEGORY_ORGANISATION_PROFILE,
                'service_provider' => EmailTemplate::CATEGORY_SERVICE_PROVIDER_PROFILE,
            };
            $template = EmailTemplate::where('category', $category)->where('language', $batch->language)->where('is_active', true)->first()
                ?? EmailTemplate::where('category', EmailTemplate::CATEGORY_GENERAL_COMMUNICATION)->where('language', $batch->language)->where('is_active', true)->first();
        }

        if (! $template) {
            throw new \RuntimeException("No active email template found for {$item->partner_type} in language {$batch->language}.");
        }

        // 2. Resolve dynamic variables for this specific partner
        $resolvedContext = $this->contextService->resolveContext(
            user: $user,
            recordType: $item->partner_type,
            recordId: $record->id,
            recipient: [
                'name' => $item->resolved_name ?: $partnerName,
                'email' => $item->resolved_email,
                'company' => $partnerName,
            ],
            senderProfileId: $senderProfile->id
        );

        $rendered = $this->templateRenderer->renderPreview(
            template: $template,
            context: $resolvedContext['context'],
            signatureType: 'personal',
            user: $user
        );

        $subject = trim($rendered['subject']);
        $bodyHtml = trim($rendered['body_html']);
        $bodyText = ! empty($rendered['body_text']) ? trim($rendered['body_text']) : strip_tags($bodyHtml);

        if (preg_match('/\{\{\s*[\w\.\-]+\s*\}\}/', $subject) || preg_match('/\{\{\s*[\w\.\-]+\s*\}\}/', $bodyHtml)) {
            throw new \RuntimeException('Email template contains unresolved placeholders.');
        }

        // 3. Generate PDF if requested
        $pdfBytes = null;
        $pdfFilename = $this->resolvePdfFilename($item->partner_type, $partnerCode);
        if ($batch->include_pdf) {
            $pdfBytes = $this->generatePartnerPdf($item->partner_type, $record);
        }

        // 4. Create isolated EmailConversation
        $conversation = EmailConversation::create([
            'subject' => $subject,
            'status' => EmailConversation::STATUS_CLOSED,
            'folder' => EmailMessage::FOLDER_SENT,
            'last_message_at' => now(),
            'message_count' => 1,
        ]);

        // 5. Create EmailMessage
        $message = EmailMessage::create([
            'conversation_id' => $conversation->id,
            'sender_profile_id' => $senderProfile->id,
            'sender_email' => $senderProfile->email,
            'sender_name' => $senderProfile->name,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'status' => EmailMessage::STATUS_QUEUED,
            'folder' => EmailMessage::FOLDER_SENT,
            'idempotency_key' => (string) Str::uuid(),
            'has_attachments' => $batch->include_pdf && ! empty($pdfBytes),
            'sent_by_user_id' => $user->id,
        ]);

        // 6. Store PDF as private attachment snapshot
        if ($batch->include_pdf && ! empty($pdfBytes)) {
            $this->attachmentService->storeRawBytes($message, $pdfBytes, $pdfFilename, 'application/pdf');
        }

        // 7. Store Recipient
        EmailRecipient::create([
            'email_message_id' => $message->id,
            'type' => EmailRecipient::TYPE_TO,
            'email' => strtolower(trim($item->resolved_email)),
            'name' => $item->resolved_name,
            'recipient_type' => $item->partner_type,
            'recipient_id' => (string) $record->id,
            'contact_id' => $item->contact_id,
            'email_source' => $item->email_source ?: 'general',
        ]);

        // 8. Link records
        $this->recordLinkService->linkRecord(
            conversation: $conversation,
            recordType: $item->partner_type,
            recordId: (string) $record->id,
            user: $user,
            linkType: 'primary',
            isConfirmed: true,
            confidence: 1.0,
            reason: "Bulk contextual email sent for {$item->partner_type}",
            messageId: $message->id
        );

        if ($item->contact_id) {
            $contact = BpContact::find($item->contact_id);
            if ($contact && (string) $contact->contactable_id === (string) $record->id) {
                $this->recordLinkService->linkRecord(
                    conversation: $conversation,
                    recordType: 'bp_contact',
                    recordId: (string) $contact->id,
                    user: $user,
                    linkType: 'contact',
                    isConfirmed: true,
                    confidence: 1.0,
                    reason: 'Bulk email recipient contact',
                    messageId: $message->id
                );
            }
        }

        // 9. Dispatch send email message job
        SendEmailMessageJob::dispatch($message->id);

        return $message;
    }

    public function resolvePartnerName(Model $record): string
    {
        $legalName = $this->resolveModelTranslatable($record, 'legal_name');
        $commName = $this->resolveModelTranslatable($record, 'commercial_name');

        return $legalName ?: ($commName ?: "Partner #{$record->id}");
    }

    public function resolvePartnerCode(string $normalizedType, Model $record): string
    {
        return match ($normalizedType) {
            'client' => $record->client_id ?? (string) $record->id,
            'supplier' => $record->supplier_id ?? (string) $record->id,
            'organisation' => $record->organisation_id ?? (string) $record->id,
            'service_provider' => $record->service_provider_id ?? (string) $record->id,
            default => (string) $record->id,
        };
    }

    public function resolveModelTranslatable(mixed $model, string $attribute): string
    {
        if (! $model) {
            return '';
        }

        if (method_exists($model, 'getTranslations')) {
            $translations = $model->getTranslations($attribute);
            if (! empty($translations) && is_array($translations)) {
                $locale = app()->getLocale();
                if (! empty($translations[$locale])) {
                    return (string) $translations[$locale];
                }
                if (! empty($translations['fr'])) {
                    return (string) $translations['fr'];
                }
                if (! empty($translations['en'])) {
                    return (string) $translations['en'];
                }
                $first = reset($translations);
                if (! empty($first)) {
                    return (string) $first;
                }
            }
        }

        return $this->formatTranslatable($model->{$attribute} ?? null);
    }

    private function resolvePdfFilename(string $normalizedType, string $code): string
    {
        $prefix = str_replace('_', '-', $normalizedType);

        return "{$prefix}-{$code}.pdf";
    }

    /**
     * Persist recipient list on message.
     *
     * @param  array<int, mixed>  $recipients
     */
    private function storeRecipients(EmailMessage $message, string $type, array $recipients): void
    {
        foreach ($recipients as $rec) {
            $email = is_string($rec) ? $rec : ($rec['email'] ?? null);
            if (! $email) {
                continue;
            }

            EmailRecipient::create([
                'email_message_id' => $message->id,
                'type' => $type,
                'email' => strtolower(trim($email)),
                'name' => is_array($rec) ? ($rec['name'] ?? null) : null,
                'recipient_type' => is_array($rec) ? ($rec['recipient_type'] ?? null) : null,
                'recipient_id' => is_array($rec) ? ($rec['recipient_id'] ?? null) : null,
                'contact_id' => is_array($rec) ? ($rec['contact_id'] ?? null) : null,
                'email_source' => is_array($rec) ? ($rec['email_source'] ?? 'general') : 'general',
            ]);
        }
    }

    private function formatTranslatable(mixed $val): string
    {
        if (is_string($val)) {
            return $val;
        }
        if (is_array($val)) {
            return (string) ($val[app()->getLocale()] ?? reset($val) ?: '');
        }

        return '';
    }
}
