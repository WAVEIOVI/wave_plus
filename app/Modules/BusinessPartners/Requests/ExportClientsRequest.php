<?php

declare(strict_types=1);

namespace App\Modules\BusinessPartners\Requests;

use App\Modules\BusinessPartners\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('export', Client::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('ids');

        if (is_string($ids)) {
            $trimmed = trim($ids);
            $this->merge([
                'ids' => $trimmed === ''
                    ? []
                    : array_values(array_filter(array_map('trim', explode(',', $trimmed)), static fn (string $v) => $v !== '')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => [
                'required',
                'string',
                'ulid',
                'distinct',
                Rule::exists('clients', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function validatedIds(): array
    {
        $ids = $this->validated('ids', []);

        return array_values(array_unique(array_filter(
            array_map(static fn ($id) => trim((string) $id), is_array($ids) ? $ids : []),
            static fn (string $id) => $id !== ''
        )));
    }
}
