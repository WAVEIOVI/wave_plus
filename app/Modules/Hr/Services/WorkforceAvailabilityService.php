<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class WorkforceAvailabilityService
{
    /**
     * Check if a specific employee is available on a specific date.
     */
    public function isEmployeeAvailable(string $employeeId, CarbonInterface $date): bool
    {
        $employee = Employee::find($employeeId);
        if (! $employee || $employee->status !== 'active') {
            return false;
        }

        $dateStr = $date->toDateString();
        $hasLeave = LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->exists();

        return ! $hasLeave;
    }

    /**
     * Find available workforce based on flexible criteria array.
     */
    public function findAvailableWorkforce(array $criteria): Collection
    {
        $date = isset($criteria['date']) ? Carbon::parse($criteria['date']) : now();
        $startTime = isset($criteria['start_time']) ? Carbon::parse($criteria['start_time']) : $date->copy()->startOfDay();
        $endTime = isset($criteria['end_time']) ? Carbon::parse($criteria['end_time']) : $date->copy()->endOfDay();

        $skillIds = [];
        if (! empty($criteria['required_skills'])) {
            foreach ($criteria['required_skills'] as $req) {
                if (is_array($req) && isset($req['skill_id'])) {
                    $skillIds[] = $req['skill_id'];
                } elseif (is_string($req)) {
                    $skillIds[] = $req;
                }
            }
        }

        $certIds = $criteria['required_certifications'] ?? [];
        $teamId = $criteria['team_id'] ?? null;
        $deptId = $criteria['department_id'] ?? null;

        $results = $this->queryAvailability($startTime, $endTime, $skillIds, $certIds, $teamId, $deptId);

        // Filter if requested min levels
        if (! empty($criteria['required_skills'])) {
            $results = $results->filter(function ($item) use ($criteria) {
                foreach ($criteria['required_skills'] as $req) {
                    if (is_array($req) && isset($req['skill_id'], $req['min_level'])) {
                        $matched = collect($item['active_skills'])->firstWhere('id', $req['skill_id']);
                        if (! $matched) {
                            return false;
                        }
                        $levelMap = ['basic' => 1, 'intermediate' => 2, 'advanced' => 3, 'expert' => 4];
                        $actualLvl = is_numeric($matched['level']) ? (int) $matched['level'] : ($levelMap[$matched['level']] ?? 1);
                        if ($actualLvl < (int) $req['min_level']) {
                            return false;
                        }
                    }
                }

                return true;
            });
        }

        return $results->values();
    }

    /**
     * Query internal employees matching availability and qualification criteria.
     *
     * @param  array<string>  $requiredSkillIds
     * @param  array<string>  $requiredCertificationIds
     * @return Collection<int, array<string, mixed>>
     */
    public function queryAvailability(
        CarbonInterface $startTime,
        CarbonInterface $endTime,
        array $requiredSkillIds = [],
        array $requiredCertificationIds = [],
        ?string $teamId = null,
        ?string $departmentId = null
    ): Collection {
        $startDate = $startTime->toDateString();
        $endDate = $endTime->toDateString();

        // 1. Fetch active employees
        $query = Employee::with([
            'department:id,name,code',
            'position:id,title,code',
            'currentTeam:id,name,code',
            'employeeSkills.skill',
            'certifications.certification',
            'leaveRequests' => function ($q) use ($startDate, $endDate) {
                $q->where('status', 'approved')
                    ->where(function ($sub) use ($startDate, $endDate) {
                        $sub->whereBetween('start_date', [$startDate, $endDate])
                            ->orWhereBetween('end_date', [$startDate, $endDate])
                            ->orWhere(function ($s) use ($startDate, $endDate) {
                                $s->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                            });
                    });
            },
        ])->where('status', 'active');

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        if ($teamId) {
            $query->where('current_team_id', $teamId);
        }

        $employees = $query->get();

        $holidays = \App\Modules\Hr\Models\PublicHoliday::active()
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere('is_recurring', true);
            })
            ->get();

        return $employees->map(function (Employee $employee) use ($startTime, $endTime, $requiredSkillIds, $requiredCertificationIds, $holidays) {
            $unavailabilityReasons = [];

            // A. Check approved leave
            if ($employee->leaveRequests->isNotEmpty()) {
                $leave = $employee->leaveRequests->first();
                $unavailabilityReasons[] = "On approved leave ({$leave->start_date->format('Y-m-d')} to {$leave->end_date->format('Y-m-d')})";
            }

            // A2. Check Public Holiday
            $matchingHoliday = $holidays->first(function ($h) use ($startTime, $employee) {
                if (! $h->matchesDate($startTime)) {
                    return false;
                }
                if ($h->applies_to_all) {
                    return true;
                }
                return $h->department_id === $employee->department_id;
            });
            if ($matchingHoliday) {
                $hName = $matchingHoliday->getTranslation('name', app()->getLocale()) ?? $matchingHoliday->name;
                $unavailabilityReasons[] = "Public holiday: {$hName}";
            }

            // B. Check Skills match
            $matchedSkillsCount = 0;
            $employeeSkillMap = $employee->employeeSkills->keyBy('skill_id');
            $missingSkills = [];

            foreach ($requiredSkillIds as $skillId) {
                if ($employeeSkillMap->has($skillId)) {
                    $matchedSkillsCount++;
                } else {
                    $missingSkills[] = $skillId;
                }
            }

            $skillMatchRate = count($requiredSkillIds) > 0
                ? round(($matchedSkillsCount / count($requiredSkillIds)) * 100)
                : 100;

            if (count($missingSkills) > 0) {
                $unavailabilityReasons[] = 'Missing required skills: '.count($missingSkills);
            }

            // C. Check Certifications validity
            $validCertsCount = 0;
            $missingCerts = [];
            $expiredCerts = [];

            foreach ($requiredCertificationIds as $certId) {
                $empCert = $employee->certifications->firstWhere('certification_id', $certId);
                if (! $empCert) {
                    $missingCerts[] = $certId;

                    continue;
                }

                $isExpired = $empCert->expiry_date && Carbon::parse($empCert->expiry_date)->isBefore($endTime);
                if ($empCert->status !== 'valid' || $isExpired) {
                    $expiredCerts[] = $empCert->certification->name ?? $certId;
                } else {
                    $validCertsCount++;
                }
            }

            if (count($missingCerts) > 0) {
                $unavailabilityReasons[] = 'Missing required certifications: '.count($missingCerts);
            }

            if (count($expiredCerts) > 0) {
                $unavailabilityReasons[] = 'Expired certifications: '.implode(', ', $expiredCerts);
            }

            $certMatchRate = count($requiredCertificationIds) > 0
                ? round(($validCertsCount / count($requiredCertificationIds)) * 100)
                : 100;

            $isAvailable = empty($unavailabilityReasons);

            return [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
                'work_email' => $employee->work_email,
                'work_phone' => $employee->work_phone,
                'department' => $employee->department ? [
                    'id' => $employee->department->id,
                    'name' => $employee->department->name,
                ] : null,
                'position' => $employee->position ? [
                    'id' => $employee->position->id,
                    'title' => $employee->position->title,
                ] : null,
                'current_team' => $employee->currentTeam ? [
                    'id' => $employee->currentTeam->id,
                    'name' => $employee->currentTeam->name,
                ] : null,
                'is_available' => $isAvailable,
                'skill_match_percentage' => $skillMatchRate,
                'certification_match_percentage' => $certMatchRate,
                'reasons' => $unavailabilityReasons,
                'active_skills' => $employee->employeeSkills->map(fn ($es) => [
                    'id' => $es->skill_id,
                    'name' => $es->skill->name ?? '',
                    'level' => $es->level,
                    'is_verified' => $es->is_verified,
                ]),
                'active_certifications' => $employee->certifications->map(fn ($ec) => [
                    'id' => $ec->certification_id,
                    'name' => $ec->certification->name ?? '',
                    'status' => $ec->status,
                    'expiry_date' => $ec->expiry_date ? $ec->expiry_date->format('Y-m-d') : null,
                    'is_valid' => $ec->is_valid,
                ]),
            ];
        });
    }
}
