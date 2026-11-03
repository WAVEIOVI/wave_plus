<?php

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Controllers\Concerns\AuthorizesCatalogRequests;
use App\Modules\Catalog\Helpers\MediaFormatter;
use App\Modules\Catalog\Models\CatalogItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CatalogItemMediaController extends Controller
{
    use AuthorizesCatalogRequests;

    public function store(Request $request, string $catalogItem): JsonResponse
    {
        $item = CatalogItem::findOrFail($catalogItem);
        $this->authorize('update', $item);

        $request->validate([
            'collection' => 'required|string|in:images,documents,certificates',
            'file' => 'required|file',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'reference' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:active,draft,expired,revoked',
            'expires_at' => 'nullable|date',
        ]);

        $collection = $request->input('collection');
        $fileMeta = \App\Modules\Shared\Services\MediaValidationService::validateAndProcess(
            $request->file('file'),
            $collection,
            'file'
        );

        $customProperties = [
            'original_name' => $fileMeta['original_name'],
            'uploaded_by' => $fileMeta['uploaded_by'],
            'uploaded_by_id' => $fileMeta['uploaded_by_id'],
            'width' => $fileMeta['width'],
            'height' => $fileMeta['height'],
        ];

        // Store domain custom properties if provided
        $fields = ['title', 'notes', 'reference', 'type', 'status', 'expires_at'];
        foreach ($fields as $field) {
            if ($request->filled($field)) {
                $customProperties[$field] = $request->input($field);
            }
        }

        $media = $item->addMedia($request->file('file'))
            ->usingFileName($fileMeta['original_name'])
            ->withCustomProperties($customProperties)
            ->toMediaCollection($collection);

        return response()->json([
            'message' => 'File uploaded successfully.',
            'data' => MediaFormatter::format($media),
        ], 201);

    }

    /**
     * Download a specific media file.
     */
    public function download(string $catalogItem, Media $media)
    {
        $item = CatalogItem::findOrFail($catalogItem);
        $this->authorize('view', $item);

        if ($media->model_id !== $item->id || $media->model_type !== CatalogItem::class) {
            abort(403, 'Unauthorized file access.');
        }

        return $media->toResponse(request());
    }

    /**
     * Delete a specific media file.
     */
    public function destroy(string $catalogItem, Media $media): JsonResponse
    {
        $item = CatalogItem::findOrFail($catalogItem);
        $this->authorize('update', $item);

        if ($media->model_id !== $item->id || $media->model_type !== CatalogItem::class) {
            abort(403, 'Unauthorized file access.');
        }

        $media->delete();

        return response()->json([
            'message' => 'File deleted successfully.',
        ]);
    }
}
