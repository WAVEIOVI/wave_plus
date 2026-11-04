<?php

declare(strict_types=1);

namespace App\Modules\Hr\Requests;

use App\Modules\Hr\Support\TranslatableFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Modules\Hr\Models\Employee::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        TranslatableFields::normalizeRequest($this, ['first_name', 'middle_name', 'last_name', 'preferred_name']);

        // Frontend payload aliases -> canonical hr_employees columns.
        if ($this->has('email') && ! $this->has('work_email')) {
            $this->merge(['work_email' => $this->input('email')]);
        }
        if ($this->has('phone') && ! $this->has('work_phone')) {
            $this->merge(['work_phone' => $this->input('phone')]);
        }
        if ($this->has('phone_number') && ! $this->has('work_phone')) {
            $this->merge(['work_phone' => $this->input('phone_number')]);
        }
        if ($this->has('team_id') && ! $this->has('current_team_id')) {
            $this->merge(['current_team_id' => $this->input('team_id')]);
        }
    }

    public function rules(): array
    {
        return [
            ...TranslatableFields::rules('first_name', true, 100),
            ...TranslatableFields::rules('middle_name', false, 100),
            ...TranslatableFields::rules('last_name', true, 100),
            ...TranslatableFields::rules('preferred_name', false, 100),
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'work_email' => ['nullable', 'email', 'max:150'],
            'personal_email' => ['nullable', 'email', 'max:150'],
            'work_phone' => ['nullable', 'string', 'max:50'],
            'personal_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'hire_date' => ['required', 'date'],
            'termination_date' => ['required_if:status,terminated', 'nullable', 'date'],
            'status' => ['nullable', 'in:active,on_leave,suspended,terminated'],
            'department_id' => ['ulid', 'nullable', 'exists:hr_departments,id'],
            'position_id' => ['ulid', 'nullable', 'exists:hr_positions,id'],
            'manager_id' => ['ulid', 'nullable', 'exists:hr_employees,id'],
            'current_team_id' => ['ulid', 'nullable', 'exists:hr_work_teams,id'],
            'user_id' => ['ulid', 'nullable', 'exists:users,id', 'unique:hr_employees,user_id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
