<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWorkshopTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workshop_unit_id' => 'required|ulid|exists:workshop_units,id',
            'destination_workshop_unit_id' => 'nullable|ulid|exists:workshop_units,id',
            'equipment_ids' => 'nullable|array',
            'equipment_ids.*' => 'ulid|exists:client_equipment,id',
            'equipment_id' => 'nullable|ulid|exists:client_equipment,id',
            'items' => 'nullable|array',
            'items.*.equipment_id' => 'required_with:items|string|exists:client_equipment,id',
            'items.*.condition' => 'nullable|string|max:50',
            'items.*.notes' => 'nullable|string',
            'transfer_date' => 'nullable|date',
            'reason' => 'nullable|string|max:500',
            'responsible_employee_id' => 'nullable|ulid|exists:hr_employees,id',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'condition' => 'nullable|string|max:50',
            'visible_damages' => 'nullable|string',
            'accessories_received' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }
}
