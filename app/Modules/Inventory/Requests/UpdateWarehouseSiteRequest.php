<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->route('site') ?? $this->route('id');

        return [
            'code' => ['sometimes', 'string', 'max:20', "unique:warehouse_sites,code,{$siteId}"],
            'name' => ['sometimes', 'array'],
            'name.en' => ['sometimes', 'string', 'max:255'],
            'name.fr' => ['nullable', 'string', 'max:255'],
            'name.ar' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'manager_id' => ['ulid', 'nullable', 'exists:users,id'],
            'status' => ['nullable', 'in:active,inactive,maintenance'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
