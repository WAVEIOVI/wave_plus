<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateInterventionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment_id' => 'nullable|ulid|exists:client_equipment,id',
            'blueprint_id' => 'nullable|ulid|exists:equipment_blueprints,id',
            'service_type' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:40',
            'result' => 'nullable|string|in:pass,fail,conditional_pass',
            'technician_notes' => 'nullable|string',
            'technical_observations' => 'nullable|string',
            'measurements' => 'nullable|array',
            'materials' => 'nullable|array',
            'findings' => 'nullable|array',
        ];
    }
}
