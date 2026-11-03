<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailRecordLink;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmailRecordLinkService
{
    public function __construct(
        protected EmailRecordMatcherService $matcher
    ) {}

    /**
     * Check if a user is authorized to access a given business record.
     */
    public function authorizeRecordAccess(User $user, string $recordType, string|int $recordId): Model
    {
        $modelClass = EmailRecordLink::resolveModelClass($recordType);
        if (! $modelClass) {
            throw ValidationException::withMessages([
                'record_type' => ["Unsupported record type [{$recordType}]."],
            ]);
        }

        // Query model, preserving history for archived/soft-deleted records
        $hasSoftDeletes = in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass), true);
        $query = $hasSoftDeletes
            ? $modelClass::withTrashed()
            : $modelClass::query();

        $record = $query->find($recordId);
        if (! $record) {
            throw new NotFoundHttpException("Record of type [{$recordType}] with ID [{$recordId}] not found.");
        }

        // Check module-specific permissions
        $authorized = match (strtolower($recordType)) {
            'client', 'supplier', 'organisation', 'organization', 'service_provider', 'business_partner' => $user->hasRole('Admin') || $user->can('read-BusinessPartners') || $user->can('access-BusinessPartners') || $user->can('view', $record),

            'bp_contact', 'contact' => $user->hasRole('Admin') || $user->can('read-BusinessPartners') || $user->can('access-BusinessPartners') || ($record->contactable && $user->can('view', $record->contactable)),

            'bp_document', 'document' => $user->hasRole('Admin') || $user->can('read-BusinessPartners') || $user->can('access-BusinessPartners') || ($record->documentable && $user->can('view', $record->documentable)),

            'quotation', 'sales_order', 'contract' => $user->hasRole('Admin') || $user->can('read-CrmSales') || $user->can('access-CrmSales') || $user->can('view', $record),

            'purchase_order', 'goods_receipt_note' => $user->hasRole('Admin') || $user->can('read-Procurement') || $user->can('access-Procurement') || $user->can('view', $record),

            'sales_invoice', 'supplier_invoice', 'invoice' => $user->hasRole('Admin') || $user->can('read-Finance') || $user->can('access-Finance') || $user->can('view', $record),

            'mission', 'service_work_order' => $user->hasRole('Admin') || $user->can('read-OperationalHub') || $user->can('access-OperationalHub') || $user->can('view', $record),

            'employee' => $user->hasRole('Admin') || $user->can('read-Hr') || $user->can('access-Hr') || $user->can('view', $record),

            'delivery_work_order' => $user->hasRole('Admin') || $user->can('read-Logistics') || $user->can('access-Logistics') || $user->can('view', $record),

            default => $user->hasRole('Admin'),
        };

        if (! $authorized) {
            throw new AccessDeniedHttpException("You are not authorized to access [{$recordType}] records.");
        }

        return $record;
    }

    /**
     * Link an email conversation to a business record.
     */
    public function linkRecord(
        EmailConversation $conversation,
        string $recordType,
        string|int $recordId,
        User $user,
        string $linkType = 'manual',
        bool $isConfirmed = true,
        float $confidence = 1.0,
        ?string $reason = null,
        ?string $messageId = null
    ): EmailRecordLink {
        // Enforce authorization
        $record = $this->authorizeRecordAccess($user, $recordType, $recordId);
        $modelClass = get_class($record);

        $existing = EmailRecordLink::query()
            ->where('conversation_id', $conversation->id)
            ->where('associable_type', $modelClass)
            ->where('associable_id', (string) $recordId)
            ->first();

        if ($existing) {
            if (! $existing->is_confirmed && $isConfirmed) {
                $existing->confirm($user->id);
            }

            return $existing;
        }

        return EmailRecordLink::create([
            'conversation_id' => $conversation->id,
            'email_message_id' => $messageId,
            'associable_type' => $modelClass,
            'associable_id' => (string) $recordId,
            'link_type' => $linkType,
            'is_confirmed' => $isConfirmed,
            'confidence_score' => $confidence,
            'match_reason' => $reason ?? "Linked by {$user->name}",
            'linked_by' => $user->id,
        ]);
    }

    /**
     * Unlink an email conversation from a business record.
     */
    public function unlinkRecord(
        EmailConversation $conversation,
        string $recordType,
        string|int $recordId,
        User $user
    ): bool {
        // Enforce authorization
        $this->authorizeRecordAccess($user, $recordType, $recordId);

        $modelClass = EmailRecordLink::resolveModelClass($recordType);

        $link = EmailRecordLink::query()
            ->where('conversation_id', $conversation->id)
            ->where(function ($q) use ($modelClass, $recordType) {
                $q->where('associable_type', $modelClass)
                    ->orWhere('associable_type', $recordType);
            })
            ->where('associable_id', (string) $recordId)
            ->first();

        if ($link) {
            return (bool) $link->delete();
        }

        return false;
    }

    /**
     * Get all record links for a conversation formatted with record details.
     */
    public function getLinksForConversation(EmailConversation $conversation, User $user, bool $includeUnconfirmed = true): Collection
    {
        $query = EmailRecordLink::query()
            ->where('conversation_id', $conversation->id);

        if (! $includeUnconfirmed) {
            $query->where('is_confirmed', true);
        }

        $links = $query->with('linkedByUser')->get();

        return $links->map(function (EmailRecordLink $link) use ($user) {
            $type = EmailRecordLink::resolveRecordType($link->associable_type);
            $recordName = "{$type} #{$link->associable_id}";
            $recordUrl = null;
            $canView = true;

            try {
                $record = $this->authorizeRecordAccess($user, $type, $link->associable_id);
                $name = trim(($record->first_name ?? '').' '.($record->last_name ?? ''));
                if ($name === '' && isset($record->name)) {
                    $name = (string) $record->name;
                }

                $recordName = $record->reference
                    ?? $record->invoice_number
                    ?? $record->mission_number
                    ?? $record->legal_name
                    ?? ($name !== '' ? $name : null)
                    ?? $record->title
                    ?? $record->file_name
                    ?? $recordName;

                $recordUrl = $this->resolveRecordUrl($type, $link->associable_id, $record);
            } catch (\Throwable) {
                $canView = false;
            }

            return [
                'id' => $link->id,
                'conversation_id' => $link->conversation_id,
                'record_type' => $type,
                'record_id' => $link->associable_id,
                'record_reference' => $recordName,
                'record_url' => $recordUrl,
                'link_type' => $link->link_type,
                'is_confirmed' => $link->is_confirmed,
                'confidence_score' => $link->confidence_score,
                'match_reason' => $link->match_reason,
                'linked_by' => $link->linkedByUser?->name,
                'created_at' => $link->created_at?->toIso8601String(),
                'can_view_record' => $canView,
            ];
        })->filter(fn ($item) => $item['can_view_record'])->values();
    }

    /**
     * Get conversations associated with a specific business record.
     */
    public function getConversationsForRecord(
        string $recordType,
        string|int $recordId,
        User $user,
        int $perPage = 15
    ): LengthAwarePaginator {
        // Enforce authorization for the target record
        $this->authorizeRecordAccess($user, $recordType, $recordId);

        $modelClass = EmailRecordLink::resolveModelClass($recordType);

        // Fetch conversation IDs linked to this record
        $conversationIds = EmailRecordLink::query()
            ->where(function ($q) use ($modelClass, $recordType) {
                $q->where('associable_type', $modelClass)
                    ->orWhere('associable_type', $recordType);
            })
            ->where('associable_id', (string) $recordId)
            ->where('is_confirmed', true)
            ->pluck('conversation_id')
            ->filter()
            ->unique();

        $convQuery = EmailConversation::query()
            ->whereIn('id', $conversationIds);

        // Apply communications access control so restricted department conversations stay isolated
        app(\App\Modules\Communications\Email\Services\EmailAccessControlService::class)->applyConversationScope($convQuery, $user);

        return $convQuery
            ->with(['mailbox', 'assignedTo', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderBy('last_message_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Run automatic matching on conversation and create confirmed or suggested links.
     */
    public function autoMatchAndLink(EmailConversation $conversation, ?EmailMessage $message = null): array
    {
        $matches = $this->matcher->matchRecords($conversation, $message);
        $created = [];

        foreach ($matches as $match) {
            $modelClass = EmailRecordLink::resolveModelClass($match['record_type']);
            if (! $modelClass) {
                continue;
            }

            $link = EmailRecordLink::firstOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'associable_type' => $modelClass,
                    'associable_id' => (string) $match['record_id'],
                ],
                [
                    'email_message_id' => $message?->id,
                    'link_type' => $match['is_confirmed'] ? 'auto_reference' : 'auto_email',
                    'is_confirmed' => $match['is_confirmed'],
                    'confidence_score' => $match['confidence_score'],
                    'match_reason' => $match['match_reason'],
                ]
            );

            $created[] = $link;
        }

        return $created;
    }

    /**
     * Confirm an ambiguous suggested link.
     */
    public function confirmSuggestion(int $linkId, User $user): EmailRecordLink
    {
        $link = EmailRecordLink::findOrFail($linkId);
        $this->authorizeRecordAccess($user, EmailRecordLink::resolveRecordType($link->associable_type), $link->associable_id);

        $link->confirm($user->id);

        return $link;
    }

    /**
     * Dismiss/delete an ambiguous suggested link.
     */
    public function dismissSuggestion(int $linkId, User $user): bool
    {
        $link = EmailRecordLink::findOrFail($linkId);
        $this->authorizeRecordAccess($user, EmailRecordLink::resolveRecordType($link->associable_type), $link->associable_id);

        return (bool) $link->delete();
    }

    /**
     * Resolve frontend navigation route for a record.
     */
    protected function resolveRecordUrl(string $recordType, string|int $recordId, ?Model $record = null): ?array
    {
        return match (strtolower($recordType)) {
            'client' => ['name' => 'business-partners-clients-id', 'params' => ['id' => $recordId]],
            'supplier' => ['name' => 'business-partners-suppliers-id', 'params' => ['id' => $recordId]],
            'organisation', 'organization' => ['name' => 'business-partners-organisations-id', 'params' => ['id' => $recordId]],
            'service_provider' => ['name' => 'business-partners-service-providers-id', 'params' => ['id' => $recordId]],
            'bp_contact', 'contact' => $record && $record->contactable_id ? $this->resolveRecordUrl(EmailRecordLink::resolveRecordType($record->contactable_type) ?: 'client', $record->contactable_id) : null,
            'bp_document', 'document' => $record && $record->documentable_id ? $this->resolveRecordUrl(EmailRecordLink::resolveRecordType($record->documentable_type) ?: 'client', $record->documentable_id) : null,
            'employee' => ['name' => 'hr-employees-id', 'params' => ['id' => $recordId]],
            'quotation' => ['name' => 'crm-quotations-id', 'params' => ['id' => $recordId]],
            'sales_order' => ['name' => 'crm-sales-orders-id', 'params' => ['id' => $recordId]],
            'purchase_order' => ['name' => 'procurement-purchase-orders-id', 'params' => ['id' => $recordId]],
            'sales_invoice', 'invoice' => ['name' => 'finance-sales-invoices-id', 'params' => ['id' => $recordId]],
            'mission' => ['name' => 'operational-hub-missions-id', 'params' => ['id' => $recordId]],
            default => null,
        };
    }
}
