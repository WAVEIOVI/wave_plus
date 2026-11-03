<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'first_name' => $this->getTranslations('first_name'),
            'middle_name' => $this->getTranslations('middle_name'),
            'last_name' => $this->getTranslations('last_name'),
            'full_name' => $this->full_name,
            'preferred_name' => $this->getTranslations('preferred_name'),
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'nationality' => $this->nationality,
            'work_email' => $this->work_email,
            'personal_email' => $this->personal_email,
            'email' => $this->work_email ?? $this->personal_email,
            'work_phone' => $this->work_phone,
            'personal_phone' => $this->personal_phone,
            'phone' => $this->work_phone ?? $this->personal_phone,
            'phone_number' => $this->work_phone ?? $this->personal_phone,
            'address' => $this->address,
            'hire_date' => $this->hire_date?->toDateString(),
            'termination_date' => $this->termination_date?->toDateString(),
            'status' => $this->status,
            'avatar_url' => $this->avatar_url,
            'department_id' => $this->department_id,
            'department' => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->getTranslations('name'),
                'code' => $this->department->code,
            ] : null,
            'position_id' => $this->position_id,
            'job_title' => $this->position?->title,
            'position' => $this->position ? [
                'id' => $this->position->id,
                'title' => $this->position->getTranslations('title'),
                'code' => $this->position->code,
            ] : null,
            'manager_id' => $this->manager_id,
            'manager' => $this->manager ? [
                'id' => $this->manager->id,
                'full_name' => $this->manager->full_name,
                'employee_number' => $this->manager->employee_number,
            ] : null,
            'current_team_id' => $this->current_team_id,
            'team_id' => $this->current_team_id,
            'current_team' => $this->currentTeam ? [
                'id' => $this->currentTeam->id,
                'name' => $this->currentTeam->getTranslations('name'),
                'code' => $this->currentTeam->code,
            ] : null,
            'team' => $this->currentTeam ? [
                'id' => $this->currentTeam->id,
                'name' => $this->currentTeam->getTranslations('name'),
                'code' => $this->currentTeam->code,
            ] : null,
            'user_id' => $this->user_id,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
