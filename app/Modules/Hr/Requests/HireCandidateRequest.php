<?php

declare(strict_types=1);

namespace App\Modules\Hr\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HireCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['ulid', 'required', 'exists:hr_departments,id'],
            'position_id' => ['ulid', 'required', 'exists:hr_positions,id'],
            'hire_date' => ['required', 'date'],
            'manager_id' => ['ulid', 'nullable', 'exists:hr_employees,id'],
            'work_email' => ['nullable', 'email', 'max:150'],
            'work_phone' => ['nullable', 'string', 'max:50'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contractor,intern'],
            'contract_type' => ['nullable', 'in:permanent,fixed_term,temporary,probation'],
        ];
    }
}
