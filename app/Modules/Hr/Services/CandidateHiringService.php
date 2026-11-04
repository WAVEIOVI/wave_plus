<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmploymentHistory;
use App\Modules\Hr\Models\HrAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CandidateHiringService
{
    /**
     * Atomically convert a Candidate to an Employee.
     */
    public function hireCandidate(
        Candidate $candidate,
        string $departmentId,
        string $positionId,
        Carbon $hireDate,
        ?string $managerId = null,
        ?string $workEmail = null,
        ?string $workPhone = null,
        string $employmentType = 'full_time',
        string $contractType = 'permanent',
        ?string $actorId = null
    ): Employee {
        return DB::transaction(function () use (
            $candidate, $departmentId, $positionId, $hireDate, $managerId,
            $workEmail, $workPhone, $employmentType, $contractType, $actorId
        ) {
            if ($candidate->status === 'hired') {
                throw new InvalidArgumentException("Candidate {$candidate->full_name} is already hired.");
            }

            // Guard against duplicate hire if an employee record already references this candidate
            $existingEmployee = Employee::where('notes', 'like', "%[{$candidate->reference}]%")->first();
            if ($existingEmployee) {
                throw new InvalidArgumentException("Candidate {$candidate->full_name} ({$candidate->reference}) is already hired as employee {$existingEmployee->employee_number}.");
            }

            $effectiveEmail = $workEmail ?? $candidate->email;
            if ($effectiveEmail && Employee::where('work_email', $effectiveEmail)->exists()) {
                throw new InvalidArgumentException("An employee with work email [{$effectiveEmail}] already exists.");
            }

            // Create new Employee
            $employee = Employee::create([
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'personal_email' => $candidate->email,
                'personal_phone' => $candidate->phone,
                'work_email' => $effectiveEmail,
                'work_phone' => $workPhone,
                'hire_date' => $hireDate->toDateString(),
                'status' => 'active',
                'department_id' => $departmentId,
                'position_id' => $positionId,
                'manager_id' => $managerId,
                'notes' => "Hired from recruitment candidate record [{$candidate->reference}].",
            ]);

            // Copy candidate documents to employee
            foreach ($candidate->getMedia('resume') as $media) {
                $media->copy($employee, 'documents');
            }

            foreach ($candidate->getMedia('documents') as $media) {
                $media->copy($employee, 'documents');
            }

            // Create initial employment history record
            EmploymentHistory::create([
                'employee_id' => $employee->id,
                'position_id' => $positionId,
                'department_id' => $departmentId,
                'manager_id' => $managerId,
                'employment_type' => $employmentType,
                'contract_type' => $contractType,
                'start_date' => $hireDate->toDateString(),
                'reason_for_change' => 'Initial hire',
            ]);

            // Update candidate status
            $candidate->update([
                'status' => 'hired',
                'notes' => trim($candidate->notes."\nHired as employee: {$employee->employee_number}"),
            ]);

            // Audit log
            HrAuditLog::create([
                'auditable_type' => Employee::class,
                'auditable_id' => $employee->id,
                'actor_id' => $actorId,
                'action' => 'HIRE_CANDIDATE',
                'new_values' => [
                    'candidate_id' => $candidate->id,
                    'candidate_reference' => $candidate->reference,
                    'employee_number' => $employee->employee_number,
                ],
                'reason' => 'Candidate hired and converted to employee.',
            ]);

            return $employee;
        });
    }
}
