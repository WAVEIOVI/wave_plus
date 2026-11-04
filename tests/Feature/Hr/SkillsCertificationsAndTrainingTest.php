<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\Certification;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeCertification;
use App\Modules\Hr\Models\EmployeeSkill;
use App\Modules\Hr\Models\EmployeeTraining;
use App\Modules\Hr\Models\Skill;
use App\Modules\Hr\Models\TrainingProgram;
use App\Modules\Hr\Models\TrainingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SkillsCertificationsAndTrainingTest extends TestCase
{
    use RefreshDatabase;

    protected User $hrManager;

    protected User $viewer;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $managerRole = Role::findOrCreate('HR Manager', 'web');
        $viewerRole = Role::findOrCreate('HR Viewer', 'web');

        $permissions = [
            'hr.skills.view',
            'hr.skills.manage',
            'hr.certifications.view',
            'hr.certifications.manage',
            'hr.training.view',
            'hr.training.manage',
            'hr.employees.view',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $managerRole->givePermissionTo($permissions);
        $viewerRole->givePermissionTo(['hr.skills.view', 'hr.certifications.view', 'hr.training.view', 'hr.employees.view']);

        $this->hrManager = User::factory()->create();
        $this->hrManager->assignRole($managerRole);

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole($viewerRole);

        $this->employee = Employee::create([
            'employee_number' => 'EMP-TEST-001',
            'first_name' => 'Sara',
            'last_name' => 'Ben Ali',
            'work_email' => 'sara.test@waveplus.tn',
            'hire_date' => '2024-01-15',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);
    }

    public function test_hr_manager_can_create_and_manage_skills(): void
    {
        Sanctum::actingAs($this->hrManager);

        $createRes = $this->postJson('/api/hr/skills', [
            'code' => 'SK-WELD-01',
            'category' => 'Technical',
            'name' => ['en' => 'TIG Welding', 'fr' => 'Soudage TIG', 'ar' => 'لحام تيغ'],
            'description' => ['en' => 'Precision arc welding process'],
            'is_active' => true,
        ])->assertStatus(201);

        $skillId = $createRes->json('data.id');
        $this->assertDatabaseHas('hr_skills', ['code' => 'SK-WELD-01']);

        // Update skill
        $this->putJson("/api/hr/skills/{$skillId}", [
            'category' => 'Advanced Fabrication',
            'name' => ['en' => 'Advanced TIG Welding'],
        ])->assertOk();

        $this->assertDatabaseHas('hr_skills', [
            'id' => $skillId,
            'category' => 'Advanced Fabrication',
        ]);

        // Viewer cannot update or delete
        Sanctum::actingAs($this->viewer);
        $this->putJson("/api/hr/skills/{$skillId}", [
            'category' => 'Hacked',
        ])->assertForbidden();

        $this->deleteJson("/api/hr/skills/{$skillId}")->assertForbidden();
    }

    public function test_assign_verify_update_and_delete_employee_skill(): void
    {
        Sanctum::actingAs($this->hrManager);

        $skill = Skill::create([
            'code' => 'SK-ELEC-01',
            'name' => ['en' => 'High Voltage Safety'],
            'category' => 'Safety',
            'is_active' => true,
        ]);

        // Assign skill
        $assignRes = $this->postJson("/api/hr/employees/{$this->employee->id}/skills", [
            'skill_id' => $skill->id,
            'level' => 'intermediate',
            'acquired_date' => '2025-06-01',
            'notes' => 'Completed internal qualification workshop',
        ])->assertOk();

        $empSkillId = $assignRes->json('data.id');
        $this->assertDatabaseHas('hr_employee_skills', [
            'employee_id' => $this->employee->id,
            'skill_id' => $skill->id,
            'level' => 'intermediate',
            'is_verified' => false,
        ]);

        // Verify skill
        $this->putJson("/api/hr/employee-skills/{$empSkillId}/verify", [
            'notes' => 'Verified by Lead Engineer',
        ])->assertOk();

        $this->assertDatabaseHas('hr_employee_skills', [
            'id' => $empSkillId,
            'is_verified' => true,
            'verified_by' => $this->hrManager->id,
        ]);

        // Update skill level
        $this->putJson("/api/hr/employee-skills/{$empSkillId}", [
            'level' => 'advanced',
        ])->assertOk();

        $this->assertDatabaseHas('hr_employee_skills', [
            'id' => $empSkillId,
            'level' => 'advanced',
        ]);

        // Delete employee skill
        $this->deleteJson("/api/hr/employee-skills/{$empSkillId}")->assertOk();
        $this->assertDatabaseMissing('hr_employee_skills', ['id' => $empSkillId]);
    }

    public function test_assign_certification_prevents_duplicate_and_supports_expiry_tracking(): void
    {
        Sanctum::actingAs($this->hrManager);

        $cert = Certification::create([
            'code' => 'CERT-FA-01',
            'name' => ['en' => 'Occupational First Aid'],
            'issuing_authority' => ['en' => 'Red Crescent'],
            'validity_period_months' => 24,
            'is_active' => true,
        ]);

        // Initial assignment
        $res1 = $this->postJson("/api/hr/employees/{$this->employee->id}/certifications", [
            'certification_id' => $cert->id,
            'certificate_number' => 'FA-2025-001',
            'issuing_organization' => 'Red Crescent Tunisia',
            'issue_date' => '2025-01-10',
            'expiry_date' => now()->addDays(20)->toDateString(), // expiring soon
            'status' => 'valid',
        ])->assertStatus(201);

        $certRecordId = $res1->json('data.id');

        // Re-assigning the same certification should renew/update without duplicate row
        $res2 = $this->postJson("/api/hr/employees/{$this->employee->id}/certifications", [
            'certification_id' => $cert->id,
            'certificate_number' => 'FA-2025-RENEWED',
            'issuing_organization' => 'Red Crescent Tunisia',
            'issue_date' => now()->toDateString(),
            'expiry_date' => now()->addYears(2)->toDateString(),
            'status' => 'valid',
        ])->assertStatus(200);

        $this->assertEquals($certRecordId, $res2->json('data.id'));
        $this->assertEquals(1, EmployeeCertification::where('employee_id', $this->employee->id)->where('certification_id', $cert->id)->count());

        // Update certification details
        $this->putJson("/api/hr/employee-certifications/{$certRecordId}", [
            'notes' => 'Updated after audit',
        ])->assertOk();

        $this->assertDatabaseHas('hr_employee_certifications', [
            'id' => $certRecordId,
            'certificate_number' => 'FA-2025-RENEWED',
            'notes' => 'Updated after audit',
        ]);

        // Verify certification
        $this->putJson("/api/hr/employee-certifications/{$certRecordId}/verify")->assertOk();
        $this->assertDatabaseHas('hr_employee_certifications', [
            'id' => $certRecordId,
            'status' => 'valid',
            'verified_by' => $this->hrManager->id,
        ]);
    }

    public function test_expiring_certifications_and_employee_development_summary(): void
    {
        Sanctum::actingAs($this->hrManager);

        $cert = Certification::create([
            'code' => 'CERT-FORK-01',
            'name' => ['en' => 'Forklift Operation License'],
            'is_active' => true,
        ]);

        // Create certification expiring in 15 days
        EmployeeCertification::create([
            'employee_id' => $this->employee->id,
            'certification_id' => $cert->id,
            'certificate_number' => 'FORK-999',
            'issue_date' => now()->subYears(1)->toDateString(),
            'expiry_date' => now()->addDays(15)->toDateString(),
            'status' => 'valid',
        ]);

        // Expiring query
        $expiringRes = $this->getJson('/api/hr/certifications/expiring?days=30')->assertOk();
        $this->assertCount(1, $expiringRes->json('data'));
        $this->assertEquals('FORK-999', $expiringRes->json('data.0.certificate_number'));
        $this->assertEquals('expiring', $expiringRes->json('data.0.status'));

        // Employee development summary
        $summaryRes = $this->getJson("/api/hr/employees/{$this->employee->id}/development-summary")->assertOk();
        $this->assertEquals($this->employee->id, $summaryRes->json('data.employee_id'));
        $this->assertEquals(1, $summaryRes->json('data.certifications.total'));
        $this->assertEquals(1, $summaryRes->json('data.certifications.expiring'));
        $this->assertCount(1, $summaryRes->json('data.expiring_certifications'));
    }

    public function test_training_completion_auto_awards_qualifications_without_duplicates_or_level_downgrade(): void
    {
        Sanctum::actingAs($this->hrManager);

        $skill = Skill::create([
            'code' => 'SK-FIRE-01',
            'name' => ['en' => 'Industrial Fire Fighting'],
            'is_active' => true,
        ]);

        $cert = Certification::create([
            'code' => 'CERT-FIRE-01',
            'name' => ['en' => 'Certified Fire Warden'],
            'validity_period_months' => 12,
            'is_active' => true,
        ]);

        // Give employee an existing EXPERT level in this skill
        EmployeeSkill::create([
            'employee_id' => $this->employee->id,
            'skill_id' => $skill->id,
            'level' => 'expert',
            'acquired_date' => '2023-01-01',
            'is_verified' => true,
        ]);

        // Give employee an existing certification record
        $existingCert = EmployeeCertification::create([
            'employee_id' => $this->employee->id,
            'certification_id' => $cert->id,
            'certificate_number' => 'PREV-FIRE-001',
            'issue_date' => '2024-01-01',
            'expiry_date' => now()->subDays(10)->toDateString(), // expired
            'status' => 'expired',
        ]);

        $program = TrainingProgram::create([
            'code' => 'TR-FIRE-2026',
            'name' => ['en' => 'Fire Safety & Evacuation Refresher'],
            'duration_hours' => 16,
            'target_skill_id' => $skill->id,
            'target_certification_id' => $cert->id,
            'is_active' => true,
        ]);

        $session = TrainingSession::create([
            'training_program_id' => $program->id,
            'session_code' => 'SESS-FIRE-01',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-02',
            'status' => 'in_progress',
        ]);

        // Enroll employee
        $enrollRes = $this->postJson("/api/hr/training/sessions/{$session->id}/enroll", [
            'employee_id' => $this->employee->id,
        ])->assertStatus(201);

        $participantId = $enrollRes->json('data.id');

        // Complete training
        $this->postJson("/api/hr/training/participants/{$participantId}/complete", [
            'status' => 'completed',
            'score' => 95,
            'certificate_number' => 'CERT-FIRE-2026-WIN',
        ])->assertOk();

        // 1. Skill level must PRESERVE 'expert', not downgraded to 'intermediate'
        $empSkill = EmployeeSkill::where('employee_id', $this->employee->id)->where('skill_id', $skill->id)->first();
        $this->assertNotNull($empSkill);
        $this->assertEquals('expert', $empSkill->level);
        $this->assertTrue($empSkill->is_verified);

        // 2. Certification record must be RENEWED on the existing record, NOT duplicated
        $certCount = EmployeeCertification::where('employee_id', $this->employee->id)->where('certification_id', $cert->id)->count();
        $this->assertEquals(1, $certCount, 'Must not duplicate existing certification row');

        $refreshedCert = $existingCert->fresh();
        $this->assertEquals('valid', $refreshedCert->status);
        $this->assertEquals('CERT-FIRE-2026-WIN', $refreshedCert->certificate_number);
        $this->assertTrue($refreshedCert->expiry_date->isFuture());
    }

    public function test_batch_attendance_and_completion(): void
    {
        Sanctum::actingAs($this->hrManager);

        $program = TrainingProgram::create([
            'code' => 'TR-BASIC-SAFETY',
            'name' => ['en' => 'Basic Site Safety'],
            'duration_hours' => 8,
            'is_active' => true,
        ]);

        $session = TrainingSession::create([
            'training_program_id' => $program->id,
            'session_code' => 'SESS-SAFETY-01',
            'start_date' => '2026-05-10',
            'end_date' => '2026-05-10',
            'status' => 'in_progress',
        ]);

        $emp2 = Employee::create([
            'employee_number' => 'EMP-TEST-002',
            'first_name' => 'Ali',
            'last_name' => 'Trabelsi',
            'work_email' => 'ali.test@waveplus.tn',
            'hire_date' => '2024-02-01',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);

        $p1 = EmployeeTraining::create([
            'training_session_id' => $session->id,
            'employee_id' => $this->employee->id,
            'status' => 'enrolled',
        ]);

        $p2 = EmployeeTraining::create([
            'training_session_id' => $session->id,
            'employee_id' => $emp2->id,
            'status' => 'enrolled',
        ]);

        // Batch attendance
        $this->postJson("/api/hr/training/sessions/{$session->id}/batch-attendance", [
            'participants' => [
                [
                    'id' => $p1->id,
                    'status' => 'completed',
                    'score' => 90,
                    'certificate_number' => 'SAF-001',
                ],
                [
                    'id' => $p2->id,
                    'status' => 'attended',
                    'score' => 70,
                ],
            ],
        ])->assertOk();

        $this->assertEquals('completed', $p1->fresh()->status);
        $this->assertEquals(90, $p1->fresh()->score);
        $this->assertEquals('attended', $p2->fresh()->status);
        $this->assertEquals(70, $p2->fresh()->score);
    }
}
