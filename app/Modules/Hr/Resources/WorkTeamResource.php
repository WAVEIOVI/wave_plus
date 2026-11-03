<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->getTranslations('name'),
            'department_id' => $this->department_id,
            'department' => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->getTranslations('name'),
                'code' => $this->department->code,
            ] : null,
            'team_leader_id' => $this->team_leader_id,
            'team_leader_name' => $this->teamLeader?->full_name,
            'leader' => $this->teamLeader ? [
                'id' => $this->teamLeader->id,
                'first_name' => $this->teamLeader->getTranslations('first_name'),
                'last_name' => $this->teamLeader->getTranslations('last_name'),
                'full_name' => $this->teamLeader->full_name,
            ] : null,
            'team_type' => $this->team_type,
            'is_active' => $this->is_active,
            'status' => $this->is_active ? 'active' : 'inactive',
            'description' => $this->getTranslations('description'),
            'members_count' => $this->members_count ?? $this->activeMembers->count(),
            'members' => $this->whenLoaded('activeMembers', fn () => $this->activeMembers->map(fn ($m) => [
                'id' => $m->id,
                'employee_id' => $m->employee_id,
                'full_name' => $m->employee?->full_name,
                'first_name' => $m->employee?->getTranslations('first_name') ?? [],
                'last_name' => $m->employee?->getTranslations('last_name') ?? [],
                'employee_number' => $m->employee?->employee_number,
                'position' => $m->employee?->position?->getTranslations('title') ?? [],
                'role_in_team' => $m->role_in_team,
                'is_primary' => $m->is_primary,
                'joined_at' => $m->joined_at?->toDateString(),
                'left_at' => $m->left_at?->toDateString(),
                'employee' => $m->employee ? [
                    'id' => $m->employee->id,
                    'full_name' => $m->employee->full_name,
                ] : null,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
