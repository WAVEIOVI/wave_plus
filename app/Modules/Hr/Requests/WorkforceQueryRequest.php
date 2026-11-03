<?php

declare(strict_types=1);

namespace App\Modules\Hr\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkforceQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'required_skill_ids' => ['nullable', 'array'],
            'required_skill_ids.*' => ['ulid', 'exists:hr_skills,id'],
            'required_certification_ids' => ['nullable', 'array'],
            'required_certification_ids.*' => ['ulid', 'exists:hr_certifications,id'],
            'team_id' => ['ulid', 'nullable', 'exists:hr_work_teams,id'],
            'department_id' => ['ulid', 'nullable', 'exists:hr_departments,id'],
        ];
    }
}
