<?php

namespace App\Modules\BusinessPartners\Traits;

use App\Modules\BusinessPartners\Jobs\SendPdfEmailJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared email-sending actions for all Business Partner controllers.
 *
 * @property string $partnerType (e.g. 'client', 'supplier', 'service_provider', 'organisation')
 * @property string $modelClass (e.g. Client::class)
 */
trait HasPartnerActions
{
    /**
     * Dispatch job to email the partner list PDF.
     */
    public function sendEmailList(Request $request): JsonResponse
    {
        $this->authorize('viewAny', $this->modelClass);

        $user = $request->user();
        if (! $user->hasRole('Super Admin') && ! $user->can('create-email-messages')) {
            abort(403, 'You are not authorized to compose or send emails.');
        }

        $validated = $request->validate([
            'emailTo' => 'required|array',
            'emailTo.*' => 'email',
            'emailCc' => 'nullable|array',
            'emailCc.*' => 'email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        SendPdfEmailJob::dispatch($this->partnerType, 'list', null, $validated);

        return response()->json([
            'message' => 'Email queued successfully.',
        ]);
    }

    /**
     * Dispatch job to email a single partner record PDF.
     */
    public function sendEmailSingle(Request $request, string $id): JsonResponse
    {
        $record = $this->modelClass::findOrFail($id);
        $this->authorize('view', $record);

        $user = $request->user();
        if (! $user->hasRole('Super Admin') && ! $user->can('create-email-messages')) {
            abort(403, 'You are not authorized to compose or send emails.');
        }

        $validated = $request->validate([
            'emailTo' => 'required|array',
            'emailTo.*' => 'email',
            'emailCc' => 'nullable|array',
            'emailCc.*' => 'email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        SendPdfEmailJob::dispatch($this->partnerType, 'single', $id, $validated);

        return response()->json([
            'message' => 'Email queued successfully.',
        ]);
    }
}
