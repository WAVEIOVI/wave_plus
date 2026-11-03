<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkshopUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nameTranslations = $this->getTranslations('name');
        $descriptionTranslations = $this->getTranslations('description');

        return [
            'id'                      => $this->id,
            'code'                    => $this->code,
            // Translation maps are kept for i18n-aware inputs; display_* are ready-to-render strings.
            'name'                    => $nameTranslations,
            'name_display'            => $this->resolveTranslation($nameTranslations),
            'description'             => $descriptionTranslations,
            'description_display'     => $this->resolveTranslation($descriptionTranslations),
            'type'                    => [
                'value' => $this->type?->value ?? (string) $this->type,
                'label' => $this->type?->label() ?? ucfirst((string) $this->type),
                'color' => $this->type?->badgeColor() ?? 'primary',
            ],
            'status'                  => [
                'value' => $this->status?->value ?? (string) $this->status,
                'label' => $this->status?->label() ?? ucfirst((string) $this->status),
                'color' => $this->status?->badgeColor() ?? 'secondary',
            ],
            'location'                => $this->location,
            'vehicle_id'              => $this->vehicle_id,
            'vehicle'                 => $this->whenLoaded('vehicle', fn () => [
                'id'           => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'label'        => $this->vehicle->label,
            ]),
            'capacity'                => (int) ($this->capacity ?? 1),
            'capacity_notes'          => $this->capacity_notes,
            'active_receptions_count' => (int) ($this->active_receptions_count ?? 0),
            'swos_count'              => $this->swos_count ?? $this->swos?->count() ?? 0,
            'missions_count'          => $this->missions_count ?? $this->missions?->count() ?? 0,
            'receptions_count'        => $this->receptions_count ?? $this->receptions?->count() ?? 0,
            'created_at'              => $this->created_at?->toISOString(),
        ];
    }

    /**
     * @param  array<string, string|null>  $translations
     */
    private function resolveTranslation(array $translations): ?string
    {
        if ($translations === []) {
            return null;
        }

        $locale = app()->getLocale();

        return $translations[$locale]
            ?? $translations['en']
            ?? $translations['fr']
            ?? (reset($translations) ?: null);
    }
}
