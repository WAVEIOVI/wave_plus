<?php

declare(strict_types=1);

namespace App\Modules\Hr\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $durationType = $this->input('duration_type', 'full_day');

        if ($durationType === 'hourly' && $this->filled('start_time') && $this->filled('end_time')) {
            $startTime = Carbon::parse($this->start_time);
            $endTime = Carbon::parse($this->end_time);
            if ($endTime->gt($startTime)) {
                $hours = round($startTime->diffInMinutes($endTime) / 60, 2);
                $days = round($hours / 8.0, 3);
                $this->merge([
                    'total_days' => max(0.05, $days),
                    'end_date' => $this->input('start_date'),
                ]);
            }
        } elseif (! $this->has('total_days') && $this->filled('start_date') && $this->filled('end_date')) {
            $start = Carbon::parse($this->start_date);
            $end = Carbon::parse($this->end_date);
            if ($end->gte($start)) {
                $this->merge([
                    'total_days' => $start->diffInDays($end) + 1,
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'ulid', 'exists:hr_employees,id'],
            'leave_type_id' => ['required', 'ulid', 'exists:hr_leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_type' => ['nullable', 'string', 'in:full_day,partial_day,hourly'],
            'start_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'total_days' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:500'],
            'submit' => ['nullable', 'boolean'],
        ];
    }
}
