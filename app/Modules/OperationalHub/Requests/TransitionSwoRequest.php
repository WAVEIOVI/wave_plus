<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransitionSwoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'              => 'required|string',
            'notes'               => 'nullable|string|max:2000',
            'cancellation_reason' => 'nullable|string|max:2000',
            'metadata'            => 'nullable|array',
        ];
    }
}
