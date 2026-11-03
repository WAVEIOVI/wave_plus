<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'employee_id' => $this->employee_id,
            'employee' => $this->employee ? [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_number' => $this->employee->employee_number,
                'department' => $this->employee->department?->name,
                'avatar_url' => $this->employee->avatar_url,
            ] : null,
            'leave_type_id' => $this->leave_type_id,
            'leave_type' => $this->leaveType ? [
                'id' => $this->leaveType->id,
                'name' => $this->leaveType->name,
                'code' => $this->leaveType->code,
                'is_paid' => $this->leaveType->is_paid,
                'allows_hourly' => (bool) ($this->leaveType->allows_hourly ?? false),
            ] : null,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'duration_type' => $this->duration_type ?? 'full_day',
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'total_days' => $this->total_days,
            'days_requested' => $this->total_days,
            'reason' => $this->reason,
            'status' => $this->status,
            'approver_id' => $this->approver_id,
            'approver_name' => $this->approver?->name,
            'approved_at' => $this->approved_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'decision_note' => $this->decision_note,
            'requested_by_id' => $this->requested_by_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
