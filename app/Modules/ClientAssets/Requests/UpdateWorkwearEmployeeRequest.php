<?php

namespace App\Modules\ClientAssets\Requests;

use App\Modules\ClientAssets\Models\WorkwearEmployee;
use App\Modules\ClientAssets\Requests\Concerns\WorkwearEmployeeProfileRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkwearEmployeeRequest extends FormRequest
{
    use WorkwearEmployeeProfileRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'sometimes|ulid|exists:clients,id',
            'employee_ref' => 'nullable|string|max:50',
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:100',
            'role' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'status' => 'sometimes|in:active,inactive,terminated',
            'notes' => 'nullable|string|max:5000',
        ] + $this->profileRules() + $this->modelAssignmentRules();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('client_id')) {
                return;
            }

            $routeEmp = $this->route('emp');
            $employee = $routeEmp instanceof WorkwearEmployee
                ? $routeEmp
                : WorkwearEmployee::query()->find((string) $routeEmp);

            // Assignments link items of the employee's client; moving the employee would orphan that history.
            if ($employee && $employee->client_id !== $this->input('client_id')
                && ($employee->assignments()->exists() || $employee->modelAssignments()->exists())) {
                $validator->errors()->add('client_id', 'The client cannot be changed because this employee has assignment history.');
            }
        });
    }
}
