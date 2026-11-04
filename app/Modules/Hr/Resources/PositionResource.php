<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canViewSalary = $user && (
            $user->hasRole(['Super Admin', 'Admin', 'HR Manager'])
            || $user->can('hr.employees.view-sensitive')
            || $user->can('hr.positions.manage')
        );

        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->getTranslations('title'),
            'description' => $this->getTranslations('description'),
            'standard_hourly_rate' => $canViewSalary && $this->standard_hourly_rate !== null ? (float) $this->standard_hourly_rate : null,
            'requirements' => $this->getTranslations('requirements'),
            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,
            'department' => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->getTranslations('name'),
                'code' => $this->department->code,
            ] : null,
            'status' => $this->status,
            'employees_count' => $this->employees_count ?? 0,
            'employees' => $this->whenLoaded('employees', fn () => $this->employees->map(fn ($employee) => [
                'id' => $employee->id,
                'first_name' => $employee->getTranslations('first_name'),
                'last_name' => $employee->getTranslations('last_name'),
                'full_name' => $employee->full_name,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
