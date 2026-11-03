<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Requests;

use App\Modules\OperationalHub\Enums\MissionMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateMissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'swo_id' => 'required|ulid|exists:service_work_orders,id',
            'mode' => ['nullable', 'string', Rule::in(MissionMode::values())],
            'source_field_mission_id' => 'nullable|ulid|exists:missions,id',
            'lead_employee_id' => 'nullable|ulid|exists:hr_employees,id',
            'workshop_unit_id' => 'nullable|ulid|exists:workshop_units,id',
            'service_site_id' => 'nullable|ulid|exists:client_sites,id',
            'team_id' => 'nullable|ulid|exists:hr_work_teams,id',
            'lead_technician_id' => 'nullable|ulid|exists:hr_employees,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'ulid|exists:hr_employees,id',
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
            'checklist_template_id' => 'nullable|ulid|exists:checklist_templates,id',
            'planned_date' => 'nullable|date',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'planned_start_time' => 'nullable|string',
            'planned_duration_hours' => 'nullable|numeric|gte:0',
            'preparation_notes' => 'nullable|string|max:3000',
            'client_contact_name' => 'nullable|string|max:150',
        ];
    }
}
