<?php

declare(strict_types=1);

namespace App\Modules\Hr\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleDocument;
use App\Modules\Hr\Resources\VehicleDocumentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetDocumentController extends Controller
{
    use \App\Modules\Hr\Concerns\AuthorizesHrMutations;

    public function index(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $vehicleModel = Vehicle::findOrFail($vehicle);
        $query = $vehicleModel->documents();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('document_type')) {
            $query->where('document_type', $request->string('document_type')->toString());
        }

        $records = $query->orderByDesc('expiry_date')->paginate(min(max((int) $request->input('per_page', 15), 1), 100));

        return response()->json([
            'data' => VehicleDocumentResource::collection($records->items()),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function show(string $document): JsonResponse
    {
        $this->authorizeHrRead('hr.fleet.view');

        $record = VehicleDocument::findOrFail($document);

        return response()->json(['data' => VehicleDocumentResource::make($record)]);
    }

    public function store(Request $request, string $vehicle): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');
        $vehicleModel = Vehicle::findOrFail($vehicle);

        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'reminder_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,expired,pending_renewal,archived'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'file' => ['nullable', 'file'],
        ]);

        $hasFile = $request->hasFile('file');
        $fileMeta = null;

        if ($hasFile) {
            $fileMeta = \App\Modules\Shared\Services\MediaValidationService::validateAndProcess(
                $request->file('file'),
                \App\Modules\Shared\Services\MediaValidationService::CATEGORY_DOCUMENT,
                'file'
            );
        }

        unset($data['file']);
        $data['vehicle_id'] = $vehicleModel->id;
        $data['status'] ??= 'active';
        $data['created_by'] = $request->user()?->id;

        $record = VehicleDocument::create($data);

        if ($hasFile) {
            $media = $record->addMediaFromRequest('file')
                ->usingFileName($fileMeta['original_name'])
                ->withCustomProperties([
                    'uploaded_by' => $fileMeta['uploaded_by'],
                    'uploaded_by_id' => $fileMeta['uploaded_by_id'],
                    'document_type' => $record->document_type,
                    'title' => $record->title,
                ])
                ->toMediaCollection('document');

            $record->update([
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'file_size' => $media->size,
                'file_path' => $record->getDownloadUrl(),
            ]);
        }

        return response()->json(['data' => VehicleDocumentResource::make($record->fresh())], 201);
    }

    public function update(Request $request, string $document): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.manage');
        $record = VehicleDocument::findOrFail($document);

        $data = $request->validate([
            'document_type' => ['sometimes', 'string', 'max:50'],
            'title' => ['sometimes', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'reminder_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,expired,pending_renewal,archived'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'file' => ['nullable', 'file'],
        ]);

        $hasFile = $request->hasFile('file');
        $fileMeta = null;

        if ($hasFile) {
            $fileMeta = \App\Modules\Shared\Services\MediaValidationService::validateAndProcess(
                $request->file('file'),
                \App\Modules\Shared\Services\MediaValidationService::CATEGORY_DOCUMENT,
                'file'
            );
        }

        unset($data['file']);
        $data['updated_by'] = $request->user()?->id;
        $record->update($data);

        if ($hasFile) {
            $record->clearMediaCollection('document');
            $media = $record->addMediaFromRequest('file')
                ->usingFileName($fileMeta['original_name'])
                ->withCustomProperties([
                    'uploaded_by' => $fileMeta['uploaded_by'],
                    'uploaded_by_id' => $fileMeta['uploaded_by_id'],
                    'document_type' => $record->document_type,
                    'title' => $record->title,
                ])
                ->toMediaCollection('document');

            $record->update([
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'file_size' => $media->size,
                'file_path' => $record->getDownloadUrl(),
            ]);
        }

        return response()->json(['data' => VehicleDocumentResource::make($record->fresh())]);
    }

    public function download(string $document)
    {
        $this->authorizeHrRead('hr.fleet.view');
        $record = VehicleDocument::findOrFail($document);
        $media = $record->getFirstMedia('document');

        if ($media) {
            return $media->toResponse(request());
        }

        if ($record->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($record->file_path)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->download($record->file_path, $record->file_name ?: 'document.pdf');
        }

        abort(404, 'No file attached to this vehicle document.');
    }

    public function destroy(string $document): JsonResponse
    {
        $this->authorizeHrMutation('hr.fleet.archive');
        $record = VehicleDocument::findOrFail($document);
        $record->clearMediaCollection('document');
        $record->delete();

        return response()->json(['message' => 'Vehicle document deleted']);
    }
}

