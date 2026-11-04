<?php

declare(strict_types=1);

namespace App\Modules\Hr\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = (new EmployeeResource($this->resource))->toArray($request);

        $base['employment_history'] = $this->employmentHistory->map(fn ($eh) => [
            'id' => $eh->id,
            'position' => $eh->position?->getTranslations('title') ?? [],
            'department' => $eh->department?->getTranslations('name') ?? [],
            'team' => $eh->team?->getTranslations('name') ?? [],
            'manager' => $eh->manager?->full_name ?? 'N/A',
            'employment_type' => $eh->employment_type,
            'contract_type' => $eh->contract_type,
            'start_date' => $eh->start_date?->toDateString(),
            'end_date' => $eh->end_date?->toDateString(),
            'reason_for_change' => $eh->reason_for_change,
            'notes' => $eh->notes,
        ]);

        $base['team_memberships'] = $this->teamMemberships->map(fn ($membership) => [
            'id' => $membership->id,
            'team' => $membership->team ? [
                'id' => $membership->team->id,
                'name' => $membership->team->getTranslations('name'),
            ] : null,
            'role_in_team' => $membership->role_in_team,
            'is_primary' => $membership->is_primary,
            'joined_at' => $membership->joined_at?->toDateString(),
            'left_at' => $membership->left_at?->toDateString(),
        ]);

        $base['skills'] = $this->employeeSkills->map(fn ($es) => [
            'id' => $es->id,
            'skill_id' => $es->skill_id,
            'name' => $es->skill?->getTranslations('name') ?? [],
            'code' => $es->skill?->code ?? '',
            'category' => $es->skill?->category ?? '',
            'level' => $es->level,
            'acquired_date' => $es->acquired_date?->toDateString(),
            'is_verified' => $es->is_verified,
            'verified_at' => $es->verified_at?->toIso8601String(),
            'expiry_date' => $es->expiry_date?->toDateString(),
            'notes' => $es->notes,
        ]);

        $base['certifications'] = $this->certifications->map(fn ($ec) => [
            'id' => $ec->id,
            'certification_id' => $ec->certification_id,
            'name' => $ec->certification?->getTranslations('name') ?? [],
            'code' => $ec->certification?->code ?? '',
            'certificate_number' => $ec->certificate_number,
            'issuing_organization' => $ec->issuing_organization,
            'issue_date' => $ec->issue_date?->toDateString(),
            'expiry_date' => $ec->expiry_date?->toDateString(),
            'status' => $ec->computed_status ?? $ec->status,
            'is_valid' => $ec->is_valid,
            'verified_at' => $ec->verified_at?->toIso8601String(),
            'notes' => $ec->notes,
            'files' => $ec->getMedia('certificate_files')->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->file_name,
                'url' => $m->getUrl(),
                'size' => $m->size,
            ]),
        ]);

        $base['schedules'] = $this->schedules->take(14)->map(fn ($s) => [
            'id' => $s->id,
            'date' => $s->date->toDateString(),
            'start_time' => $s->effective_start_time,
            'end_time' => $s->effective_end_time,
            'shift' => $s->shift ? [
                'name' => $s->shift->getTranslations('name'),
                'start_time' => $s->shift->start_time,
                'end_time' => $s->shift->end_time,
            ] : null,
            'status' => $s->lifecycle_status ?? $s->status,
        ]);

        $base['recent_attendance'] = $this->attendances->take(14)->map(fn ($a) => [
            'id' => $a->id,
            'date' => $a->date->toDateString(),
            'actual_in' => $a->actual_in,
            'actual_out' => $a->actual_out,
            'worked_hours' => $a->worked_hours,
            'scheduled_hours' => $a->scheduled_hours,
            'late_minutes' => $a->late_minutes,
            'overtime_hours' => $a->overtime_hours,
            'status' => $a->status,
            'is_corrected' => $a->is_corrected,
        ]);

        $base['leave_balances'] = $this->leaveBalances->map(fn ($lb) => [
            'id' => $lb->id,
            'leave_type_id' => $lb->leave_type_id,
            'name' => $lb->leaveType?->getTranslations('name') ?? [],
            'code' => $lb->leaveType?->code ?? '',
            'year' => $lb->year,
            'accrued_days' => $lb->accrued_days,
            'approved_used_days' => $lb->approved_used_days,
            'pending_reserved_days' => $lb->pending_reserved_days,
            'available_days' => $lb->available_days,
        ]);

        $base['leave_requests'] = $this->leaveRequests->take(10)->map(fn ($lr) => [
            'id' => $lr->id,
            'reference' => $lr->reference,
            'leave_type' => $lr->leaveType?->getTranslations('name') ?? [],
            'start_date' => $lr->start_date->toDateString(),
            'end_date' => $lr->end_date->toDateString(),
            'total_days' => $lr->total_days,
            'status' => $lr->status,
            'reason' => $lr->reason,
            'rejection_reason' => $lr->rejection_reason,
            'approved_at' => $lr->approved_at?->toISOString(),
        ]);

        $base['trainings'] = $this->trainings->map(fn ($et) => [
            'id' => $et->id,
            'program_name' => $et->session?->program?->getTranslations('name') ?? [],
            'session_code' => $et->session?->session_code ?? '',
            'trainer' => $et->session?->getTranslations('trainer') ?? [],
            'start_date' => $et->session?->start_date?->toDateString(),
            'end_date' => $et->session?->end_date?->toDateString(),
            'status' => $et->status,
            'score' => $et->score,
            'certificate_number' => $et->certificate_number,
        ]);

        $user = $request->user();
        $isSelf = $user && $this->user_id === $user->id;
        $canViewAppraisals = $user && (
            $user->hasRole(['Super Admin', 'Admin', 'HR Manager'])
            || $user->can('hr.appraisals.view')
            || $isSelf
        );
        $canViewDocuments = $user && (
            $user->hasRole(['Super Admin', 'Admin', 'HR Manager'])
            || $user->can('hr.employees.view-sensitive')
            || $user->can('hr.employees.edit')
            || $isSelf
        );

        $base['appraisals'] = $canViewAppraisals ? $this->appraisals->map(fn ($ap) => [
            'id' => $ap->id,
            'cycle_name' => $ap->cycle?->name ?? 'N/A',
            'cycle_type' => $ap->cycle?->cycle_type ?? '',
            'overall_score' => $ap->overall_score,
            'status' => $ap->status,
            'reviewer' => $ap->reviewer?->name ?? 'N/A',
            'manager_feedback' => $ap->manager_feedback,
            'employee_feedback' => $ap->employee_feedback,
            'goals' => $ap->goals,
            'ratings' => $ap->ratings,
            'reviewed_at' => $ap->reviewed_at?->toIso8601String(),
            'acknowledged_at' => $ap->acknowledged_at?->toIso8601String(),
            'is_completed' => $ap->is_completed,
            'date' => $ap->created_at?->toDateString(),
        ]) : [];

        $base['documents'] = $canViewDocuments ? $this->getMedia('documents')->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->file_name,
            'file_name' => $m->file_name,
            'mime_type' => $m->mime_type,
            'size' => $m->size,
            'url' => $m->getUrl(),
            'created_at' => $m->created_at?->toISOString(),
        ]) : [];

        return $base;
    }
}
