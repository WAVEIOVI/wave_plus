<?php

declare(strict_types=1);

namespace App\Modules\Hr\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClockEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('event_type')) {
            if ($this->is('*clock-in')) {
                $this->merge(['event_type' => 'in']);
            } elseif ($this->is('*clock-out')) {
                $this->merge(['event_type' => 'out']);
            }
        }

        if ($this->has('timestamp') && ! $this->has('event_time')) {
            $this->merge(['event_time' => $this->input('timestamp')]);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['ulid', 'nullable', 'exists:hr_employees,id'],
            'event_type' => ['required', 'in:in,out'],
            'event_time' => ['nullable', 'date'],
            'source' => ['nullable', 'in:web,mobile,manual,qr,pin,device,biometric'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
