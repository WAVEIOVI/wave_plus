<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->getTranslations('name'),
            'issuing_authority' => $this->getTranslations('issuing_authority'),
            'description' => $this->getTranslations('description'),
            'validity_period_months' => $this->validity_period_months,
            'validity_months' => $this->validity_period_months,
            'is_active' => $this->is_active,
            'certified_employees_count' => $this->employeeCertifications()->where('status', 'valid')->count(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
