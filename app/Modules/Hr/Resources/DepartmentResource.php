<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->getTranslations('name'),
            'description' => $this->getTranslations('description'),
            'parent_department_id' => $this->parent_department_id,
            'parent_name' => $this->parent?->name,
            'manager_id' => $this->manager_id,
            'manager_name' => $this->manager?->full_name,
            'manager' => $this->manager ? [
                'id' => $this->manager->id,
                'full_name' => $this->manager->full_name,
                'employee_number' => $this->manager->employee_number,
            ] : null,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'employees_count' => $this->employees_count ?? $this->employees()->count(),
            'positions_count' => $this->positions_count ?? $this->positions()->count(),
            'teams_count' => $this->work_teams_count ?? $this->workTeams()->count(),
            'positions' => $this->whenLoaded('positions', fn () => PositionResource::collection($this->positions)->resolve()),
            'position_ids' => $this->relationLoaded('positions') ? $this->positions->pluck('id')->values()->all() : [],
            'children' => $this->whenLoaded('children', fn () => DepartmentResource::collection($this->children)->resolve()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
