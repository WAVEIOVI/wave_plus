<?php

namespace App\Modules\ClientAssets\Requests;

use App\Modules\ClientAssets\Requests\Concerns\WorkwearEmployeeProfileRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkwearEmployeeRequest extends FormRequest
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
            'employee_ref' => 'nullable|string|max:50',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:100',
            'role' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,inactive,terminated',
            'notes' => 'nullable|string|max:5000',
        ] + $this->profileRules() + $this->modelAssignmentRules();
    }
}
