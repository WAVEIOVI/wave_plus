<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Automation\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ConditionEvaluatorService
{
    /**
     * Allowed comparison operators.
     */
    public const ALLOWED_OPERATORS = [
        '=',
        '!=',
        '>',
        '<',
        '>=',
        '<=',
        'in',
        'not_in',
        'contains',
        'is_null',
        'is_not_null',
    ];

    /**
     * Evaluate an array of structured condition rules against a business record.
     *
     * @param  array<int, array{field: string, operator: string, value: mixed}>|null  $conditions
     */
    public function evaluate(?array $conditions, Model $record, array $context = []): bool
    {
        if (empty($conditions)) {
            return true; // No conditions means rule always matches trigger
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateSingleCondition($condition, $record, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition safely.
     *
     * @param  array{field: string, operator: string, value: mixed}  $condition
     */
    protected function evaluateSingleCondition(array $condition, Model $record, array $context): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '=';
        $expectedValue = $condition['value'] ?? null;

        if (! in_array($operator, self::ALLOWED_OPERATORS, true)) {
            return false;
        }

        $actualValue = $this->resolveFieldValue($field, $record, $context);

        return match ($operator) {
            '=' => $this->compareEquals($actualValue, $expectedValue),
            '!=' => ! $this->compareEquals($actualValue, $expectedValue),
            '>' => $this->compareRelative($actualValue, $expectedValue) > 0,
            '<' => $this->compareRelative($actualValue, $expectedValue) < 0,
            '>=' => $this->compareRelative($actualValue, $expectedValue) >= 0,
            '<=' => $this->compareRelative($actualValue, $expectedValue) <= 0,
            'in' => is_array($expectedValue) && in_array($actualValue, $expectedValue, false),
            'not_in' => is_array($expectedValue) && ! in_array($actualValue, $expectedValue, false),
            'contains' => is_string($actualValue) && is_string($expectedValue) && str_contains(strtolower($actualValue), strtolower($expectedValue)),
            'is_null' => $actualValue === null || $actualValue === '',
            'is_not_null' => $actualValue !== null && $actualValue !== '',
            default => false,
        };
    }

    /**
     * Resolve the value of a field from the record or context.
     */
    protected function resolveFieldValue(string $field, Model $record, array $context): mixed
    {
        // 1. Direct model attribute or relation
        if (array_key_exists($field, $context)) {
            return $context[$field];
        }

        // Support dot notation like client.legal_name or assigned_to.email
        if (str_contains($field, '.')) {
            $parts = explode('.', $field);
            $curr = $record;
            foreach ($parts as $part) {
                if ($curr instanceof Model) {
                    $curr = $curr->{$part};
                } elseif (is_array($curr) && array_key_exists($part, $curr)) {
                    $curr = $curr[$part];
                } else {
                    return null;
                }
            }

            return $curr;
        }

        // Standard direct attribute
        $val = $record->getAttribute($field);
        if ($val instanceof \BackedEnum) {
            return $val->value;
        }

        return $val;
    }

    /**
     * Safely compare equality considering enums, dates, and numbers.
     */
    protected function compareEquals(mixed $actual, mixed $expected): bool
    {
        if ($actual instanceof \BackedEnum) {
            $actual = $actual->value;
        }
        if ($expected instanceof \BackedEnum) {
            $expected = $expected->value;
        }

        if ($actual instanceof Carbon && is_string($expected)) {
            return $actual->toDateString() === Carbon::parse($expected)->toDateString();
        }

        return (string) $actual === (string) $expected;
    }

    /**
     * Compare relative values (numbers or dates).
     */
    protected function compareRelative(mixed $actual, mixed $expected): int
    {
        if ($actual instanceof Carbon || $this->isDateString($actual)) {
            $actualDate = $actual instanceof Carbon ? $actual : Carbon::parse($actual);
            $expectedDate = $expected === 'now' ? Carbon::now() : Carbon::parse($expected);

            return $actualDate->timestamp <=> $expectedDate->timestamp;
        }

        return (float) $actual <=> (float) $expected;
    }

    protected function isDateString(mixed $val): bool
    {
        return is_string($val) && (bool) strtotime($val);
    }
}
