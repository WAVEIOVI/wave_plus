<?php

declare(strict_types=1);

namespace App\Modules\Hr\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requested_actual_in' => ['nullable', 'date_format:H:i:s,H:i'],
            'requested_actual_out' => ['nullable', 'date_format:H:i:s,H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
