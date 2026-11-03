<?php

declare(strict_types=1);

namespace App\Modules\Hr\Support;

use Illuminate\Http\Request;

final class TranslatableFields
{
    public static function normalizeRequest(Request $request, array $fields): void
    {
        $locale = app()->getLocale();
        $normalized = [];

        foreach ($fields as $field) {
            $value = $request->input($field);
            if (is_string($value)) {
                $normalized[$field] = [$locale => $value];
            }
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    public static function rules(
        string $field,
        bool $required = false,
        ?int $maxLength = null,
        bool $requiredWhenPresent = false
    ): array {
        $fieldRules = [$required ? 'required' : 'sometimes'];
        if ($requiredWhenPresent) {
            $fieldRules[] = 'required';
        }

        $fieldRules[] = $required ? 'array' : 'nullable';
        if (! $required) {
            $fieldRules[] = 'array';
        }

        $rules = [
            $field => [...$fieldRules, static function (string $attribute, mixed $value, \Closure $fail) use ($required, $requiredWhenPresent): void {
                if (($required || $requiredWhenPresent) && is_array($value)
                    && collect($value)->filter(
                        static fn ($translation): bool => is_string($translation) && trim($translation) !== ''
                    )->isEmpty()) {
                    $fail("The {$attribute} field must contain at least one non-empty translation.");
                }
            }],
            "{$field}.*" => ['nullable', 'string'],
        ];

        if ($maxLength !== null) {
            $rules["{$field}.*"][] = "max:{$maxLength}";
        }

        return $rules;
    }
}
