<?php

declare(strict_types=1);

namespace App\Modules\Shared\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MediaValidationService
{
    public const CATEGORY_IMAGE = 'image';

    public const CATEGORY_DOCUMENT = 'document';

    public const CATEGORY_CERTIFICATE = 'certificate';

    public const CATEGORY_RESUME = 'resume';

    public const CATEGORY_DESIGN = 'design';

    public const CATEGORY_VIDEO = 'video';

    /**
     * Configuration for allowed extensions, MIME types, and maximum sizes (in KB).
     */
    protected const POLICIES = [
        self::CATEGORY_IMAGE => [
            'max_kb' => 10240, // 10MB
            'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'],
            'mimes' => [
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'image/webp' => ['webp'],
                'image/gif' => ['gif'],
                'image/svg+xml' => ['svg'],
                'image/svg' => ['svg'],
            ],
        ],
        self::CATEGORY_DOCUMENT => [
            'max_kb' => 20480, // 20MB
            'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'webp'],
            'mimes' => [
                'application/pdf' => ['pdf'],
                'application/msword' => ['doc'],
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
                'application/vnd.ms-excel' => ['xls'],
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
                'text/plain' => ['txt', 'csv'],
                'text/csv' => ['csv'],
                'text/x-csv' => ['csv'],
                'application/csv' => ['csv'],
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'image/webp' => ['webp'],
            ],
        ],
        self::CATEGORY_CERTIFICATE => [
            'max_kb' => 20480, // 20MB
            'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
            'mimes' => [
                'application/pdf' => ['pdf'],
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'image/webp' => ['webp'],
            ],
        ],
        self::CATEGORY_RESUME => [
            'max_kb' => 10240, // 10MB
            'extensions' => ['pdf', 'doc', 'docx'],
            'mimes' => [
                'application/pdf' => ['pdf'],
                'application/msword' => ['doc'],
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            ],
        ],
        self::CATEGORY_DESIGN => [
            'max_kb' => 51200, // 50MB
            'extensions' => ['ai', 'eps', 'pdf', 'svg', 'png', 'psd'],
            'mimes' => [
                'application/postscript' => ['ai', 'eps'],
                'application/illustrator' => ['ai'],
                'application/pdf' => ['ai', 'pdf'],
                'image/svg+xml' => ['svg'],
                'image/svg' => ['svg'],
                'image/png' => ['png'],
                'image/vnd.adobe.photoshop' => ['psd'],
                'image/x-photoshop' => ['psd'],
                'application/x-photoshop' => ['psd'],
                'application/photoshop' => ['psd'],
            ],
        ],
        self::CATEGORY_VIDEO => [
            'max_kb' => 102400, // 100MB
            'extensions' => ['mp4', 'webm', 'mov', 'avi'],
            'mimes' => [
                'video/mp4' => ['mp4'],
                'video/webm' => ['webm'],
                'video/quicktime' => ['mov'],
                'video/x-msvideo' => ['avi'],
                'video/avi' => ['avi'],
            ],
        ],
    ];

    /**
     * Map collection names to validation category.
     */
    public static function categoryForCollection(string $collection): string
    {
        return match ($collection) {
            'images', 'photos', 'avatar', 'logo', 'logos' => self::CATEGORY_IMAGE,
            'certificates', 'certificate_files' => self::CATEGORY_CERTIFICATE,
            'resume' => self::CATEGORY_RESUME,
            'design_files' => self::CATEGORY_DESIGN,
            'video', 'videos', 'inspection_videos' => self::CATEGORY_VIDEO,
            default => self::CATEGORY_DOCUMENT,
        };
    }

    /**
     * Determine whether a collection should be stored publicly or on private local disk.
     */
    public static function diskForCollection(string $collection, ?string $modelClass = null): string
    {
        if (in_array($collection, ['images', 'photos', 'avatar', 'logo', 'logos'], true)) {
            return 'public';
        }

        // Public catalog specifications and conformance documents
        if ($modelClass && str_contains($modelClass, 'CatalogItem') && in_array($collection, ['documents', 'certificates'], true)) {
            return 'public';
        }

        return 'local';
    }

    /**
     * Determine whether a collection is considered private and must reside on local disk.
     */
    public static function isPrivateCollection(string $collection, ?string $modelClass = null): bool
    {
        return self::diskForCollection($collection, $modelClass) === 'local';
    }

    /**
     * Standardize custom properties for an uploaded attachment across all modules.
     */
    public static function buildCustomProperties(array $fileMeta, array $additional = []): array
    {
        $title = $additional['title'] ?? $additional['name'] ?? null;
        if (! $title && ! empty($fileMeta['original_name'])) {
            $title = pathinfo($fileMeta['original_name'], PATHINFO_FILENAME);
        }

        $category = $additional['category'] ?? $additional['type'] ?? $additional['document_type'] ?? 'general';
        $reference = $additional['reference'] ?? $additional['reference_number'] ?? $additional['document_number'] ?? null;
        $notes = $additional['notes'] ?? $additional['description'] ?? null;
        $issueDate = $additional['issue_date'] ?? $additional['date'] ?? null;
        $expiryDate = $additional['expiry_date'] ?? $additional['expiration_date'] ?? $additional['expires_at'] ?? null;
        $status = $additional['status'] ?? 'active';
        $source = $additional['source'] ?? 'upload';

        $properties = [
            'original_name' => $fileMeta['original_name'] ?? null,
            'uploaded_by' => $fileMeta['uploaded_by'] ?? null,
            'uploaded_by_id' => $fileMeta['uploaded_by_id'] ?? null,
            'width' => $fileMeta['width'] ?? null,
            'height' => $fileMeta['height'] ?? null,
            'title' => $title,
            'name' => $title,
            'category' => $category,
            'type' => $category,
            'reference' => $reference,
            'reference_number' => $reference,
            'notes' => $notes,
            'description' => $notes,
            'issue_date' => $issueDate,
            'date' => $issueDate,
            'expiry_date' => $expiryDate,
            'expiration_date' => $expiryDate,
            'status' => $status,
            'source' => $source,
        ];

        // Merge any other extra custom properties (e.g. is_main, issuer, auto_renewable)
        foreach ($additional as $key => $value) {
            if ($value !== null && ! array_key_exists($key, $properties)) {
                $properties[$key] = $value;
            }
        }

        return $properties;
    }

    /**
     * Validate and process an uploaded file.
     *
     * @return array Standardized physical file attributes
     *
     * @throws ValidationException
     */
    public static function validateAndProcess(UploadedFile $file, string $categoryOrCollection, string $fieldName = 'file'): array
    {
        $rawExtForCategory = strtolower($file->getClientOriginalExtension());
        if (
            in_array($categoryOrCollection, ['images', 'photos', 'documents'], true)
            && in_array($rawExtForCategory, self::POLICIES[self::CATEGORY_VIDEO]['extensions'], true)
        ) {
            $category = self::CATEGORY_VIDEO;
        } else {
            $category = isset(self::POLICIES[$categoryOrCollection])
                ? $categoryOrCollection
                : self::categoryForCollection($categoryOrCollection);
        }

        $policy = self::POLICIES[$category] ?? self::POLICIES[self::CATEGORY_DOCUMENT];

        // 1. Check file size
        $sizeBytes = $file->getSize();
        $maxBytes = $policy['max_kb'] * 1024;
        if ($sizeBytes > $maxBytes) {
            $maxMb = $policy['max_kb'] / 1024;
            throw ValidationException::withMessages([
                $fieldName => ["The file size must not exceed {$maxMb}MB."],
            ]);
        }

        // 2. Check extension
        $rawExt = strtolower($file->getClientOriginalExtension());
        if (! in_array($rawExt, $policy['extensions'], true)) {
            $allowedList = implode(', ', $policy['extensions']);
            throw ValidationException::withMessages([
                $fieldName => ["The file must have one of the following extensions: {$allowedList}."],
            ]);
        }

        // 3. Inspect genuine MIME type via finfo
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw ValidationException::withMessages([
                $fieldName => ['The uploaded file could not be read.'],
            ]);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $realPath) ?: $file->getMimeType();
        finfo_close($finfo);

        // Normalize text/xml, application/xml, or text/plain for SVG
        if (($detectedMime === 'text/xml' || $detectedMime === 'application/xml' || $detectedMime === 'text/plain') && $rawExt === 'svg') {
            $sample = @file_get_contents($realPath, false, null, 0, 1024);
            if ($sample && str_contains($sample, '<svg')) {
                $detectedMime = 'image/svg+xml';
            }
        }

        // 4. Validate MIME against policy
        if (! array_key_exists($detectedMime, $policy['mimes'])) {
            throw ValidationException::withMessages([
                $fieldName => ["The file type ({$detectedMime}) is not permitted for {$category} uploads."],
            ]);
        }

        // 5. Cross-check extension against detected MIME (prevent extension spoofing)
        $allowedExtensionsForMime = $policy['mimes'][$detectedMime];
        if (! in_array($rawExt, $allowedExtensionsForMime, true)) {
            throw ValidationException::withMessages([
                $fieldName => ["File extension .{$rawExt} does not match actual file content ({$detectedMime})."],
            ]);
        }

        // 6. SVG Security Sanitization
        if ($detectedMime === 'image/svg+xml' || $rawExt === 'svg') {
            try {
                SvgSanitizer::sanitizeUploadedFile($file);
                clearstatcache(true, $realPath);
                $sizeBytes = filesize($realPath) ?: $sizeBytes;
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    $fieldName => ['The SVG file contains unsafe content or invalid XML: '.$e->getMessage()],
                ]);
            }
        }

        // 7. Extract image dimensions if applicable
        $width = null;
        $height = null;
        $isImage = str_starts_with($detectedMime, 'image/');

        if ($isImage && $detectedMime !== 'image/svg+xml') {
            $dimensions = @getimagesize($realPath);
            if ($dimensions) {
                $width = (int) $dimensions[0];
                $height = (int) $dimensions[1];
            }
        }

        $user = Auth::user();
        $safeOriginalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeOriginalName = preg_replace('/[^a-zA-Z0-9_\-\s]/', '_', $safeOriginalName).'.'.$rawExt;

        return [
            'original_name' => $safeOriginalName,
            'client_name' => $file->getClientOriginalName(),
            'extension' => $rawExt,
            'mime_type' => $detectedMime,
            'size' => $sizeBytes,
            'width' => $width,
            'height' => $height,
            'is_image' => $isImage,
            'uploaded_by' => $user?->name,
            'uploaded_by_id' => $user?->id,
        ];
    }
}
