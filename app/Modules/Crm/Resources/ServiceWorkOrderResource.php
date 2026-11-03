<?php

declare(strict_types=1);

namespace App\Modules\Crm\Resources;

use App\Modules\BusinessPartners\Resources\ClientResource;
use App\Modules\BusinessPartners\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Crm\Models\ServiceWorkOrder
 */
class ServiceWorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'swo_number' => $this->swo_number,
            'sales_order_id' => $this->sales_order_id,
            'lsa_id' => $this->lsa_id,
            'client_id' => $this->client_id,
            'service_site_id' => $this->service_site_id,
            'assigned_team_id' => $this->assigned_team_id,
            'status' => $this->status,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'priority' => $this->priority,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'client' => new ClientResource($this->whenLoaded('client')),
            'assigned_team' => new UserResource($this->whenLoaded('assignedTeam')),
            'lsa_document' => new LsaDocumentResource($this->whenLoaded('lsaDocument')),
            'items' => $this->whenLoaded('items'),
        ];
    }
}
