<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Department $dept;
    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        \App\Models\Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->dept = Department::create([
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);

        $this->position = Position::create([
            'department_id' => $this->dept->id,
            'title' => 'Senior Technician',
            'code' => 'SR-TECH',
            'standard_hourly_rate' => 35.00,
        ]);
    }

    public function test_can_create_employee_with_auto_generated_reference(): void
    {
        $payload = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@waveplus.local',
            'phone' => '+33 6 12 34 56 78',
            'hire_date' => '2026-01-15',
            'department_id' => $this->dept->id,
            'position_id' => $this->position->id,
            'employment_type' => 'full_time',
            'contract_type' => 'permanent',
            'base_salary' => 45000.00,
            'hourly_rate' => 28.00,
        ];

        $response = $this->postJson('/api/hr/employees', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.first_name.en', 'Jean');
        $response->assertJsonPath('data.last_name.en', 'Dupont');
        $response->assertJsonPath('data.hire_date', '2026-01-15');
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonMissingPath('data.employeeNumber');
        $response->assertJsonMissingPath('data.firstName');
        $response->assertJsonMissingPath('data.lastName');
        $response->assertJsonMissingPath('data.fullName');
        $response->assertJsonMissingPath('data.workEmail');
        $response->assertJsonMissingPath('data.workPhone');
        $response->assertJsonMissingPath('data.hireDate');
        $response->assertJsonMissingPath('data.avatarUrl');
        $response->assertJsonMissingPath('data.departmentId');
        $response->assertJsonMissingPath('data.positionId');
        $response->assertJsonMissingPath('data.currentTeam');

        // Confirm reference generated like EMP-XXXXX
        $empNumber = $response->json('data.employee_number');
        $this->assertNotNull($empNumber);
        $this->assertStringStartsWith('EMP-', $empNumber);

        // Employee must exist in hr_employees independently without user link
        $employee = Employee::where('work_email', 'jean.dupont@waveplus.local')->first();
        $this->assertNotNull($employee);
        $this->assertNull($employee->user_id);
    }

    public function test_employee_can_optionally_link_to_user_account(): void
    {
        $appUser = User::factory()->create(['email' => 'tech.user@waveplus.local']);

        $employee = Employee::create([
            'first_name' => 'Marc',
            'last_name' => 'Valentin',
            'work_email' => 'marc.valentin@waveplus.local',
            'hire_date' => '2026-02-01',
            'status' => 'active',
            'user_id' => $appUser->id,
        ]);

        $this->assertEquals($appUser->id, $employee->user_id);
        $this->assertInstanceOf(User::class, $employee->user);
    }

    public function test_can_update_employee_details(): void
    {
        $employee = Employee::create([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'work_email' => 'alice.martin@waveplus.local',
            'hire_date' => '2026-03-01',
            'department_id' => $this->dept->id,
            'position_id' => $this->position->id,
            'status' => 'active',
        ]);

        $updatePayload = [
            'first_name' => 'Alice',
            'last_name' => 'Martin-Smith',
            'work_email' => 'alice.smith@waveplus.local',
            'department_id' => $this->dept->id,
            'position_id' => $this->position->id,
            'notes' => 'Promoted to lead tech',
        ];

        $response = $this->putJson("/api/hr/employees/{$employee->id}", $updatePayload);

        $response->assertOk();
        $response->assertJsonPath('data.last_name.en', 'Martin-Smith');
        $response->assertJsonPath('data.work_email', 'alice.smith@waveplus.local');

        $this->assertTrue(
            Employee::whereKey($employee->id)
                ->where('last_name->en', 'Martin-Smith')
                ->where('work_email', 'alice.smith@waveplus.local')
                ->exists()
        );
    }

    public function test_status_transition_creates_employment_history(): void
    {
        $employee = Employee::create([
            'first_name' => 'Luc',
            'last_name' => 'Moreau',
            'work_email' => 'luc.moreau@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $this->dept->id,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/hr/employees/{$employee->id}/change-status", [
            'status' => 'suspended',
            'reason' => 'Administrative inquiry in progress',
            'effective_date' => '2026-09-15',
        ]);

        $response->assertOk();
        $this->assertEquals('suspended', $employee->fresh()->status);

        // Verify employment history logged
        $this->assertDatabaseHas('hr_employment_histories', [
            'employee_id' => $employee->id,
            'reason_for_change' => 'Administrative inquiry in progress',
        ]);
    }

    public function test_termination_status_transition_saves_termination_date_and_history(): void
    {
        $employee = Employee::create([
            'first_name' => 'Nina',
            'last_name' => 'Laurent',
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/hr/employees/{$employee->id}/change-status", [
            'status' => 'terminated',
            'reason' => 'Contract ended',
            'effective_date' => '2026-10-01',
        ]);

        $response->assertOk();
        $this->assertSame('terminated', $employee->fresh()->status);
        $this->assertSame('2026-10-01', $employee->fresh()->termination_date->toDateString());
        $this->assertDatabaseHas('hr_employment_histories', [
            'employee_id' => $employee->id,
            'start_date' => '2026-10-01 00:00:00',
            'reason_for_change' => 'Contract ended',
        ]);
    }
}
