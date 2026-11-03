<?php

namespace App\Modules\BusinessPartners\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusinessPartners\Controllers\Concerns\AuthorizesPartnerRequests;
use App\Modules\BusinessPartners\Models\BpDocument;
use App\Modules\BusinessPartners\Requests\StoreBpDocumentRequest;
use App\Modules\BusinessPartners\Requests\UpdateBpDocumentRequest;
use App\Modules\BusinessPartners\Resources\BpDocumentResource;
use App\Modules\Shared\Services\MediaValidationService;
use Illuminate\Http\JsonResponse;

class BpDocumentController extends Controller
{
    use AuthorizesPartnerRequests;

    public function index(string $ownerType, string $ownerId): JsonResponse
    {
        $this->authorizePartnerView($ownerType, $ownerId);

        $documents = BpDocument::where('documentable_type', $this->morphClass($ownerType))
            ->where('documentable_id', $ownerId)
            ->with(['uploadedBy', 'verifiedBy'])
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => BpDocumentResource::collection($documents),
        ]);
    }

    public function store(StoreBpDocumentRequest $request, string $ownerType, string $ownerId): JsonResponse
    {
        $owner = $this->authorizePartnerUpdate($ownerType, $ownerId);

        $data = $request->validated();
        $data['documentable_type'] = $owner::class;
        $data['documentable_id'] = $owner->getKey();
        $data['uploaded_by'] = $request->user()?->id;
        unset($data['file']);

        $document = BpDocument::create($data);

        if ($request->hasFile('file')) {
            $this->attachDocumentFile($document, $request);
        }

        return response()->json([
            'message' => 'Document created successfully.',
            'data' => new BpDocumentResource($document->fresh()),
        ], 201);
    }

    public function show(string $document): JsonResponse
    {
        $record = BpDocument::with(['uploadedBy', 'verifiedBy'])->findOrFail($document);
        $this->authorizeAddressable($record, 'view');

        return response()->json([
            'data' => new BpDocumentResource($record),
        ]);
    }

    public function update(UpdateBpDocumentRequest $request, string $document): JsonResponse
    {
        $record = BpDocument::findOrFail($document);
        $this->authorizeAddressable($record, 'update');

        $data = $request->validated();
        unset($data['file']);
        $record->update($data);

        if ($request->hasFile('file')) {
            $this->attachDocumentFile($record, $request);
        }

        return response()->json([
            'message' => 'Document updated successfully.',
            'data' => new BpDocumentResource($record->fresh()),
        ]);
    }

    /**
     * Attach the uploaded file through Spatie Media Library and derive every
     * file attribute from the stored media record. Client-supplied
     * mime_type / file_size_bytes / file_path are never trusted; page_count is
     * only computed when the client did not already supply a scanned count.
     */
    protected function attachDocumentFile(BpDocument $document, $request): void
    {
        $fileMeta = MediaValidationService::validateAndProcess(
            $request->file('file'),
            MediaValidationService::CATEGORY_DOCUMENT,
            'file'
        );

        $document->clearMediaCollection('document');
        $media = $document->addMediaFromRequest('file')
            ->usingFileName($fileMeta['original_name'])
            ->withCustomProperties([
                'uploaded_by' => $fileMeta['uploaded_by'],
                'uploaded_by_id' => $fileMeta['uploaded_by_id'],
            ])
            ->toMediaCollection('document');

        $downloadUrl = url("/api/bp/documents/{$document->id}/download");

        $attributes = [
            'file_path' => $downloadUrl,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'file_size_bytes' => $media->size,
        ];

        if (blank($document->page_count)) {
            $attributes['page_count'] = $this->detectPageCount($media->getPath(), $media->mime_type);
        }

        $document->update($attributes);
    }

    /**
     * Best-effort page count for PDFs. Returns null when it cannot be
     * determined reliably so uploads never fail because of this.
     */
    protected function detectPageCount(string $path, ?string $mimeType): ?int
    {
        if ($mimeType !== 'application/pdf' || ! is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $count = preg_match_all('/\/Type\s*\/Page[^s]/', $contents);

        return $count > 0 ? $count : null;
    }

    public function download(string $document)
    {
        $record = BpDocument::findOrFail($document);
        $this->authorizeAddressable($record, 'view');
        $media = $record->getFirstMedia('document');

        if (! $media) {
            abort(404, 'No file attached to this document.');
        }

        return $media->toResponse(request());
    }

    public function destroy(string $document): JsonResponse
    {
        $record = BpDocument::findOrFail($document);
        $this->authorizeAddressable($record, 'update');
        $record->delete();

        return response()->json([
            'message' => 'Document deleted successfully.',
        ]);
    }
}