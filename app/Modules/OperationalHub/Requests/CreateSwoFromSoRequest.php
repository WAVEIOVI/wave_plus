<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Requests;

use App\Modules\OperationalHub\Enums\SwoFulfillmentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSwoFromSoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_order_id' => 'required|ulid|exists:sales_orders,id',
            'fulfillment_mode' => ['nullable', 'string', Rule::in(SwoFulfillmentMode::values())],
            'service_site_id' => 'nullable|ulid|exists:client_sites,id',
            'site_ids' => 'nullable|array',
            'site_ids.*' => 'ulid|exists:client_sites,id',
            'locations' => 'nullable|array',
            'locations.*.client_site_id' => 'required_with:locations|string|exists:client_sites,id',
            'team_id' => 'nullable|ulid|exists:hr_work_teams,id',
            'lead_technician_id' => 'nullable|ulid|exists:hr_employees,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'ulid|exists:hr_employees,id',
            'employees' => 'nullable|array',
            'workshop_unit_id' => 'nullable|ulid|exists:workshop_units,id',
            'vehicle_id' => 'nullable|ulid|exists:vehicles,id',
            'approved_driver_id' => 'nullable|ulid|exists:hr_approved_drivers,id',
            'mobile_warehouse_id' => 'nullable|ulid|exists:warehouse_sites,id',
            'checklist_template_id' => 'nullable|ulid|exists:checklist_templates,id',
            'scheduled_date' => 'nullable|date',
            'planned_date' => 'nullable|date',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date|after_or_equal:planned_start',
            'planned_start_time' => 'nullable|string',
            'planned_duration_hours' => 'nullable|numeric|gte:0',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'notes' => 'nullable|string|max:3000',
            'lines' => 'nullable|array',
            'lines.*.so_line_id' => 'required_with:lines|string|exists:sales_order_lines,id',
            'lines.*.qty' => 'nullable|numeric|gt:0',
            'lines.*.notes' => 'nullable|string|max:1000',
        ];
    }
}
