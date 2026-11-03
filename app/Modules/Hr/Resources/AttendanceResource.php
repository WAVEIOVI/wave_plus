<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->employee ? [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_number' => $this->employee->employee_number,
                'department' => $this->employee->department?->name,
                'avatar_url' => $this->employee->avatar_url,
            ] : null,
            'date' => $this->date->toDateString(),
            'scheduled_in' => $this->scheduled_in,
            'scheduled_out' => $this->scheduled_out,
            'actual_in' => $this->actual_in,
            'actual_out' => $this->actual_out,
            'scheduled_hours' => $this->scheduled_hours,
            'worked_hours' => $this->worked_hours,
            'late_minutes' => $this->late_minutes,
            'early_departure_minutes' => $this->early_departure_minutes,
            'overtime_hours' => $this->overtime_hours,
            'status' => $this->status,
            'coverage_status' => $this->coverage_status,
            'schedule_id' => $this->schedule_id,
            'covering_leave_id' => $this->covering_leave_id,
            'covering_leave' => $this->coveringLeave ? [
                'id' => $this->coveringLeave->id,
                'reference' => $this->coveringLeave->reference,
                'leave_type_name' => $this->coveringLeave->leaveType?->name,
                'duration_type' => $this->coveringLeave->duration_type,
                'start_time' => $this->coveringLeave->start_time,
                'end_time' => $this->coveringLeave->end_time,
                'status' => $this->coveringLeave->status,
            ] : null,
            'exceptions_count' => $this->relationLoaded('exceptions') ? $this->exceptions->count() : 0,
            'exceptions' => $this->relationLoaded('exceptions') ? $this->exceptions->map(fn ($e) => [
                'id' => $e->id,
                'exception_type' => $e->exception_type,
                'expected_time' => $e->expected_time,
                'actual_time' => $e->actual_time,
                'deviation_minutes' => $e->deviation_minutes,
                'review_status' => $e->review_status,
            ]) : [],
            'is_corrected' => $this->is_corrected,
            'notes' => $this->notes,
            'punches' => $this->relationLoaded('clockEvents') ? $this->clockEvents->map(fn ($e) => [
                'id' => $e->id,
                'type' => $e->event_type,
                'time' => Carbon::parse($e->event_time)->format('H:i'),
                'source' => $e->source,
            ]) : [],
            'sessions' => $this->buildSessions(),
            'corrections' => $this->corrections->map(fn ($c) => [
                'id' => $c->id,
                'requested_actual_in' => $c->requested_actual_in,
                'requested_actual_out' => $c->requested_actual_out,
                'reason' => $c->reason,
                'status' => $c->status,
                'requester_name' => $c->requester?->name,
                'approver_name' => $c->approver?->name,
                'created_at' => $c->created_at->toISOString(),
            ]),
        ];
    }

    protected function buildSessions(): array
    {
        $sessions = [];

        if ($this->relationLoaded('clockEvents') && $this->clockEvents->isNotEmpty()) {
            $openIn = null;
            foreach ($this->clockEvents as $event) {
                if ($event->event_type === 'in') {
                    if ($openIn) {
                        $sessions[] = [
                            'in' => Carbon::parse($openIn->event_time)->format('H:i'),
                            'out' => null,
                        ];
                    }
                    $openIn = $event;
                } elseif ($event->event_type === 'out') {
                    $sessions[] = [
                        'in' => $openIn ? Carbon::parse($openIn->event_time)->format('H:i') : null,
                        'out' => Carbon::parse($event->event_time)->format('H:i'),
                    ];
                    $openIn = null;
                }
            }
            if ($openIn) {
                $sessions[] = [
                    'in' => Carbon::parse($openIn->event_time)->format('H:i'),
                    'out' => null,
                ];
            }
        }

        if (empty($sessions) && ($this->actual_in || $this->actual_out)) {
            $sessions[] = [
                'in' => $this->actual_in ? substr((string) $this->actual_in, 0, 5) : null,
                'out' => $this->actual_out ? substr((string) $this->actual_out, 0, 5) : null,
            ];
        }

        return $sessions;
    }
}
