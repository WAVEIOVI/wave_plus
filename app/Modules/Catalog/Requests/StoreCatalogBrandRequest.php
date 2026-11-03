<?php

namespace App\Modules\Catalog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:40|unique:catalog_brands,code',
            'country_of_origin' => 'nullable|string|size:2',
            'warranty_policy_notes' => 'nullable|string',
            'logo_source' => 'nullable|string|in:brandfetch,logo_dev,upload,initials',
            'logo_domain' => 'nullable|string|max:255',
            'logo_external_url' => 'nullable|url|max:2048',
            'logo' => 'nullable|file|max:10240',
        ];
    }

}
