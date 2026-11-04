<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\Candidate;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Interview;
use App\Modules\Hr\Models\JobOpening;
use App\Modules\Hr\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecruitmentAndHiringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $recruiter;

    private User $unauthorizedUser;

    private Department $dept;

    private Position $pos;

    private JobOpening $opening;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Create Spatie Permissions & Roles
        $permissions = [
            'read-PeopleSkills',
            'hr.recruitment.view',
            'hr.recruitment.manage',
            'hr.recruitment.hire',
            'hr.employees.view',
            'hr.employees.create',
            'hr.employees.edit',
            'hr.departments.view',
            'hr.positions.view',
        ];

        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $superAdminRole = Role::findOrCreate('Super Admin', 'web');
        $recruiterRole = Role::findOrCreate('Recruiter', 'web');
        $recruiterRole->givePermissionTo([
            'hr.recruitment.view',
            'hr.recruitment.manage',
            'hr.recruitment.hire',
            'hr.positions.view',
            'hr.departments.view',
            'hr.employees.view',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole($superAdminRole);

        $this->recruiter = User::factory()->create();
        $this->recruiter->assignRole($recruiterRole);

        $this->unauthorizedUser = User::factory()->create();

        $this->dept = Department::create([
            'name' => 'Field Services',
            'code' => 'FSV',
        ]);

        $this->pos = Position::create([
            'department_id' => $this->dept->id,
            'title' => 'Fire Protection Engineer',
            'code' => 'FPE-01',
            'standard_hourly_rate' => 35.00,
        ]);

        $this->opening = JobOpening::create([
            'title' => 'Fire Protection Engineer Role',
            'department_id' => $this->dept->id,
            'position_id' => $this->pos->id,
            'openings_count' => 2,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_unauthorized_user_cannot_access_recruitment(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);

        $response = $this->getJson('/api/hr/recruitment/candidates');
        $response->assertForbidden();

        $response = $this->getJson('/api/hr/recruitment/openings');
        $response->assertForbidden();
    }

    public function test_recruiter_can_list_and_filter_candidates(): void
    {
        Sanctum::actingAs($this->recruiter);

        $c1 = Candidate::create([
            'first_name' => 'Sami',
            'last_name' => 'Khedira',
            'email' => 'sami.k@example.tn',
            'status' => 'applied',
        ]);
        $c1->applications()->create([
            'job_opening_id' => $this->opening->id,
            'application_date' => now()->toDateString(),
            'status' => 'applied',
        ]);

        $c2 = Candidate::create([
            'first_name' => 'Leila',
            'last_name' => 'Trabelsi',
            'email' => 'leila.t@example.tn',
            'status' => 'interview',
        ]);

        // Filter by stage
        $res = $this->getJson('/api/hr/recruitment/candidates?status=applied');
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals('sami.k@example.tn', $res->json('data.0.email'));

        // Filter by opening
        $res = $this->getJson("/api/hr/recruitment/candidates?opening_id={$this->opening->id}");
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals('Sami Khedira', $res->json('data.0.full_name'));

        // Search by name
        $res = $this->getJson('/api/hr/recruitment/candidates?search=Leila');
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals('leila.t@example.tn', $res->json('data.0.email'));
    }

    public function test_candidate_stage_update_logs_audit_trail(): void
    {
        Sanctum::actingAs($this->recruiter);

        $candidate = Candidate::create([
            'first_name' => 'Mounir',
            'last_name' => 'Gharbi',
            'email' => 'mounir.g@example.tn',
            'status' => 'applied',
        ]);

        $res = $this->patchJson("/api/hr/recruitment/candidates/{$candidate->id}/stage", [
            'status' => 'screening',
            'notes' => 'Passed initial phone screen successfully.',
        ]);

        $res->assertOk();
        $this->assertEquals('screening', $candidate->fresh()->status);

        $this->assertDatabaseHas('hr_audit_logs', [
            'auditable_type' => Candidate::class,
            'auditable_id' => $candidate->id,
            'action' => 'STAGE_CHANGE',
            'actor_id' => $this->recruiter->id,
        ]);
    }

    public function test_schedule_interview_and_submit_evaluation(): void
    {
        Sanctum::actingAs($this->recruiter);

        $candidate = Candidate::create([
            'first_name' => 'Yassine',
            'last_name' => 'Chikhaoui',
            'email' => 'yassine.c@example.tn',
            'status' => 'screening',
        ]);

        // 1. Schedule Interview
        $res = $this->postJson("/api/hr/recruitment/candidates/{$candidate->id}/interviews", [
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
            'duration_minutes' => 60,
            'job_opening_id' => $this->opening->id,
            'feedback' => 'Technical evaluation for pump system design.',
        ]);

        $res->assertCreated();
        $interviewId = $res->json('data.id');
        $this->assertNotNull($interviewId);
        $this->assertEquals('interview', $candidate->fresh()->status);

        // 2. Submit Evaluation
        $evalRes = $this->putJson("/api/hr/recruitment/interviews/{$interviewId}", [
            'rating' => 5,
            'feedback' => 'Exceptional technical depth and clear safety culture.',
            'status' => 'completed',
        ]);

        $evalRes->assertOk();
        $this->assertEquals(5, $evalRes->json('data.rating'));
        $this->assertEquals('completed', $evalRes->json('data.status'));

        // Candidate stage auto-advanced to 'evaluated'
        $this->assertEquals('evaluated', $candidate->fresh()->status);

        $this->assertDatabaseHas('hr_audit_logs', [
            'auditable_type' => Interview::class,
            'auditable_id' => $interviewId,
            'action' => 'INTERVIEW_EVALUATION',
        ]);
    }

    public function test_upload_candidate_media_documents(): void
    {
        Sanctum::actingAs($this->recruiter);

        $candidate = Candidate::create([
            'first_name' => 'Amira',
            'last_name' => 'Bouazizi',
            'email' => 'amira.b@example.tn',
            'status' => 'applied',
        ]);

        $pdf = UploadedFile::fake()->createWithContent('curriculum_vitae.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $res = $this->postJson("/api/hr/recruitment/candidates/{$candidate->id}/media", [
            'file' => $pdf,
            'collection' => 'resume',
        ]);

        $res->assertCreated();
        $this->assertCount(1, $candidate->fresh()->getMedia('resume'));

        $mediaId = $res->json('media.id');

        // Delete media
        $delRes = $this->deleteJson("/api/hr/recruitment/candidates/{$candidate->id}/media/{$mediaId}");
        $delRes->assertOk();
        $this->assertCount(0, $candidate->fresh()->getMedia('resume'));
    }

    public function test_hire_candidate_duplicate_prevention(): void
    {
        Sanctum::actingAs($this->recruiter);

        $candidate = Candidate::create([
            'first_name' => 'Walid',
            'last_name' => 'Dhaouadi',
            'email' => 'walid.d@example.tn',
            'status' => 'selected',
        ]);

        $hireData = [
            'department_id' => $this->dept->id,
            'position_id' => $this->pos->id,
            'hire_date' => '2026-11-01',
            'work_email' => 'walid.dhaouadi@waveplus.local',
            'employment_type' => 'full_time',
            'contract_type' => 'permanent',
        ];

        // First hire succeeds
        $res = $this->postJson("/api/hr/recruitment/candidates/{$candidate->id}/hire", $hireData);
        $res->assertCreated();
        $this->assertEquals('hired', $candidate->fresh()->status);

        // Second hire attempt on same candidate (even with a different email) rejected with candidate validation error
        $res2 = $this->postJson("/api/hr/recruitment/candidates/{$candidate->id}/hire", [
            ...$hireData,
            'work_email' => 'walid.another@waveplus.local',
        ]);
        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['candidate']);

        // Attempting to hire another candidate with the same work email fails unique validation
        $otherCandidate = Candidate::create([
            'first_name' => 'Slim',
            'last_name' => 'Riahi',
            'email' => 'slim.r@example.tn',
            'status' => 'selected',
        ]);

        $duplicateEmailData = [
            'department_id' => $this->dept->id,
            'position_id' => $this->pos->id,
            'hire_date' => '2026-11-01',
            'work_email' => 'walid.dhaouadi@waveplus.local', // Already used
        ];

        $res3 = $this->postJson("/api/hr/recruitment/candidates/{$otherCandidate->id}/hire", $duplicateEmailData);
        $res3->assertStatus(422);
        $res3->assertJsonValidationErrors(['work_email']);
    }

    public function test_job_opening_crud_and_status_timestamps(): void
    {
        Sanctum::actingAs($this->recruiter);

        // Create
        $res = $this->postJson('/api/hr/recruitment/openings', [
            'title' => 'Electrical Specialist',
            'department_id' => $this->dept->id,
            'position_id' => $this->pos->id,
            'openings_count' => 3,
            'status' => 'draft',
            'description' => 'Electrical system diagnostics and certifications.',
        ]);

        $res->assertCreated();
        $openingId = $res->json('data.id');
        $this->assertNotNull($openingId);

        // Update to published sets published_at automatically
        $updateRes = $this->putJson("/api/hr/recruitment/openings/{$openingId}", [
            'title' => 'Senior Electrical Specialist',
            'openings_count' => 3,
            'status' => 'published',
        ]);

        $updateRes->assertOk();
        $this->assertEquals('published', $updateRes->json('data.status'));
        $this->assertNotNull($updateRes->json('data.published_at'));

        // Update to closed sets closed_at automatically
        $closeRes = $this->putJson("/api/hr/recruitment/openings/{$openingId}", [
            'title' => 'Senior Electrical Specialist',
            'openings_count' => 3,
            'status' => 'closed',
        ]);

        $closeRes->assertOk();
        $this->assertEquals('closed', $closeRes->json('data.status'));
        $this->assertNotNull($closeRes->json('data.closed_at'));
    }
}
