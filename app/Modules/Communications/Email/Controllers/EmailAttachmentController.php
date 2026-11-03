<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communications\Email\Models\EmailAttachment;
use App\Modules\Communications\Email\Services\EmailAccessControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class EmailAttachmentController extends Controller
{
    public function __construct(
        private readonly EmailAccessControlService $accessControl,
    ) {}

    /**
     * Securely download an email attachment after verifying access to the parent message.
     */
    public function download(Request $request, EmailAttachment $attachment): Response
    {
        $user = $request->user();
        $message = $attachment->message;

        if (! $message || ! $this->accessControl->canAccessMessage($user, $message)) {
            abort(403, 'Unauthorized access to this email attachment.');
        }

        $disk = $attachment->storage_disk ?: 'local';

        if ($attachment->storage_path && Storage::disk($disk)->exists($attachment->storage_path)) {
            return Storage::disk($disk)->download(
                $attachment->storage_path,
                $attachment->filename,
                [
                    'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            );
        }

        if (! empty($attachment->content_b64)) {
            $data = base64_decode($attachment->content_b64, true);
            if ($data !== false) {
                return response($data, 200, [
                    'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
                    'Content-Disposition' => sprintf('attachment; filename="%s"', addslashes($attachment->filename)),
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        abort(404, 'Attachment file not found on server.');
    }
}
