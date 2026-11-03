<?php

namespace App\Modules\BusinessPartners\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\BusinessPartners\Models\BpDocument
 */
class BpDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'documentable_type' => $this->documentable_type,
            'documentable_id' => $this->documentable_id,
            'uploaded_by' => $this->uploaded_by,
            'uploaded_by_user' => $this->whenLoaded('uploadedBy'),
            'verified_by' => $this->verified_by,
            'verified_by_user' => $this->whenLoaded('verifiedBy'),
            'type' => $this->type,
            'title' => $this->title,
            'reference_number' => $this->reference_number,
            'issue_date' => $this->issue_date?->toDateString(),
            'effective_date' => $this->effective_date?->toDateString(),
            'expiration_date' => $this->expiration_date?->toDateString(),
            'auto_renewable' => $this->auto_renewable,
            'status' => $this->status,
            'is_verified' => $this->is_verified,
            'file_name' => $this->file_name,
            'file_url' => $this->hasMedia('document') ? $this->getFirstMediaUrl('document') : null,
            'download_url' => $this->hasMedia('document') ? url("/api/bp/documents/{$this->id}/download") : null,
            'mime_type' => $this->mime_type,
            'file_size_bytes' => $this->file_size_bytes,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'source' => $this->source,
            'page_count' => $this->page_count,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}