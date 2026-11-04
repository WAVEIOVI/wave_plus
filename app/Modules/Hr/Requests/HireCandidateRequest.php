<?php

declare(strict_types=1);

namespace App\Modules\Hr\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HireCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->hasRole(['Super Admin', 'Admin']) || $user->can('hr.recruitment.hire'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && ! $this->has('work_email')) {
            $this->merge(['work_email' => $this->input('email')]);
        }
        if ($this->has('phone') && ! $this->has('work_phone')) {
            $this->merge(['work_phone' => $this->input('phone')]);
        }
    }

    public function rules(): array
    {
        return [
            'department_id' => ['ulid', 'required', 'exists:hr_departments,id'],
            'position_id' => ['ulid', 'required', 'exists:hr_positions,id'],
            'hire_date' => ['required', 'date'],
            'manager_id' => ['ulid', 'nullable', 'exists:hr_employees,id'],
            'work_email' => ['nullable', 'email', 'max:150', 'unique:hr_employees,work_email'],
            'work_phone' => ['nullable', 'string', 'max:50'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contractor,intern'],
            'contract_type' => ['nullable', 'in:permanent,fixed_term,temporary,probation'],
        ];
    }
}
