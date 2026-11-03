<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWorkshopReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workshop_unit_id' => 'required|ulid|exists:workshop_units,id',
            'equipment_id' => 'required|ulid|exists:client_equipment,id',
            // client_id is derived from the equipment / source documents when omitted.
            'client_id' => 'nullable|ulid|exists:clients,id',
            'sales_order_id' => 'nullable|ulid|exists:sales_orders,id',
            'swo_id' => 'nullable|ulid|exists:service_work_orders,id',
            'source_mission_id' => 'nullable|ulid|exists:missions,id',
            'exit_pass_id' => 'nullable|ulid|exists:workshop_exit_passes,id',
            'received_by' => 'nullable|integer|exists:users,id',
            'reception_date' => 'nullable|date',
            'condition' => 'nullable|string|max:50',
            'visible_damages' => 'nullable|string',
            'accessories_received' => 'nullable|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:pending,received,in_diagnosis,in_progress,testing,completed,returned',
        ];
    }
}
