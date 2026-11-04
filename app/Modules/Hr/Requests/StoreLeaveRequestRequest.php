<?php

declare(strict_types=1);

namespace App\Modules\Hr\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Modules\Hr\Models\LeaveRequest::class)
            || $this->user()?->can('hr.leave.request')
            || $this->user()?->can('hr.leave.manage')
            || false;
    }

    protected function prepareForValidation(): void
    {
        $durationType = $this->input('duration_type', 'full_day');

        if (in_array($durationType, ['hourly', 'partial_day'], true) && $this->filled('start_time') && $this->filled('end_time')) {
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
        } elseif ($durationType === 'partial_day' && ! $this->filled('total_days')) {
            $this->merge([
                'total_days' => 0.5,
                'end_date' => $this->input('start_date'),
            ]);
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $employeeId = $this->input('employee_id');
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date', $startDate);
            $durationType = $this->input('duration_type', 'full_day');
            $startTime = $this->input('start_time');
            $endTime = $this->input('end_time');
            $leaveTypeId = $this->input('leave_type_id');

            if (! $employeeId || ! $startDate || ! $leaveTypeId) {
                return;
            }

            // Employee self-service check: non-managers can only request for their own employee record
            $user = $this->user();
            $hasManage = $user && ($user->hasRole(['Super Admin', 'Admin']) || $user->can('hr.leave.manage'));
            if (! $hasManage && $user) {
                $linkedEmployee = \App\Modules\Hr\Models\Employee::where('user_id', $user->id)->first();
                if (! $linkedEmployee || $linkedEmployee->id !== $employeeId) {
                    $validator->errors()->add('employee_id', 'You can only submit leave requests for your own employee profile.');

                    return;
                }
            }

            // Verify leave type allows hourly if duration is hourly or partial_day
            $leaveType = \App\Modules\Hr\Models\LeaveType::find($leaveTypeId);
            if ($leaveType && in_array($durationType, ['hourly', 'partial_day'], true) && ! $leaveType->allows_hourly) {
                $validator->errors()->add(
                    'duration_type',
                    "The selected leave type '{$leaveType->code}' does not allow hourly or partial-day leave requests."
                );

                return;
            }

            // Check for overlapping active leave requests
            $query = \App\Modules\Hr\Models\LeaveRequest::where('employee_id', $employeeId)
                ->whereIn('status', ['draft', 'submitted', 'pending', 'approved']);

            $currentId = $this->route('id') ?? $this->route('leave_request');
            if ($currentId) {
                $query->where('id', '!=', $currentId);
            }

            $candidates = $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereDate('start_date', '<=', $endDate)
                    ->whereDate('end_date', '>=', $startDate);
            })->get();

            foreach ($candidates as $existing) {
                $isNewHourly = in_array($durationType, ['hourly', 'partial_day'], true) && $startTime && $endTime;
                $isExistingHourly = $existing->isHourlyLeave() && $existing->start_time && $existing->end_time;

                if ($isNewHourly && $isExistingHourly && $startDate === $existing->start_date->toDateString()) {
                    // Both are hourly on the same date: conflict only if time spans overlap
                    if ($startTime < $existing->end_time && $endTime > $existing->start_time) {
                        $validator->errors()->add(
                            'start_date',
                            "Overlapping hourly leave request exists ({$existing->reference} from {$existing->start_time} to {$existing->end_time}, status: {$existing->status})."
                        );
                        break;
                    }
                } else {
                    $validator->errors()->add(
                        'start_date',
                        "The employee already has an active or approved leave request ({$existing->reference} - {$existing->status}) from {$existing->start_date->toDateString()} to {$existing->end_date->toDateString()}."
                    );
                    break;
                }
            }
        });
    }
}
