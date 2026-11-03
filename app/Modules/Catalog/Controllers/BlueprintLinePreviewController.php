<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\EquipmentBlueprint;
use App\Modules\Catalog\Services\BlueprintLineBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlueprintLinePreviewController extends Controller
{
    public function __construct(
        private BlueprintLineBuilderService $lineBuilderService,
    ) {}

    public function resolveLines(Request $request, EquipmentBlueprint $blueprint): JsonResponse
    {
        $validated = $request->validate([
            'selected_option_values' => ['nullable', 'array'],
            'selected_option_values.*' => ['string'],
            'selected_servicings' => ['nullable', 'array'],
            'selected_servicings.*' => ['string'],
            'variant_selections' => ['nullable', 'array'],
            'equipment_qty' => ['nullable', 'numeric', 'min:0.001'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $selectedOptionValues = $validated['selected_option_values'] ?? [];
        $selectedServicings = $validated['selected_servicings'] ?? null;
        $variantSelections = $validated['variant_selections'] ?? [];
        $equipmentQty = isset($validated['equipment_qty']) ? (float) $validated['equipment_qty'] : 1.0;
        $locale = $validated['locale'] ?? app()->getLocale();

        $resolution = $this->lineBuilderService->resolve(
            blueprint: $blueprint,
            selectedOptionValueIds: $selectedOptionValues,
            selectedServicingIds: $selectedServicings,
            variantSelections: $variantSelections,
            equipmentQty: $equipmentQty,
            locale: $locale,
        );

        return response()->json([
            'success' => true,
            'data' => array_merge($resolution->toArray(), [
                'has_missing_variants' => $resolution->hasMissingVariants(),
            ]),
        ]);
    }
}
