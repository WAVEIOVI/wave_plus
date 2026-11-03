<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\LeaveBalance;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LeaveBalanceService
{
    /**
     * Ensure a leave balance record exists for the employee, leave type, and year.
     */
    public function getOrCreateBalance(string $employeeId, string $leaveTypeId, int $year): LeaveBalance
    {
        $leaveType = LeaveType::findOrFail($leaveTypeId);

        return LeaveBalance::firstOrCreate(
            [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
            ],
            [
                'accrued_days' => $leaveType->max_days_per_year,
                'approved_used_days' => 0,
                'pending_reserved_days' => 0,
                'available_days' => $leaveType->max_days_per_year,
            ]
        );
    }

    /**
     * Reserve days when a leave request is submitted.
     */
    public function reserveDays(LeaveRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $year = (int) $request->start_date->format('Y');
            $balance = $this->getOrCreateBalance($request->employee_id, $request->leave_type_id, $year);

            if ($balance->available_days < $request->total_days) {
                throw new InvalidArgumentException(
                    "Insufficient leave balance. Available: {$balance->available_days} days, Requested: {$request->total_days} days."
                );
            }

            $balance->pending_reserved_days += $request->total_days;
            $balance->recalculate();
        });
    }

    /**
     * Release reserved days (on rejection or cancellation).
     */
    public function releaseReservedDays(LeaveRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $year = (int) $request->start_date->format('Y');
            $balance = $this->getOrCreateBalance($request->employee_id, $request->leave_type_id, $year);

            $balance->pending_reserved_days = max(0, $balance->pending_reserved_days - $request->total_days);
            $balance->recalculate();
        });
    }

    /**
     * Move reserved days to approved used days upon approval.
     */
    public function approveDays(LeaveRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $year = (int) $request->start_date->format('Y');
            $balance = $this->getOrCreateBalance($request->employee_id, $request->leave_type_id, $year);

            $balance->pending_reserved_days = max(0, $balance->pending_reserved_days - $request->total_days);
            $balance->approved_used_days += $request->total_days;
            $balance->recalculate();
        });
    }
}
