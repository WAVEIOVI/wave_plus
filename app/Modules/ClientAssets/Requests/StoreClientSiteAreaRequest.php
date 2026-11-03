<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Requests;

use App\Modules\ClientAssets\Models\ClientSiteArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreClientSiteAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => 'sometimes|required|ulid|exists:client_sites,id',
            'parent_area_id' => 'nullable|ulid|exists:client_site_areas,id',
            'code' => 'nullable|string|max:60',
            'name' => 'required|string|max:255',
            'area_type' => 'nullable|string|max:50',
            'classification' => 'nullable|string|max:50',
            'accessibility' => 'nullable|string|max:50',
            'level_number' => 'nullable|integer',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive',
            'sort_order' => 'nullable|integer',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $siteId = $this->route('site') ?: $this->input('site_id');
            $parentId = $this->input('parent_area_id');

            if ($siteId && $parentId) {
                $parentArea = ClientSiteArea::find($parentId);
                if ($parentArea && $parentArea->site_id !== $siteId) {
                    $validator->errors()->add('parent_area_id', 'The parent area must belong to the same site.');
                }
            }
        });
    }
}
