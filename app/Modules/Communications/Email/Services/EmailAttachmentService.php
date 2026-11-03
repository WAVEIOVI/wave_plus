<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailAttachment;
use App\Modules\Communications\Email\Models\EmailMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Persists email attachments on the PRIVATE disk.
 *
 *  - Uploaded files (upload / photo / scan) are stored under email-attachments/{message}/.
 *  - Existing documents are authorised server-side and COPIED, so the sent email is a frozen snapshot.
 *
 * Paths and URLs are never returned to clients.
 */
class EmailAttachmentService
{
    public const DISK = 'local';

    public const MAX_FILES = 10;

    public const MAX_FILE_BYTES = 10 * 1024 * 1024;

    public const MAX_TOTAL_BYTES = 20 * 1024 * 1024;

    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'webp', 'gif'];

    public function __construct(
        private readonly EmailDocumentSearchService $documents,
    ) {}

    /**
     * Make the message attachments match: kept existing ids + new uploads + new document references.
     * Returns the storage paths of removed attachments so the caller can delete them after commit.
     *
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, array{source: string, id: string}>  $documentRefs
     * @param  array<int, int|string>|null  $keepIds  null = keep everything already attached
     * @return array<int, string> removed storage paths
     */
    public function sync(EmailMessage $message, User $user, array $files, array $documentRefs, ?array $keepIds = null): array
    {
        $existing = $message->attachments()->get();
        $keep = $keepIds === null ? $existing : $existing->whereIn('id', array_map('intval', $keepIds));
        $removed = $existing->diff($keep);

        $this->assertLimits($keep->count() + count($files) + count($documentRefs), (int) $keep->sum('size_bytes'), $files);

        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = $this->storeUpload($message, $file);
            }

            foreach ($documentRefs as $ref) {
                $stored[] = $this->copyDocument($message, $user, (string) $ref['source'], (string) $ref['id']);
            }

            $this->assertTotalSize((int) $keep->sum('size_bytes') + array_sum(array_map(fn (EmailAttachment $a) => $a->size_bytes, $stored)));
        } catch (\Throwable $e) {
            foreach ($stored as $attachment) {
                $this->deleteFile($attachment->storage_path);
                $attachment->delete();
            }

            throw $e;
        }

        $paths = [];
        foreach ($removed as $attachment) {
            if ($attachment->storage_path) {
                $paths[] = $attachment->storage_path;
            }
            $attachment->delete();
        }

        return $paths;
    }

    public function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * @param  array<int, string>  $paths
     */
    public function deleteFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->deleteFile($path);
        }
    }

    public function storeRawBytes(EmailMessage $message, string $bytes, string $filename, string $mimeType = 'application/pdf'): EmailAttachment
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'pdf');
        $path = "email-attachments/{$message->id}/".Str::random(40).'.'.$extension;

        Storage::disk(self::DISK)->put($path, $bytes);

        return EmailAttachment::create([
            'email_message_id' => $message->id,
            'filename' => $this->safeName($filename, $extension),
            'mime_type' => $mimeType,
            'source' => EmailAttachment::SOURCE_DOCUMENT,
            'size_bytes' => strlen($bytes),
            'storage_disk' => self::DISK,
            'storage_path' => $path,
        ]);
    }

    private function storeUpload(EmailMessage $message, UploadedFile $file): EmailAttachment
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['attachments' => ["File type .{$extension} is not allowed."]]);
        }

        $path = $file->storeAs("email-attachments/{$message->id}", Str::random(40).'.'.$extension, self::DISK);

        return EmailAttachment::create([
            'email_message_id' => $message->id,
            'filename' => $this->safeName($file->getClientOriginalName(), $extension),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'source' => EmailAttachment::SOURCE_UPLOAD,
            'size_bytes' => $file->getSize() ?: 0,
            'storage_disk' => self::DISK,
            'storage_path' => $path,
        ]);
    }

    private function copyDocument(EmailMessage $message, User $user, string $source, string $id): EmailAttachment
    {
        $document = $this->documents->resolveAttachable($user, $source, $id);

        if (! $document) {
            throw ValidationException::withMessages(['document_attachments' => ['A selected document is unavailable or you are not authorized to attach it.']]);
        }

        $media = $document->getFirstMedia('document');

        if (! $media || ! is_file($media->getPath())) {
            throw ValidationException::withMessages(['document_attachments' => ['A selected document has no readable file.']]);
        }

        $extension = strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['document_attachments' => ["File type .{$extension} cannot be attached."]]);
        }

        if ($media->size > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages(['document_attachments' => ["{$media->file_name} exceeds the 10 MB attachment limit."]]);
        }

        $path = "email-attachments/{$message->id}/".Str::random(40).'.'.$extension;
        $stream = fopen($media->getPath(), 'rb');

        try {
            Storage::disk(self::DISK)->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return EmailAttachment::create([
            'email_message_id' => $message->id,
            'filename' => $this->safeName($media->file_name, $extension),
            'mime_type' => $media->mime_type ?: 'application/octet-stream',
            'source' => EmailAttachment::SOURCE_DOCUMENT,
            'source_type' => $source,
            'source_id' => $id,
            'size_bytes' => (int) $media->size,
            'storage_disk' => self::DISK,
            'storage_path' => $path,
        ]);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function assertLimits(int $count, int $keptBytes, array $files): void
    {
        if ($count > self::MAX_FILES) {
            throw ValidationException::withMessages(['attachments' => ['You can attach at most '.self::MAX_FILES.' files.']]);
        }

        $newBytes = array_sum(array_map(fn (UploadedFile $f) => $f->getSize() ?: 0, $files));
        $this->assertTotalSize($keptBytes + $newBytes);
    }

    private function assertTotalSize(int $bytes): void
    {
        if ($bytes > self::MAX_TOTAL_BYTES) {
            throw ValidationException::withMessages(['attachments' => ['Total attachment size cannot exceed 20 MB.']]);
        }
    }

    private function safeName(string $name, string $extension): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = trim(preg_replace('/[^\p{L}\p{N}\-_. ]+/u', '_', $base) ?? '', ' ._');

        return Str::limit($base !== '' ? $base : 'attachment', 120, '').'.'.$extension;
    }
}
