<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\AttendanceException;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use App\Modules\Hr\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorized;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        Permission::findOrCreate('hr.employees.view', 'web');
        Permission::findOrCreate('read-hr-dashboard', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->unauthorized = User::factory()->create();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/hr/dashboard-stats')->assertUnauthorized();
    }

    public function test_unauthorized_user_cannot_access_hr_dashboard(): void
    {
        Sanctum::actingAs($this->unauthorized);

        $this->getJson('/api/hr/dashboard-stats')->assertForbidden();
    }

    public function test_user_with_hr_view_permission_can_access_dashboard(): void
    {
        $role = Role::findOrCreate('HR Staff', 'web');
        $role->givePermissionTo('hr.employees.view');
        $this->unauthorized->assignRole($role);

        Sanctum::actingAs($this->unauthorized);

        $this->getJson('/api/hr/dashboard-stats')->assertOk();
    }

    public function test_dashboard_returns_real_aggregated_statistics(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Create real department & employees
        $dept = Department::create([
            'name' => ['en' => 'Software Engineering', 'fr' => 'Ingénierie Logicielle'],
            'code' => 'ENG',
        ]);

        $activeEmp = Employee::create([
            'first_name' => 'Amine',
            'last_name' => 'Mansour',
            'work_email' => 'amine.mansour@waveplus.local',
            'hire_date' => Carbon::today()->toDateString(),
            'department_id' => $dept->id,
            'status' => 'active',
        ]);

        $onLeaveEmp = Employee::create([
            'first_name' => 'Sonia',
            'last_name' => 'Trabelsi',
            'work_email' => 'sonia.trabelsi@waveplus.local',
            'hire_date' => Carbon::today()->subMonths(2)->toDateString(),
            'department_id' => $dept->id,
            'status' => 'on_leave',
        ]);

        // 2. Create real leave request
        $leaveType = LeaveType::create([
            'name' => 'Annual Leave',
            'code' => 'AL-DASH',
            'is_paid' => true,
            'days_per_year' => 21,
        ]);

        LeaveRequest::create([
            'employee_id' => $onLeaveEmp->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addDays(3)->toDateString(),
            'total_days' => 4,
            'status' => 'submitted',
        ]);

        // 3. Create real fleet vehicle
        Vehicle::create([
            'fleet_code' => 'FL-DASH-01',
            'plate_number' => '220-TN-0001',
            'label' => 'Logistics Express Van',
            'type' => 'van',
            'ownership_type' => 'company',
            'status' => 'available',
        ]);

        $response = $this->getJson('/api/hr/dashboard-stats');

        $response->assertOk();

        // Check top-level payload keys
        $response->assertJsonStructure([
            'data' => [
                'kpis' => [
                    'employees',
                    'attendance',
                    'leave',
                    'workforce',
                    'recruitment',
                    'certifications',
                    'fleet',
                    'appraisals',
                ],
                'headcount' => [
                    'total',
                    'active',
                    'on_leave',
                    'suspended',
                    'terminated',
                    'department_distribution',
                    'monthly_growth',
                ],
                'today_attendance',
                'leave' => [
                    'pending_approval',
                    'on_leave_today',
                    'upcoming_7_days',
                ],
                'workforce',
                'recruitment',
                'certifications',
                'training',
                'appraisals',
                'fleet' => [
                    'total_vehicles',
                    'available',
                    'in_use',
                    'maintenance',
                ],
                'recent_activity',
            ],
        ]);

        // Assert accurate real values from the database
        $response->assertJsonPath('data.headcount.total', 2);
        $response->assertJsonPath('data.headcount.active', 1);
        $response->assertJsonPath('data.headcount.on_leave', 1);
        $response->assertJsonPath('data.leave.pending_approval', 1);
        $response->assertJsonPath('data.fleet.total_vehicles', 1);
        $response->assertJsonPath('data.fleet.available', 1);
    }

    public function test_dashboard_counts_open_attendance_exceptions_as_pending(): void
    {
        Sanctum::actingAs($this->admin);

        $employee = Employee::create([
            'first_name' => 'Nadia',
            'last_name' => 'Ben Ali',
            'work_email' => 'nadia.benali@waveplus.local',
            'hire_date' => Carbon::today()->toDateString(),
            'status' => 'active',
        ]);

        AttendanceException::create([
            'employee_id' => $employee->id,
            'date' => Carbon::today()->toDateString(),
            'exception_type' => 'late_arrival',
            'review_status' => AttendanceException::REVIEW_OPEN,
        ]);

        AttendanceException::create([
            'employee_id' => $employee->id,
            'date' => Carbon::today()->toDateString(),
            'exception_type' => 'early_departure',
            'review_status' => AttendanceException::REVIEW_RESOLVED,
        ]);

        $this->getJson('/api/hr/dashboard-stats')
            ->assertOk()
            ->assertJsonPath('data.today_attendance.pending_exceptions_count', 1);
    }
}
