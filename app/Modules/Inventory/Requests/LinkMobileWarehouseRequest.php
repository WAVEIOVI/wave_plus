<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkMobileWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => 'required|ulid|exists:vehicles,id',
            'assigned_from' => 'required|date',
            'assigned_until' => 'nullable|date|after_or_equal:assigned_from',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
