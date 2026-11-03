<?php

namespace App\Modules\Catalog\Traits;

use App\Modules\Catalog\Jobs\SendPdfEmailJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared email-sending actions for Catalog controllers.
 *
 * @property string $partnerType (e.g. 'catalog_item')
 * @property string $modelClass (e.g. CatalogItem::class)
 */
trait HasPartnerActions
{
    /**
     * Dispatch job to email the catalog item list PDF.
     */
    public function sendEmailList(Request $request): JsonResponse
    {
        $this->authorize('viewAny', $this->modelClass);

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
     * Dispatch job to email a single catalog item record PDF.
     */
    public function sendEmailSingle(Request $request, string $catalogItem): JsonResponse
    {
        $record = ($this->modelClass)::findOrFail($catalogItem);
        $this->authorize('view', $record);

        $validated = $request->validate([
            'emailTo' => 'required|array',
            'emailTo.*' => 'email',
            'emailCc' => 'nullable|array',
            'emailCc.*' => 'email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        SendPdfEmailJob::dispatch($this->partnerType, 'single', $catalogItem, $validated);

        return response()->json([
            'message' => 'Email queued successfully.',
        ]);
    }
}
