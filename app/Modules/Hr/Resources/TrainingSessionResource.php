<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();
        $data['trainer'] = $this->getTranslations('trainer');

        if ($this->resource->relationLoaded('program') && $this->resource->program) {
            $data['program'] = (new TrainingProgramResource($this->resource->program))->resolve($request);
        }

        return $data;
    }
}
