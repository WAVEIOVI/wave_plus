<?php

declare(strict_types=1);

namespace App\Modules\Shared\Helpers;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaFormatter
{
    /**
     * Format a Spatie Media model into a standardized API payload.
     */
    public static function format(Media $media): array
    {
        $isPrivate = ($media->disk !== 'public');
        $downloadUrl = static::resolveDownloadUrl($media);
        $url = $isPrivate
            ? ($downloadUrl ?: null)
            : static::normalizeUrl($media->getUrl());

        $isImage = str_starts_with($media->mime_type ?? '', 'image/');

        // Conversions are only generated and accessible for public image collections
        $thumbUrl = null;
        $previewUrl = null;

        if ($isImage && ! $isPrivate) {
            if ($media->hasGeneratedConversion('thumb')) {
                $thumbUrl = static::normalizeUrl($media->getUrl('thumb'));
            }
            if ($media->hasGeneratedConversion('preview')) {
                $previewUrl = static::normalizeUrl($media->getUrl('preview'));
            }
        }

        // Custom properties
        $customProperties = $media->custom_properties ?? [];
        $width = $customProperties['width'] ?? null;
        $height = $customProperties['height'] ?? null;
        $uploadedBy = $customProperties['uploaded_by'] ?? null;
        $uploadedById = $customProperties['uploaded_by_id'] ?? null;

        $title = $customProperties['title'] ?? $customProperties['name'] ?? $media->name;
        $category = $customProperties['category'] ?? $customProperties['type'] ?? $customProperties['document_type'] ?? $media->collection_name;
        $reference = $customProperties['reference'] ?? $customProperties['reference_number'] ?? $customProperties['document_number'] ?? null;
        $notes = $customProperties['notes'] ?? $customProperties['description'] ?? null;
        $date = $customProperties['date'] ?? $customProperties['issue_date'] ?? null;
        $expiryDate = $customProperties['expiry_date'] ?? $customProperties['expiration_date'] ?? $customProperties['expires_at'] ?? null;
        $status = $customProperties['status'] ?? 'active';

        return [
            'id' => $media->id,
            'model_type' => class_basename($media->model_type),
            'model_id' => $media->model_id,
            'collection_name' => $media->collection_name,
            'name' => $media->name,
            'title' => $title,
            'category' => $category,
            'type' => $category,
            'reference' => $reference,
            'reference_number' => $reference,
            'notes' => $notes,
            'description' => $notes,
            'date' => $date,
            'issue_date' => $date,
            'expiry_date' => $expiryDate,
            'expiration_date' => $expiryDate,
            'status' => $status,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'human_raw_size' => $media->human_readable_size,
            'disk' => $media->disk,
            'is_private' => $isPrivate,
            'url' => $url,
            'original_url' => $url,
            'download_url' => $downloadUrl ?: $url,
            'thumb_url' => $thumbUrl ?: ($isImage && ! $isPrivate ? $url : null),
            'preview_url' => $previewUrl ?: ($isImage && ! $isPrivate ? $url : null),
            'conversions' => [
                'thumb' => $thumbUrl,
                'preview' => $previewUrl,
            ],
            'is_image' => $isImage,
            'width' => $width,
            'height' => $height,
            'uploaded_by' => $uploadedBy,
            'uploaded_by_id' => $uploadedById,
            'custom_properties' => $customProperties,
            'created_at' => $media->created_at?->toISOString(),
            'updated_at' => $media->updated_at?->toISOString(),
        ];
    }

    /**
     * Resolve the authorized download route URL for an entity media record.
     */
    public static function resolveDownloadUrl(Media $media): ?string
    {
        $modelType = $media->model_type;
        $modelId = (string) $media->model_id;
        $mediaId = (string) $media->id;

        if (str_ends_with($modelType, 'ClientSite')) {
            return url("/api/client-assets/sites/{$modelId}/media/{$mediaId}/download");
        }
        if (str_ends_with($modelType, 'ClientEquipment')) {
            return url("/api/client-assets/equipment/{$modelId}/media/{$mediaId}/download");
        }
        if (str_ends_with($modelType, 'WorkwearModel')) {
            return url("/api/client-assets/workwear-models/{$modelId}/media/{$mediaId}/download");
        }
        if (str_ends_with($modelType, 'CatalogItem')) {
            return url("/api/catalog/items/{$modelId}/media/{$mediaId}/download");
        }
        if (str_ends_with($modelType, 'Employee')) {
            return url("/api/hr/employees/{$modelId}/media/{$mediaId}/download");
        }
        if (str_ends_with($modelType, 'BpDocument')) {
            return url("/api/bp/documents/{$modelId}/download");
        }
        if (str_ends_with($modelType, 'VehicleDocument')) {
            return url("/api/hr/fleet/documents/{$modelId}/download");
        }
        if (str_ends_with($modelType, 'Contract')) {
            return url("/api/crm/contracts/{$modelId}/download-document");
        }
        if (str_ends_with($modelType, 'Candidate')) {
            return url("/api/hr/recruitment/candidates/{$modelId}/media/{$mediaId}/download");
        }
        if (str_ends_with($modelType, 'EmployeeCertification')) {
            return url("/api/hr/employee-certifications/{$modelId}/download");
        }
        if (str_ends_with($modelType, 'GoodsReceiptNote')) {
            return url("/api/procurement/grns/{$modelId}/download-delivery-note");
        }

        return null;
    }

    /**
     * Replace APP_URL base with the current request's base URL to ensure
     * proper host and port mapping in development environments.
     */
    public static function normalizeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $requestBase = rtrim(url('/'), '/');

        if ($appUrl !== $requestBase && ! empty($appUrl)) {
            return str_replace($appUrl, $requestBase, $url);
        }

        return $url;
    }
}
