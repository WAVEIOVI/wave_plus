<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Models\User;
use App\Modules\BusinessPartners\Models\BpDocument;
use App\Modules\BusinessPartners\Services\BusinessPartnerEmailSearchService;
use Illuminate\Support\Facades\Gate;

/**
 * Searches EXISTING stored documents that a user may attach to an email.
 *
 * Providers are explicit and authorization-aware. Currently wired:
 *  - Business Partners documents (clients, suppliers, organisations, service providers)
 *
 * No document is generated or exported here: only already-stored files are listed.
 */
class EmailDocumentSearchService
{
    public const SOURCE_BP_DOCUMENT = 'bp_document';

    public function __construct(
        private readonly BusinessPartnerEmailSearchService $partnerSearch,
    ) {}

    /**
     * @param  array{q?: string|null, module?: string|null, category?: string|null, entity_type?: string|null}  $filters
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function search(User $user, array $filters, int $page = 1, int $perPage = 12): array
    {
        $module = $filters['module'] ?? null;

        if ($module && $module !== 'business_partners') {
            return ['data' => [], 'total' => 0];
        }

        return $this->searchBusinessPartnerDocuments($user, $filters, $page, $perPage);
    }

    /**
     * Resolve a document the user is authorized to attach. Returns null when not found or not authorized.
     */
    public function resolveAttachable(User $user, string $source, string $id): ?BpDocument
    {
        if ($source !== self::SOURCE_BP_DOCUMENT) {
            return null;
        }

        $document = BpDocument::query()->with('documentable')->find($id);

        if (! $document || ! $document->documentable) {
            return null;
        }

        if (! $this->canAccessPartnerType($user, $document->documentable_type)) {
            return null;
        }

        return Gate::forUser($user)->allows('view', $document->documentable) ? $document : null;
    }

    private function searchBusinessPartnerDocuments(User $user, array $filters, int $page, int $perPage): array
    {
        $allowed = collect($this->partnerSearch->getAllowedPartnerCategories($user));

        if ($allowed->isEmpty()) {
            return ['data' => [], 'total' => 0];
        }

        $typeFilter = $filters['entity_type'] ?? null;
        $allowedModels = $allowed
            ->when($typeFilter, fn ($c) => $c->where('key', $typeFilter))
            ->pluck('model')
            ->all();

        if (empty($allowedModels)) {
            return ['data' => [], 'total' => 0];
        }

        $query = BpDocument::query()
            ->with('documentable')
            ->whereIn('documentable_type', $allowedModels)
            ->whereHas('media', fn ($q) => $q->where('collection_name', 'document'));

        if (! empty($filters['category'])) {
            $query->where('type', $filters['category']);
        }

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = "%{$term}%";
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('file_name', 'like', $like)
                    ->orWhere('reference_number', 'like', $like);
            });
        }

        $total = (clone $query)->count();

        $rows = $query->orderByDesc('created_at')
            ->forPage($page, $perPage)
            ->get();

        $typeKeyByModel = $allowed->pluck('key', 'model');
        $typeLabelByModel = $allowed->pluck('type', 'model');

        $data = $rows
            ->filter(fn (BpDocument $d) => $d->documentable && Gate::forUser($user)->allows('view', $d->documentable))
            ->map(function (BpDocument $d) use ($typeKeyByModel, $typeLabelByModel) {
                $media = $d->getFirstMedia('document');

                $sizeBytes = (int) ($d->file_size_bytes ?: $media?->size ?: 0);
                $entityType = $typeKeyByModel[$d->documentable_type] ?? 'partner';
                $entityLabel = $typeLabelByModel[$d->documentable_type] ?? 'Partner';
                $entityName = $this->entityName($d->documentable);

                return [
                    'id' => (string) $d->id,
                    'source' => self::SOURCE_BP_DOCUMENT,
                    'name' => $this->localized($d->getTranslations('title') ?: []) ?: ($d->file_name ?: 'Document'),
                    'file_name' => $d->file_name ?: $media?->file_name,
                    'module' => 'business_partners',
                    'module_label' => 'Business Partners',
                    'entity_type' => $entityType,
                    'entity_type_label' => $entityLabel,
                    'entity_id' => (string) $d->documentable_id,
                    'entity_name' => $entityName,
                    'related_entity' => [
                        'type' => $entityType,
                        'type_label' => $entityLabel,
                        'id' => (string) $d->documentable_id,
                        'name' => $entityName,
                    ],
                    'category' => $d->type,
                    'mime_type' => $d->mime_type ?: $media?->mime_type,
                    'extension' => strtolower(pathinfo((string) ($d->file_name ?: $media?->file_name), PATHINFO_EXTENSION)),
                    'size_bytes' => $sizeBytes,
                    'file_size' => $sizeBytes,
                    'formatted_size' => self::formatBytes($sizeBytes),
                    'created_at' => $d->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return ['data' => $data, 'total' => $total];
    }

    private function canAccessPartnerType(User $user, string $modelClass): bool
    {
        return collect($this->partnerSearch->getAllowedPartnerCategories($user))
            ->contains(fn ($c) => $c['model'] === $modelClass);
    }

    private function entityName($owner): string
    {
        $legal = method_exists($owner, 'getTranslations') ? $this->localized($owner->getTranslations('legal_name')) : '';
        $commercial = method_exists($owner, 'getTranslations') ? $this->localized($owner->getTranslations('commercial_name')) : '';

        return $legal ?: ($commercial ?: 'Partner');
    }

    /**
     * @param  array<string, string>  $translations
     */
    private function localized(array $translations): string
    {
        foreach ([app()->getLocale(), 'en', 'fr', 'ar'] as $locale) {
            if (! empty($translations[$locale])) {
                return (string) $translations[$locale];
            }
        }

        return (string) (reset($translations) ?: '');
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / pow(1024, $i), 2).' '.$units[$i];
    }
}
