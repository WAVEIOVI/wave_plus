<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeEmailSearchService
{
    /**
     * Search employees and users for email auto-completion.
     *
     * Only accessible to users with HR or User management permissions.
     *
     * @return Collection<int, array{
     *   entity_type: 'employee'|'user',
     *   entity_id: string,
     *   entity_display_name: string,
     *   email: string,
     *   source: 'employee',
     *   contact_name: string,
     *   name: string,
     *   type: string,
     * }>
     */
    public function search(User $user, string $query, int $limit = 20): Collection
    {
        $searchTerm = trim($query);
        if (mb_strlen($searchTerm) === 1) {
            return collect();
        }

        // Authorization guard: avoid exposing employee/user data to unauthorized users
        if (! $this->canAccessEmployeeDirectory($user)) {
            return collect();
        }

        $results = collect();
        $wildcard = $searchTerm !== '' ? "%{$searchTerm}%" : null;

        // 1. Search hr_employees (if hr_employees table exists)
        if (\Illuminate\Support\Facades\Schema::hasTable('hr_employees')) {
            $employeesQuery = DB::table('hr_employees')
                ->leftJoin('hr_departments', 'hr_employees.department_id', '=', 'hr_departments.id')
                ->leftJoin('hr_positions', 'hr_employees.position_id', '=', 'hr_positions.id')
                ->select([
                    'hr_employees.id',
                    'hr_employees.employee_number',
                    'hr_employees.first_name',
                    'hr_employees.last_name',
                    'hr_employees.preferred_name',
                    'hr_employees.work_email',
                    'hr_employees.personal_email',
                    'hr_departments.name as department_name',
                    'hr_positions.title as position_title',
                ])
                ->whereNull('hr_employees.deleted_at')
                ->where(function ($q) {
                    $q->whereNotNull('hr_employees.work_email')->where('hr_employees.work_email', '!=', '')
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('hr_employees.personal_email')->where('hr_employees.personal_email', '!=', '');
                        });
                });

            if ($wildcard) {
                $employeesQuery->where(function ($q) use ($wildcard) {
                    $q->where('hr_employees.work_email', 'like', $wildcard)
                        ->orWhere('hr_employees.personal_email', 'like', $wildcard)
                        ->orWhere('hr_employees.first_name', 'like', $wildcard)
                        ->orWhere('hr_employees.last_name', 'like', $wildcard)
                        ->orWhere('hr_employees.preferred_name', 'like', $wildcard)
                        ->orWhere('hr_employees.employee_number', 'like', $wildcard);
                });
            }

            $employees = $employeesQuery->limit($limit)->get();

            foreach ($employees as $emp) {
                $firstName = $this->localizedJson($emp->preferred_name ?: $emp->first_name);
                $lastName = $this->localizedJson($emp->last_name);
                $fullName = trim("{$firstName} {$lastName}") ?: ($emp->employee_number ?? 'Employee');
                $deptName = $this->localizedJson($emp->department_name) ?: null;
                $jobTitle = $this->localizedJson($emp->position_title) ?: null;

                // Check work email first
                $workEmail = trim((string) $emp->work_email);
                if ($workEmail !== '' && filter_var($workEmail, FILTER_VALIDATE_EMAIL)) {
                    $results->push([
                        'entity_type' => 'employee',
                        'entity_id' => (string) $emp->id,
                        'entity_display_name' => $fullName,
                        'email' => $workEmail,
                        'source' => 'employee',
                        'contact_id' => null,
                        'contact_name' => $fullName,
                        'job_title' => $jobTitle,
                        'department' => $deptName,
                        'name' => $fullName,
                        'type' => 'Employee',
                    ]);
                }

                // If personal email matches specifically and differs from work email
                $personalEmail = trim((string) $emp->personal_email);
                if ($personalEmail !== '' && $personalEmail !== $workEmail && filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
                    if (! $wildcard || str_contains(mb_strtolower($personalEmail), mb_strtolower($searchTerm))) {
                        $results->push([
                            'entity_type' => 'employee',
                            'entity_id' => (string) $emp->id,
                            'entity_display_name' => "{$fullName} (Personal)",
                            'email' => $personalEmail,
                            'source' => 'employee',
                            'contact_id' => null,
                            'contact_name' => $fullName,
                            'job_title' => $jobTitle,
                            'department' => $deptName,
                            'name' => "{$fullName} (Personal)",
                            'type' => 'Employee',
                        ]);
                    }
                }
            }
        }

        // 2. Also search system users (for users that may not have an hr_employees row)
        $usersQuery = DB::table('users')
            ->select(['id', 'name', 'email'])
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($wildcard) {
            $usersQuery->where(function ($q) use ($wildcard) {
                $q->where('email', 'like', $wildcard)
                    ->orWhere('name', 'like', $wildcard);
            });
        }

        $users = $usersQuery->limit($limit)->get();

        foreach ($users as $u) {
            $email = trim((string) $u->email);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $userName = trim((string) $u->name) ?: 'User';

            $results->push([
                'entity_type' => 'employee',
                'entity_id' => (string) $u->id,
                'entity_display_name' => $userName,
                'email' => $email,
                'source' => 'employee',
                'contact_id' => null,
                'contact_name' => $userName,
                'job_title' => null,
                'department' => null,
                'name' => $userName,
                'type' => 'User',
            ]);
        }

        return $results->unique(fn ($item) => $item['entity_id'].':'.$item['email'])->values();
    }

    /**
     * Search employees with department and position relations, optionally filtered by departments.
     *
     * @param  array<string>|null  $departmentIds
     * @return Collection<int, array{
     *   id: string,
     *   entity_id: string,
     *   entity_type: 'employee',
     *   source: 'employee',
     *   name: string,
     *   entity_display_name: string,
     *   work_email: string,
     *   email: string,
     *   department: string|null,
     *   department_id: string|null,
     *   position: string|null,
     *   job_title: string|null,
     *   type: string,
     * }>
     */
    public function searchEmployees(User $user, string $query = '', int $limit = 50, ?array $departmentIds = null): Collection
    {
        if (! $this->canAccessEmployeeDirectory($user)) {
            return collect();
        }

        $results = collect();
        $searchTerm = trim($query);
        $wildcard = $searchTerm !== '' ? "%{$searchTerm}%" : null;

        if (\Illuminate\Support\Facades\Schema::hasTable('hr_employees')) {
            $queryBuilder = \App\Modules\Hr\Models\Employee::query()
                ->with(['department', 'position'])
                ->where(function ($q) {
                    $q->whereNotNull('work_email')->where('work_email', '!=', '')
                        ->orWhere(function ($q2) {
                            $q2->whereNotNull('personal_email')->where('personal_email', '!=', '');
                        });
                });

            if (! empty($departmentIds)) {
                $queryBuilder->whereIn('department_id', $departmentIds);
            }

            if ($wildcard) {
                $queryBuilder->where(function ($q) use ($wildcard) {
                    $q->where('work_email', 'like', $wildcard)
                        ->orWhere('personal_email', 'like', $wildcard)
                        ->orWhere('first_name', 'like', $wildcard)
                        ->orWhere('last_name', 'like', $wildcard)
                        ->orWhere('preferred_name', 'like', $wildcard)
                        ->orWhere('employee_number', 'like', $wildcard);
                });
            }

            $employees = $queryBuilder->limit($limit)->get();

            foreach ($employees as $emp) {
                $firstNameRaw = $emp->getRawOriginal('preferred_name') ?: $emp->getRawOriginal('first_name');
                $lastNameRaw = $emp->getRawOriginal('last_name');
                $firstName = $this->localizedJson(is_array($firstNameRaw) ? json_encode($firstNameRaw) : (string) $firstNameRaw);
                $lastName = $this->localizedJson(is_array($lastNameRaw) ? json_encode($lastNameRaw) : (string) $lastNameRaw);
                $fullName = trim("{$firstName} {$lastName}") ?: ($emp->employee_number ?? 'Employee');

                $workEmail = trim((string) $emp->work_email);
                $personalEmail = trim((string) $emp->personal_email);
                $primaryEmail = (filter_var($workEmail, FILTER_VALIDATE_EMAIL) ? $workEmail : (filter_var($personalEmail, FILTER_VALIDATE_EMAIL) ? $personalEmail : ''));

                if ($primaryEmail === '') {
                    continue;
                }

                $deptName = null;
                if ($emp->department) {
                    $deptNameRaw = $emp->department->getRawOriginal('name');
                    $deptName = $this->localizedJson(is_array($deptNameRaw) ? json_encode($deptNameRaw) : (string) $deptNameRaw);
                }

                $posTitle = null;
                if ($emp->position) {
                    $posTitleRaw = $emp->position->getRawOriginal('title');
                    $posTitle = $this->localizedJson(is_array($posTitleRaw) ? json_encode($posTitleRaw) : (string) $posTitleRaw);
                }

                $results->push([
                    'id' => (string) $emp->id,
                    'entity_id' => (string) $emp->id,
                    'entity_type' => 'employee',
                    'source' => 'employee',
                    'name' => $fullName,
                    'entity_display_name' => $fullName,
                    'work_email' => $primaryEmail,
                    'email' => $primaryEmail,
                    'department' => $deptName ?: null,
                    'department_id' => $emp->department_id ? (string) $emp->department_id : null,
                    'position' => $posTitle ?: null,
                    'job_title' => $posTitle ?: null,
                    'type' => 'Employee',
                ]);
            }
        }

        // Include system users if no department filter is active and query matches
        if (empty($departmentIds) && \Illuminate\Support\Facades\Schema::hasTable('users')) {
            $usersQuery = DB::table('users')
                ->select(['id', 'name', 'email'])
                ->whereNotNull('email')
                ->where('email', '!=', '');

            if ($wildcard) {
                $usersQuery->where(function ($q) use ($wildcard) {
                    $q->where('email', 'like', $wildcard)
                        ->orWhere('name', 'like', $wildcard);
                });
            }

            $users = $usersQuery->limit(20)->get();

            foreach ($users as $u) {
                $email = trim((string) $u->email);
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $userName = trim((string) $u->name) ?: 'User';

                $results->push([
                    'id' => (string) $u->id,
                    'entity_id' => (string) $u->id,
                    'entity_type' => 'employee',
                    'source' => 'employee',
                    'name' => $userName,
                    'entity_display_name' => $userName,
                    'work_email' => $email,
                    'email' => $email,
                    'department' => null,
                    'department_id' => null,
                    'position' => null,
                    'job_title' => null,
                    'type' => 'User',
                ]);
            }
        }

        return $results->unique(fn ($item) => $item['entity_id'].':'.$item['email'])->values();
    }

    private function canAccessEmployeeDirectory(User $user): bool
    {
        return $user->hasRole(['Super Admin', 'Admin', 'Responsable'])
            || $user->can('read-PeopleSkills')
            || $user->can('read-users');
    }

    private function localizedJson(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return (string) ($decoded['en'] ?? $decoded['fr'] ?? $decoded['ar'] ?? reset($decoded) ?: '');
        }

        return trim($value, '"');
    }
}
