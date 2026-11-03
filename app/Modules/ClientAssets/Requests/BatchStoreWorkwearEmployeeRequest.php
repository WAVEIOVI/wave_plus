<?php

namespace App\Modules\ClientAssets\Requests;

use App\Modules\ClientAssets\Requests\Concerns\WorkwearEmployeeProfileRules;
use Illuminate\Foundation\Http\FormRequest;

class BatchStoreWorkwearEmployeeRequest extends FormRequest
{
    use WorkwearEmployeeProfileRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|ulid|exists:clients,id',
            'employees' => 'required|array|min:1|max:500',
            'employees.*.employee_ref' => 'nullable|string|max:50',
            'employees.*.first_name' => 'required|string|max:100',
            'employees.*.last_name' => 'required|string|max:100',
            'employees.*.email' => 'nullable|email|max:150',
            'employees.*.phone' => 'nullable|string|max:50',
            'employees.*.department' => 'nullable|string|max:100',
            'employees.*.role' => 'nullable|string|max:100',
            'employees.*.position' => 'nullable|string|max:100',
            'employees.*.status' => 'nullable|in:active,inactive,terminated',
        ] + $this->profileRules('employees.*.');
    }
}
