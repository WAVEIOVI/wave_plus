<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Requests;

use App\Modules\ClientAssets\Models\ClientSiteArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateClientSiteAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_area_id' => 'nullable|ulid|exists:client_site_areas,id',
            'code' => 'nullable|string|max:60',
            'name' => 'sometimes|required|string|max:255',
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
            $areaId = $this->route('area') ?: $this->route('id');
            $parentId = $this->input('parent_area_id');

            if ($areaId && $parentId) {
                if ($areaId === $parentId) {
                    $validator->errors()->add('parent_area_id', 'An area cannot be its own parent.');

                    return;
                }

                $area = ClientSiteArea::with('children.children')->find($areaId);
                $parent = ClientSiteArea::find($parentId);

                if ($area && $parent) {
                    if ($area->site_id !== $parent->site_id) {
                        $validator->errors()->add('parent_area_id', 'The parent area must belong to the same site.');

                        return;
                    }

                    // Check if parent is a descendant of current area (circular hierarchy)
                    $descendantIds = $area->getAllDescendantIds();
                    if (in_array($parentId, $descendantIds, true)) {
                        $validator->errors()->add('parent_area_id', 'Cannot move an area into one of its own sub-areas.');
                    }
                }
            }
        });
    }
}
