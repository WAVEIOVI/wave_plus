<?php

namespace App\Modules\ClientAssets\Requests\Concerns;

/**
 * Shared validation rules for the workwear employee "Sizes & Measurements"
 * and "Workwear Requirements" sections (single, update and batch requests).
 */
trait WorkwearEmployeeProfileRules
{
    /**
     * Canonical size keys used by the UI plus the legacy keys already accepted
     * by earlier API versions (kept so existing records still validate/save).
     *
     * @var array<int, string>
     */
    public const SIZE_KEYS = [
        'top', 'bottom', 'shoes', 'gloves', 'head',
        'chest', 'waist', 'hips', 'height', 'inseam',
        'jacket', 'trousers', 'footwear',
    ];

    /**
     * Body measurements in centimetres (weight in kilograms). Distinct from garment sizes.
     *
     * @var array<int, string>
     */
    public const MEASUREMENT_KEYS = [
        'height', 'weight', 'neck', 'shoulder', 'chest', 'waist', 'hips',
        'sleeve', 'inseam', 'head', 'foot_length',
    ];

    /**
     * @return array<string, string>
     */
    protected function profileRules(string $prefix = ''): array
    {
        $rules = [
            $prefix.'sizes' => 'nullable|array',
            $prefix.'measurements' => 'nullable|array',
            $prefix.'workwear_requirements' => 'nullable|array|max:50',
            $prefix.'workwear_requirements.*.garment_type' => 'required|string|max:80',
            $prefix.'workwear_requirements.*.quantity' => 'required|integer|min:1|max:99',
            $prefix.'workwear_requirements.*.notes' => 'nullable|string|max:255',
        ];

        foreach (self::SIZE_KEYS as $key) {
            $rules[$prefix.'sizes.'.$key] = 'nullable|string|max:20';
        }

        foreach (self::MEASUREMENT_KEYS as $key) {
            $rules[$prefix.'measurements.'.$key] = 'nullable|numeric|min:0|max:400';
        }

        return $rules;
    }

    /**
     * Workwear model assignments (entitlements) — single create/update only.
     *
     * @return array<string, string>
     */
    protected function modelAssignmentRules(): array
    {
        return [
            'model_assignments' => 'nullable|array|max:50',
            'model_assignments.*.model_id' => 'required|ulid|exists:workwear_models,id',
            'model_assignments.*.quantity' => 'required|integer|min:1|max:99',
            'model_assignments.*.size' => 'nullable|string|max:20',
            'model_assignments.*.notes' => 'nullable|string|max:255',
        ];
    }
}
