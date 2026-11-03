<?php

declare(strict_types=1);

namespace App\Modules\ClientAssets\Requests;

use App\Modules\ClientAssets\Models\ClientEquipment;
use App\Modules\ClientAssets\Models\ClientSite;
use App\Modules\ClientAssets\Models\ClientSiteArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TransferEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_site_id' => 'nullable|ulid|exists:client_sites,id',
            'to_area_id' => 'nullable|ulid|exists:client_site_areas,id',
            'movement_type' => 'nullable|string|in:initial_placement,transfer,relocation,removal_to_storage,decommission',
            'reason' => 'nullable|string|max:255',
            'condition_at_transfer' => 'nullable|string|in:excellent,good,fair,poor,critical',
            'notes' => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $equipmentId = $this->route('eq') ?: $this->route('equipment') ?: $this->route('id');
            $equipment = is_string($equipmentId) ? ClientEquipment::find($equipmentId) : null;

            $toSiteId = $this->input('to_site_id');
            $toAreaId = $this->input('to_area_id');

            if ($toSiteId && $equipment) {
                $site = ClientSite::find($toSiteId);
                if ($site && $site->client_id !== $equipment->client_id) {
                    $validator->errors()->add('to_site_id', 'Target site does not belong to the equipment client.');
                }
            }

            if ($toSiteId && $toAreaId) {
                $area = ClientSiteArea::find($toAreaId);
                if ($area && $area->site_id !== $toSiteId) {
                    $validator->errors()->add('to_area_id', 'Target area does not belong to the target site.');
                }
            } elseif ($toAreaId && ! $toSiteId) {
                $validator->errors()->add('to_area_id', 'Target area cannot be specified without a target site.');
            }
        });
    }
}
