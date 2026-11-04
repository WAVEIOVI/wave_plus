<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $hrRoleTemplates = [
            'HR Manager' => [
                'read-PeopleSkills',
                'read-hr-dashboard',
                'hr.employees.view',
                'hr.employees.create',
                'hr.employees.edit',
                'hr.employees.archive',
                'hr.employees.view-sensitive',
                'hr.departments.view',
                'hr.departments.manage',
                'hr.positions.view',
                'hr.positions.manage',
                'hr.teams.view',
                'hr.teams.manage',
                'hr.attendance.view',
                'hr.attendance.manage',
                'hr.attendance.correct',
                'hr.leave.view',
                'hr.leave.request',
                'hr.leave.approve',
                'hr.leave.manage',
                'hr.recruitment.view',
                'hr.recruitment.manage',
                'hr.recruitment.hire',
                'hr.training.view',
                'hr.training.manage',
                'hr.certifications.view',
                'hr.certifications.manage',
                'hr.skills.view',
                'hr.skills.manage',
                'hr.workforce.view',
                'hr.workforce.assign',
                'hr.appraisals.view',
                'hr.appraisals.manage',
                'hr.fleet.view',
                'hr.fleet.manage',
            ],
            'HR Officer' => [
                'read-PeopleSkills',
                'read-hr-dashboard',
                'hr.employees.view',
                'hr.employees.create',
                'hr.employees.edit',
                'hr.departments.view',
                'hr.positions.view',
                'hr.teams.view',
                'hr.attendance.view',
                'hr.leave.view',
                'hr.leave.request',
                'hr.recruitment.view',
                'hr.recruitment.manage',
                'hr.training.view',
                'hr.training.manage',
                'hr.certifications.view',
                'hr.certifications.manage',
                'hr.skills.view',
                'hr.skills.manage',
                'hr.workforce.view',
            ],
            'Attendance Manager' => [
                'hr.attendance.view',
                'hr.attendance.manage',
                'hr.attendance.correct',
                'hr.attendance.kiosk',
                'hr.workforce.view',
                'hr.workforce.assign',
                'hr.employees.view',
            ],
            'Leave Approver' => [
                'hr.leave.view',
                'hr.leave.approve',
                'hr.leave.request',
                'hr.employees.view',
                'hr.attendance.view',
            ],
            'Recruiter' => [
                'hr.recruitment.view',
                'hr.recruitment.manage',
                'hr.recruitment.hire',
                'hr.positions.view',
                'hr.departments.view',
                'hr.employees.view',
            ],
            'Fleet Manager' => [
                'hr.fleet.view',
                'hr.fleet.manage',
                'hr.fleet.archive',
                'hr.employees.view',
            ],
            'Employee Self-Service' => [
                'hr.leave.request',
            ],
        ];

        foreach ($hrRoleTemplates as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            // Ensure all listed permissions exist before syncing
            foreach ($permissions as $permName) {
                Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
            }

            $role->syncPermissions($permissions);
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roleNames = [
            'HR Manager',
            'HR Officer',
            'Attendance Manager',
            'Leave Approver',
            'Recruiter',
            'Fleet Manager',
            'Employee Self-Service',
        ];

        Role::whereIn('name', $roleNames)->delete();
    }
};
