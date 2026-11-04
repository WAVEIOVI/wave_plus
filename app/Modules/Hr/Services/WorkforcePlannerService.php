<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\Attendance;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\PublicHoliday;
use App\Modules\Hr\Models\Schedule;
use App\Modules\Hr\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkforcePlannerService
{
    /**
     * Retrieve the full planner grid with schedules, covering leaves, and actual attendances.
     */
    public function getPlannerGrid(
        string $startDate,
        string $endDate,
        ?string $departmentId = null,
        ?string $teamId = null,
        ?string $search = null
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Fetch active public holidays in date range or recurring
        $holidays = PublicHoliday::active()
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere('is_recurring', true);
            })
            ->get();

        // 1. Build date array with weekend & holiday info
        $period = CarbonPeriod::create($start, $end);
        $dates = [];
        foreach ($period as $date) {
            $matchingHoliday = $holidays->first(fn ($h) => $h->matchesDate($date));
            $holidayName = $matchingHoliday ? ($matchingHoliday->getTranslation('name', app()->getLocale()) ?? $matchingHoliday->name) : null;

            $dates[] = [
                'date' => $date->toDateString(),
                'day_name' => $date->format('D'),
                'day_number' => $date->format('d'),
                'is_weekend' => $date->isWeekend(),
                'is_today' => $date->isToday(),
                'is_holiday' => (bool) $matchingHoliday,
                'holiday_name' => $holidayName,
                'holiday' => $matchingHoliday ? [
                    'id' => $matchingHoliday->id,
                    'name' => $holidayName,
                    'is_half_day' => $matchingHoliday->is_half_day,
                ] : null,
            ];
        }

        // 2. Fetch active employees
        $empQuery = Employee::with(['department', 'currentPosition', 'currentTeam'])
            ->where('status', 'active');

        if ($departmentId) {
            $empQuery->where('department_id', $departmentId);
        }

        if ($teamId) {
            $empQuery->whereHas('teamMemberships', function ($q) use ($teamId) {
                $q->where('team_id', $teamId)->whereNull('left_at');
            });
        }

        if ($search) {
            $empQuery->where(function ($q) use ($search) {
                $q->where('first_name->'.app()->getLocale(), 'like', "%{$search}%")
                    ->orWhere('last_name->'.app()->getLocale(), 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        $employees = $empQuery->orderBy('first_name->'.app()->getLocale())->orderBy('last_name->'.app()->getLocale())->get();
        $employeeIds = $employees->pluck('id')->toArray();

        // 3. Fetch shifts
        $shifts = Shift::active()->orderBy('start_time')->get();

        // 4. Fetch schedules for these employees in date range
        $schedules = Schedule::with('shift')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(['employee_id', fn ($item) => $item->date->toDateString()]);

        // 5. Fetch approved & submitted leave requests
        $leaves = LeaveRequest::with('leaveType')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', ['approved', 'submitted'])
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($sub) use ($start, $end) {
                        $sub->where('start_date', '<=', $start->toDateString())
                            ->where('end_date', '>=', $end->toDateString());
                    });
            })
            ->get();

        // 6. Fetch attendances for these employees in date range
        $attendances = Attendance::whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(['employee_id', fn ($item) => $item->date->toDateString()]);

        // 7. Assemble grid rows
        $employeeRows = [];

        foreach ($employees as $emp) {
            $days = [];

            foreach ($dates as $d) {
                $dateStr = $d['date'];

                // Schedule on this date
                $schedule = $schedules->get($emp->id)?->get($dateStr)?->first();

                // Leave on this date
                $coveringLeave = $leaves->first(function ($l) use ($emp, $dateStr) {
                    return $l->employee_id === $emp->id &&
                        $dateStr >= $l->start_date->toDateString() &&
                        $dateStr <= $l->end_date->toDateString();
                });

                // Attendance on this date
                $attendance = $attendances->get($emp->id)?->get($dateStr)?->first();

                // Compute cell summary
                $matchingEmpHoliday = $holidays->first(function ($h) use ($date, $emp) {
                    if (! $h->matchesDate($date)) {
                        return false;
                    }
                    if ($h->applies_to_all) {
                        return true;
                    }
                    return $h->department_id === $emp->department_id;
                });

                $isConflict = ($schedule && $coveringLeave && in_array($coveringLeave->status, ['approved', 'submitted']))
                    || ($schedule && $schedule->coverage_status === Schedule::COVERAGE_CONFLICT);

                $cell = [
                    'date' => $dateStr,
                    'is_weekend' => $date->isWeekend(),
                    'is_conflict' => $isConflict,
                    'conflict_reason' => $isConflict ? ($coveringLeave ? "Schedule overlaps with {$coveringLeave->status} leave" : 'Schedule conflict detected') : null,
                    'is_exceptional' => (bool) ($schedule && (! empty($schedule->custom_start_time) || ! empty($schedule->custom_end_time) || $schedule->is_overnight_override)),
                    'holiday' => $matchingEmpHoliday ? [
                        'id' => $matchingEmpHoliday->id,
                        'name' => $matchingEmpHoliday->getTranslation('name', app()->getLocale()) ?? $matchingEmpHoliday->name,
                        'is_half_day' => $matchingEmpHoliday->is_half_day,
                    ] : null,
                    'schedule' => $schedule ? [
                        'id' => $schedule->id,
                        'shift_id' => $schedule->shift_id,
                        'shift_name' => $schedule->shift?->name,
                        'shift_code' => $schedule->shift?->code,
                        'start_time' => $schedule->effective_start_time,
                        'end_time' => $schedule->effective_end_time,
                        'custom_start_time' => $schedule->custom_start_time,
                        'custom_end_time' => $schedule->custom_end_time,
                        'lifecycle_status' => $schedule->lifecycle_status ?? $schedule->status,
                        'coverage_status' => $schedule->coverage_status,
                        'is_overnight' => $schedule->is_overnight,
                        'is_conflict' => $isConflict,
                        'conflict_reason' => $isConflict ? ($coveringLeave ? "Schedule overlaps with {$coveringLeave->status} leave" : 'Schedule conflict detected') : null,
                        'is_exceptional' => ! empty($schedule->custom_start_time) || ! empty($schedule->custom_end_time) || (bool) $schedule->is_overnight_override,
                        'notes' => $schedule->notes,
                    ] : null,
                    'leave' => $coveringLeave ? [
                        'id' => $coveringLeave->id,
                        'reference' => $coveringLeave->reference,
                        'leave_type_name' => $coveringLeave->leaveType?->name,
                        'duration_type' => $coveringLeave->duration_type ?? 'full_day',
                        'start_time' => $coveringLeave->start_time,
                        'end_time' => $coveringLeave->end_time,
                        'total_days' => $coveringLeave->total_days,
                        'status' => $coveringLeave->status,
                    ] : null,
                    'attendance' => $attendance ? [
                        'id' => $attendance->id,
                        'actual_in' => $attendance->actual_in,
                        'actual_out' => $attendance->actual_out,
                        'worked_hours' => $attendance->worked_hours,
                        'scheduled_hours' => $attendance->scheduled_hours,
                        'late_minutes' => $attendance->late_minutes,
                        'early_departure_minutes' => $attendance->early_departure_minutes,
                        'status' => $attendance->status,
                        'coverage_status' => $attendance->coverage_status,
                    ] : null,
                ];

                $days[$dateStr] = $cell;
            }

            $employeeRows[] = [
                'id' => $emp->id,
                'full_name' => $emp->full_name,
                'employee_number' => $emp->employee_number,
                'avatar_url' => $emp->avatar_url,
                'department' => $emp->department ? [
                    'id' => $emp->department->id,
                    'name' => $emp->department->name,
                ] : null,
                'position' => $emp->currentPosition ? [
                    'id' => $emp->currentPosition->id,
                    'title' => $emp->currentPosition->title,
                ] : null,
                'team' => $emp->currentTeam ? [
                    'id' => $emp->currentTeam->id,
                    'name' => $emp->currentTeam->name,
                ] : null,
                'days' => $days,
            ];
        }

        return [
            'dates' => $dates,
            'shifts' => $shifts,
            'employees' => $employeeRows,
            'meta' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_employees' => count($employeeRows),
            ],
        ];
    }

    /**
     * Bulk generate or update schedules for multiple employees across a date range.
     */
    public function bulkSchedule(array $params): array
    {
        $employeeIds = $params['employee_ids'] ?? [];
        $startDate = $params['start_date'];
        $endDate = $params['end_date'];
        $shiftId = $params['shift_id'] ?? null;
        $lifecycleStatus = $params['lifecycle_status'] ?? Schedule::STATUS_PLANNED;
        $skipApprovedLeaves = $params['skip_approved_leaves'] ?? true;
        $skipHolidays = $params['skip_holidays'] ?? true;
        $includeWeekends = $params['include_weekends'] ?? false;
        $overwriteExisting = $params['overwrite_existing'] ?? false;
        $daysOfWeek = $params['days_of_week'] ?? ($includeWeekends ? [1, 2, 3, 4, 5, 6, 7] : [1, 2, 3, 4, 5]);
        if ($includeWeekends) {
            if (! in_array(6, $daysOfWeek)) {
                $daysOfWeek[] = 6;
            }
            if (! in_array(7, $daysOfWeek)) {
                $daysOfWeek[] = 7;
            }
        }
        $notes = $params['notes'] ?? null;
        $userId = auth()->id();

        $planBatchId = (string) Str::ulid();
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $period = CarbonPeriod::create($start, $end);

        // Fetch active holidays overlapping period
        $holidays = PublicHoliday::active()
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere('is_recurring', true);
            })
            ->get();

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $period, $employeeIds, $shiftId, $lifecycleStatus, $skipApprovedLeaves,
            $skipHolidays, $holidays, $overwriteExisting, $daysOfWeek, $notes, $userId, $planBatchId,
            &$created, &$updated, &$skipped
        ) {
            foreach ($period as $date) {
                $isoDay = $date->dayOfWeekIso; // 1 = Monday, 7 = Sunday
                if (! in_array($isoDay, $daysOfWeek)) {
                    continue;
                }

                $dateStr = $date->toDateString();

                // If skipHolidays is true, skip date if it is an all-company holiday
                if ($skipHolidays && $holidays->contains(fn ($h) => $h->matchesDate($date) && $h->applies_to_all)) {
                    $skipped += count($employeeIds);
                    continue;
                }

                foreach ($employeeIds as $empId) {
                    // Check if employee has full day approved leave
                    if ($skipApprovedLeaves) {
                        $hasFullDayLeave = LeaveRequest::where('employee_id', $empId)
                            ->where('status', 'approved')
                            ->whereDate('start_date', '<=', $dateStr)
                            ->whereDate('end_date', '>=', $dateStr)
                            ->where(function ($q) {
                                $q->whereNull('duration_type')
                                    ->orWhere('duration_type', 'full_day');
                            })
                            ->exists();

                        if ($hasFullDayLeave) {
                            $skipped++;

                            continue;
                        }
                    }

                    // Check for partial day leave to set coverage status
                    $partialLeave = LeaveRequest::where('employee_id', $empId)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $dateStr)
                        ->whereDate('end_date', '>=', $dateStr)
                        ->first();

                    $coverageStatus = Schedule::COVERAGE_NORMAL;
                    if ($partialLeave) {
                        $coverageStatus = $partialLeave->isHourlyLeave()
                            ? Schedule::COVERAGE_PARTIAL_LEAVE
                            : Schedule::COVERAGE_FULL_DAY_LEAVE;
                    }

                    $existing = Schedule::where('employee_id', $empId)
                        ->whereDate('date', $dateStr)
                        ->first();

                    if ($existing) {
                        if ($overwriteExisting) {
                            $existing->update([
                                'shift_id' => $shiftId ?? $existing->shift_id,
                                'lifecycle_status' => $lifecycleStatus,
                                'coverage_status' => $coverageStatus,
                                'plan_batch_id' => $planBatchId,
                                'notes' => $notes ?? $existing->notes,
                            ]);
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        Schedule::create([
                            'employee_id' => $empId,
                            'shift_id' => $shiftId,
                            'date' => $dateStr,
                            'status' => 'scheduled',
                            'lifecycle_status' => $lifecycleStatus,
                            'coverage_status' => $coverageStatus,
                            'plan_batch_id' => $planBatchId,
                            'notes' => $notes,
                            'created_by_id' => $userId,
                        ]);
                        $created++;
                    }
                }
            }
        });

        return [
            'batch_id' => $planBatchId,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_processed' => $created + $updated + $skipped,
        ];
    }

    /**
     * Publish draft schedules to planned status.
     */
    public function publishSchedules(array $params): int
    {
        $query = Schedule::where('lifecycle_status', Schedule::STATUS_DRAFT);

        if (! empty($params['schedule_ids'])) {
            $query->whereIn('id', $params['schedule_ids']);
        }

        if (! empty($params['batch_id'])) {
            $query->where('plan_batch_id', $params['batch_id']);
        }

        if (! empty($params['start_date']) && ! empty($params['end_date'])) {
            $query->whereBetween('date', [$params['start_date'], $params['end_date']]);
        }

        if (! empty($params['employee_ids'])) {
            $query->whereIn('employee_id', $params['employee_ids']);
        }

        return $query->update([
            'lifecycle_status' => Schedule::STATUS_PLANNED,
            'status' => 'scheduled',
        ]);
    }

    /**
     * Clear / remove schedules in bulk.
     */
    public function clearSchedules(array $params): int
    {
        $query = Schedule::query();

        if (! empty($params['schedule_ids'])) {
            $query->whereIn('id', $params['schedule_ids']);
        } elseif (! empty($params['batch_id'])) {
            $query->where('plan_batch_id', $params['batch_id']);
        } elseif (! empty($params['start_date']) && ! empty($params['end_date'])) {
            $query->whereBetween('date', [$params['start_date'], $params['end_date']]);
            if (! empty($params['employee_ids'])) {
                $query->whereIn('employee_id', $params['employee_ids']);
            }
        } else {
            return 0;
        }

        // Avoid deleting completed schedules
        $query->whereNotIn('lifecycle_status', [Schedule::STATUS_COMPLETED]);

        return $query->delete();
    }

    /**
     * Retrieve batch history of bulk scheduling operations.
     */
    public function getScheduleHistory(int $limit = 25): array
    {
        $batches = Schedule::whereNotNull('plan_batch_id')
            ->select(
                'plan_batch_id',
                DB::raw('MIN(created_at) as created_at'),
                DB::raw('MIN(created_by_id) as created_by_id'),
                DB::raw('MIN(date) as min_date'),
                DB::raw('MAX(date) as max_date'),
                DB::raw('COUNT(*) as total_slots'),
                DB::raw("COUNT(CASE WHEN lifecycle_status = 'draft' THEN 1 END) as draft_slots"),
                DB::raw("COUNT(CASE WHEN lifecycle_status = 'planned' THEN 1 END) as planned_slots"),
                DB::raw("COUNT(CASE WHEN lifecycle_status = 'completed' THEN 1 END) as completed_slots"),
                DB::raw('COUNT(DISTINCT employee_id) as employee_count'),
                DB::raw('MIN(notes) as notes'),
                DB::raw('MIN(shift_id) as shift_id')
            )
            ->groupBy('plan_batch_id')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $shiftIds = $batches->pluck('shift_id')->filter()->unique();
        $shifts = Shift::whereIn('id', $shiftIds)->get()->keyBy('id');
        $creatorIds = $batches->pluck('created_by_id')->filter()->unique();
        $creators = \App\Models\User::whereIn('id', $creatorIds)->get(['id', 'name', 'email'])->keyBy('id');

        return $batches->map(function ($batch) use ($shifts, $creators) {
            $shift = $shifts->get($batch->shift_id);
            $creator = $creators->get($batch->created_by_id);

            return [
                'batch_id' => $batch->plan_batch_id,
                'plan_batch_id' => $batch->plan_batch_id,
                'created_at' => $batch->created_at,
                'created_by' => $creator ? ['id' => $creator->id, 'name' => $creator->name] : null,
                'shift_name' => $shift?->name,
                'shift_code' => $shift?->code,
                'start_date' => Carbon::parse($batch->min_date)->toDateString(),
                'end_date' => Carbon::parse($batch->max_date)->toDateString(),
                'notes' => $batch->notes,
                'total_schedules' => (int) $batch->total_slots,
                'total_slots' => (int) $batch->total_slots,
                'draft_count' => (int) $batch->draft_slots,
                'draft_slots' => (int) $batch->draft_slots,
                'planned_count' => (int) $batch->planned_slots,
                'planned_slots' => (int) $batch->planned_slots,
                'completed_count' => (int) $batch->completed_slots,
                'completed_slots' => (int) $batch->completed_slots,
                'employee_count' => (int) $batch->employee_count,
            ];
        })->values()->toArray();
    }
}
