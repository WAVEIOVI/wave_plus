<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->getTranslations('name'),
            'category' => $this->category,
            'description' => $this->getTranslations('description'),
            'is_active' => $this->is_active,
            'employees_count' => $this->employee_skills_count ?? 0,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
