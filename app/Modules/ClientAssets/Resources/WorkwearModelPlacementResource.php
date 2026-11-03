<?php

namespace App\Modules\ClientAssets\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkwearModelPlacementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'view' => $this->view,
            'type' => $this->type,
            'label' => $this->label,
            'zone' => $this->zone,
            'text' => $this->text,
            'method' => $this->method,
            'color' => $this->color,
            'width_mm' => $this->width_mm,
            'height_mm' => $this->height_mm,
            'offset_x_mm' => $this->offset_x_mm,
            'offset_y_mm' => $this->offset_y_mm,
            'media_id' => $this->media_id,
            'notes' => $this->notes,
            'sort_order' => $this->sort_order,
        ];
    }
}
