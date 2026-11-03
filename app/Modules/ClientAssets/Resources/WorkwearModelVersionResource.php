<?php

namespace App\Modules\ClientAssets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkwearModelVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version_number' => $this->version_number,
            'snapshot' => $this->snapshot,
            'change_summary' => $this->change_summary,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
