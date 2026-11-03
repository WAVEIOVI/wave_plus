<?php

declare(strict_types=1);

namespace App\Modules\Crm\Requests;

use App\Modules\Crm\Enums\CapacityCheckResult;
use App\Modules\Crm\Enums\StockCheckResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesOrderFeasibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approved,rejected'],
            'credit_check_result' => ['nullable', 'string', 'in:sufficient,warning,exceeded'],
            'stock_check_result' => ['nullable', 'string', Rule::enum(StockCheckResult::class)],
            'capacity_check_result' => ['nullable', 'string', Rule::enum(CapacityCheckResult::class)],
            'rejection_reason' => ['nullable', 'required_if:decision,rejected', 'string'],
        ];
    }
}
