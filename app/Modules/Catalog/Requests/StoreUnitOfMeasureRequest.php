<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:20|unique:units_of_measure,code',
            'name' => 'required|array',
            'name.*' => 'required|string|max:255',
            'type' => 'required|in:count,mass,volume,length,service',
        ];
    }
}
