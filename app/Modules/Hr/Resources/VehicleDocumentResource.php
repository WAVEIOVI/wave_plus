<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Hr\Models\VehicleDocument
 */
class VehicleDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'document_type' => $this->document_type,
            'title' => $this->title,
            'document_number' => $this->document_number,
            'issuer' => $this->issuer,
            'issue_date' => $this->issue_date ? \Illuminate\Support\Carbon::parse($this->issue_date)->format('Y-m-d') : null,
            'expiry_date' => $this->expiry_date ? \Illuminate\Support\Carbon::parse($this->expiry_date)->format('Y-m-d') : null,
            'reminder_date' => $this->reminder_date ? \Illuminate\Support\Carbon::parse($this->reminder_date)->format('Y-m-d') : null,
            'status' => $this->status,
            'file_path' => $this->file_path,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'download_url' => $this->getDownloadUrl(),
            'has_file' => $this->hasMedia('document') || ! empty($this->file_path),
            'media' => ($m = $this->getFirstMedia('document')) ? \App\Modules\Shared\Helpers\MediaFormatter::format($m) : null,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'is_expired' => $this->is_expired,
            'is_expiring_soon' => $this->is_expiring_soon,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

}
