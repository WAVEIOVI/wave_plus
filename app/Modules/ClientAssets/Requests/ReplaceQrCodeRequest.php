<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_qr_code' => 'nullable|string|max:100|unique:equipment_qr_codes,qr_code',
            'qr_code' => 'nullable|string|max:100|unique:equipment_qr_codes,qr_code',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
