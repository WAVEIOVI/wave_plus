<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $uomId = $this->route('unitOfMeasure');

        return [
            'code' => "nullable|string|max:20|unique:units_of_measure,code,{$uomId}",
            'name' => 'nullable|array',
            'name.*' => 'nullable|string|max:255',
            'type' => 'nullable|in:count,mass,volume,length,service',
        ];
    }
}
