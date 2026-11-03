<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

class TemplateVariableResolver
{
    /**
     * Regex matching safe template placeholder tokens: {{ path.property }}
     */
    private const TOKEN_REGEX = '/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/';

    /**
     * Safely resolve dynamic variables in text/HTML without evaluating arbitrary code.
     *
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $content, array $context): string
    {
        if ($content === '') {
            return '';
        }

        // Flatten and normalize context keys for consistent access
        $normalizedContext = $this->normalizeContext($context);

        return (string) preg_replace_callback(self::TOKEN_REGEX, function (array $matches) use ($normalizedContext) {
            $key = trim($matches[1]);
            $value = data_get($normalizedContext, $key);

            if ($value === null) {
                // If not found in dot notation, also check snake_case variant
                $value = data_get($normalizedContext, str_replace('.', '_', $key));
            }

            if ($value === null) {
                return ''; // Gracefully empty when missing
            }

            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                return (string) $value;
            }

            return '';
        }, $content);
    }

    /**
     * Extract all placeholder tokens used in the given template strings.
     *
     * @return array<int, string>
     */
    public function extractTokens(string ...$contents): array
    {
        $tokens = [];

        foreach ($contents as $content) {
            if (preg_match_all(self::TOKEN_REGEX, $content, $matches)) {
                foreach ($matches[1] as $token) {
                    $tokens[] = trim($token);
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Validate template tokens against a given business context.
     *
     * @param  array<int, string>|string  $templateOrTokens
     * @param  array<string, mixed>  $context
     * @return array{
     *   required: array<int, string>,
     *   provided: array<int, string>,
     *   missing: array<int, string>,
     *   resolved_values: array<string, string>,
     * }
     */
    public function validateContext(array|string $templateOrTokens, array $context): array
    {
        $tokens = is_array($templateOrTokens)
            ? $templateOrTokens
            : $this->extractTokens($templateOrTokens);

        $normalizedContext = $this->normalizeContext($context);

        $provided = [];
        $missing = [];
        $resolvedValues = [];

        foreach ($tokens as $token) {
            $val = data_get($normalizedContext, $token);
            if ($val === null) {
                $val = data_get($normalizedContext, str_replace('.', '_', $token));
            }

            if ($val !== null && $val !== '') {
                $provided[] = $token;
                $resolvedValues[$token] = is_scalar($val) ? (string) $val : json_encode($val);
            } else {
                $missing[] = $token;
                $resolvedValues[$token] = '';
            }
        }

        return [
            'required' => $tokens,
            'provided' => $provided,
            'missing' => $missing,
            'resolved_values' => $resolvedValues,
        ];
    }

    /**
     * Normalize context so common prefixes like "recipient", "sender", "company", "document"
     * are uniformly accessible.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        // Default company fallback metadata if not supplied
        if (! isset($context['company'])) {
            $context['company'] = [
                'name' => config('app.name', 'WAVE PLUS'),
                'email' => 'contact@waveplus.xyz',
                'phone' => '+216 71 000 000',
                'website' => 'https://waveplus.xyz',
                'address' => 'Tunis, Tunisia',
            ];
        }

        return $context;
    }
}
