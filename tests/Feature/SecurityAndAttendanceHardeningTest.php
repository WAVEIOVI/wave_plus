<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Schedule;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\TimeClockEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityAndAttendanceHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $regularUser;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('User', 'web');
        Role::findOrCreate('Attendance Kiosk', 'web');

        Permission::findOrCreate('hr.attendance.manage', 'web');
        Permission::findOrCreate('hr.attendance.view', 'web');
        Permission::findOrCreate('hr.attendance.kiosk', 'web');

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@waveplus.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'can_login' => true,
            'account_status' => 'active',
            'is_suspended' => false,
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->admin = User::create([
            'name' => 'Regular Admin',
            'email' => 'admin@waveplus.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'can_login' => true,
            'account_status' => 'active',
            'is_suspended' => false,
        ]);
        $this->admin->assignRole('Admin');

        $this->regularUser = User::create([
            'name' => 'John Regular',
            'email' => 'john.regular@waveplus.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'can_login' => true,
            'account_status' => 'active',
            'is_suspended' => false,
        ]);
        $this->regularUser->assignRole('User');

        $dept = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $this->employee = Employee::create([
            'first_name' => 'John',
            'last_name' => 'Regular',
            'email' => 'john.regular@waveplus.local',
            'hire_date' => '2026-01-01',
            'department_id' => $dept->id,
            'status' => 'active',
            'user_id' => $this->regularUser->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Point 1: Protect Super Admin privileges & last active Super Admin
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_cannot_create_or_assign_super_admin_role(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/admin/users', [
            'first_name' => 'Hacker',
            'last_name' => 'Admin',
            'email' => 'hacker@waveplus.local',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role' => 'Super Admin',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_cannot_modify_super_admin_user_or_change_password(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->putJson("/api/admin/users/{$this->superAdmin->id}", [
            'first_name' => 'Tampered',
            'last_name' => 'Admin',
            'email' => 'tampered@waveplus.local',
            'role' => 'Admin',
        ]);

        $response->assertStatus(403);

        $passResponse = $this->putJson("/api/admin/users/{$this->superAdmin->id}/password", [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $passResponse->assertStatus(403);
    }

    public function test_admin_cannot_self_escalate_to_super_admin(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->putJson("/api/admin/users/{$this->admin->id}", [
            'first_name' => 'Escalated',
            'last_name' => 'Admin',
            'email' => 'admin@waveplus.local',
            'role' => 'Super Admin',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_delete_or_deactivate_last_active_super_admin(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $deleteResponse = $this->deleteJson("/api/admin/users/{$this->superAdmin->id}");
        $deleteResponse->assertStatus(422);

        $deactivateResponse = $this->putJson("/api/admin/users/{$this->superAdmin->id}", [
            'is_active' => false,
        ]);
        $deactivateResponse->assertStatus(422);
    }

    public function test_admin_cannot_tamper_with_protected_super_admin_role_or_permissions(): void
    {
        Sanctum::actingAs($this->admin);

        $superAdminRole = Role::findByName('Super Admin', 'web');

        $response = $this->putJson("/api/admin/roles/{$superAdminRole->id}", [
            'name' => 'Renamed Super Admin',
            'permissions' => ['hr.attendance.view'],
        ]);

        $response->assertStatus(403);

        $syncResponse = $this->putJson("/api/admin/roles/{$superAdminRole->id}/permissions", [
            'permissions' => ['hr.attendance.view'],
        ]);

        $syncResponse->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Point 2: Secure attendance kiosk authorization
    // ─────────────────────────────────────────────────────────────────────────

    public function test_spoofed_kiosk_device_id_cannot_clock_other_employees(): void
    {
        Sanctum::actingAs($this->regularUser);

        // Another employee
        $colleague = Employee::create([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@waveplus.local',
            'hire_date' => '2026-01-01',
            'status' => 'active',
        ]);

        // Attempting to spoof kiosk terminal identity with client device_id
        $response = $this->postJson('/api/hr/time-clock/scan', [
            'badge' => $colleague->employee_number,
            'device_id' => 'kiosk-terminal',
        ]);

        $response->assertStatus(403);
    }

    public function test_authenticated_kiosk_role_can_clock_employees_and_generates_audit_log(): void
    {
        $kioskUser = User::create([
            'name' => 'Front Desk Kiosk',
            'email' => 'kiosk@waveplus.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'can_login' => true,
            'account_status' => 'active',
            'is_suspended' => false,
        ]);
        $kioskUser->assignRole('Attendance Kiosk');
        Sanctum::actingAs($kioskUser);

        $response = $this->postJson('/api/hr/time-clock/scan', [
            'badge' => $this->employee->employee_number,
            'device_id' => 'kiosk-reception-01',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('action', 'in');

        $this->assertDatabaseHas('hr_audit_logs', [
            'auditable_type' => Employee::class,
            'auditable_id' => $this->employee->id,
            'actor_id' => $kioskUser->id,
            'action' => 'kiosk_punch',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Point 3: Enforce account deactivation consistently & revoke sessions
    // ─────────────────────────────────────────────────────────────────────────

    public function test_deactivated_or_suspended_user_cannot_log_in(): void
    {
        $suspendedUser = User::create([
            'name' => 'Suspended User',
            'email' => 'suspended@waveplus.local',
            'password' => Hash::make('password123'),
            'is_active' => false,
            'can_login' => false,
            'account_status' => 'suspended',
            'is_suspended' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'suspended@waveplus.local',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_deactivated_user_is_blocked_on_subsequent_requests_and_tokens_revoked(): void
    {
        $token = $this->regularUser->createToken('test-token')->plainTextToken;

        // Active request succeeds
        $res1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/user');
        $res1->assertOk();

        // Clear test runner auth guard cache
        auth()->forgetGuards();

        // Deactivate user
        $this->regularUser->update([
            'is_active' => false,
            'can_login' => false,
            'account_status' => 'inactive',
        ]);

        // Tokens revoked in database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->regularUser->id,
        ]);

        // Subsequent authenticated request is blocked
        $res2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/user');
        $this->assertTrue(in_array($res2->status(), [401, 403]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Point 4: Secure self-service attendance (valid punch sequence & timestamps)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_duplicate_clock_in_is_rejected_and_out_without_in_is_rejected(): void
    {
        Sanctum::actingAs($this->regularUser);

        // First Clock In succeeds
        $in1 = $this->postJson('/api/hr/time-clock/clock-in');
        $in1->assertStatus(201);

        // Second Clock In without OUT is rejected with 422
        $in2 = $this->postJson('/api/hr/time-clock/clock-in');
        $in2->assertStatus(422);

        // Clock Out succeeds
        $out1 = $this->postJson('/api/hr/time-clock/clock-out');
        $out1->assertStatus(201);

        // Second Clock Out without IN is rejected with 422
        $out2 = $this->postJson('/api/hr/time-clock/clock-out');
        $out2->assertStatus(422);
    }

    public function test_normal_employee_cannot_forge_historical_timestamp(): void
    {
        Sanctum::actingAs($this->regularUser);

        // Regular employee attempts to supply historical timestamp
        $historicalTime = '2026-05-01 07:00:00';
        $res = $this->postJson('/api/hr/time-clock/clock-in', [
            'event_time' => $historicalTime,
        ]);

        $res->assertStatus(201);

        // Server forces current timestamp (not historical May 1)
        $event = TimeClockEvent::where('employee_id', $this->employee->id)->latest('id')->first();
        $this->assertNotEquals('2026-05-01', $event->event_time->toDateString());
        $this->assertEquals(now()->toDateString(), $event->event_time->toDateString());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Point 5: Overnight attendance handling across midnight
    // ─────────────────────────────────────────────────────────────────────────

    public function test_overnight_shift_clock_out_resolves_active_session_across_midnight(): void
    {
        $overnightShift = Shift::create([
            'name' => 'Night Shift',
            'code' => 'NIGHT-01',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'is_overnight' => true,
        ]);

        $yesterday = Carbon::parse('2026-10-10');
        $today = Carbon::parse('2026-10-11');

        Schedule::create([
            'employee_id' => $this->employee->id,
            'shift_id' => $overnightShift->id,
            'date' => $yesterday->toDateString(),
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'is_overnight' => true,
        ]);

        // Clock in at 22:00 yesterday evening
        TimeClockEvent::create([
            'employee_id' => $this->employee->id,
            'event_type' => 'in',
            'event_time' => $yesterday->copy()->setTime(22, 0, 0),
            'source' => 'web',
        ]);

        Sanctum::actingAs($this->superAdmin);

        // Morning punch at 06:05 today
        $morningPunch = $today->copy()->setTime(6, 5, 0);
        $service = app(\App\Modules\Hr\Services\AttendanceCalculationService::class);

        // Shared service resolves next event as 'out'
        $nextEvent = $service->resolveNextEventType($this->employee->id, $morningPunch);
        $this->assertEquals('out', $nextEvent);

        // Clock out using the scan toggle
        $response = $this->postJson('/api/hr/time-clock/scan', [
            'badge' => $this->employee->employee_number,
            'event_time' => $morningPunch->toDateTimeString(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('action', 'out');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Point 6: Sanitize role-list errors (no stack trace or internal SQL)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_role_index_sanitizes_errors(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Normal request returns 200 without exposing traces
        $response = $this->getJson('/api/admin/roles');
        $response->assertOk();
        $this->assertArrayNotHasKey('trace', $response->json());
        $this->assertArrayNotHasKey('file', $response->json());
        $this->assertArrayNotHasKey('line', $response->json());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Point 7: Harden public registration requests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_public_registration_normalizes_and_prevents_duplicate_enumeration(): void
    {
        $payload = [
            'first_name' => '  Jane  ',
            'last_name' => '  Doe  ',
            'email' => '  JANE.DOE@WAVEPLUS.LOCAL  ',
            'company_name' => 'Acme Corp',
            'phone' => '1234567890',
            'notes' => 'Registration test',
        ];

        $res1 = $this->postJson('/api/public/registration-requests', $payload);
        $res1->assertStatus(201);

        // Duplicate submission returns same safe message without leaking enumeration
        $res2 = $this->postJson('/api/public/registration-requests', $payload);
        $res2->assertStatus(201);
        $res2->assertJsonPath('message', 'Your registration request has been submitted successfully. A Wave Plus administrator will review your information.');

        // Only 1 registration request was created (no duplicates)
        $this->assertEquals(1, \App\Models\RegistrationRequest::where('email', 'jane.doe@waveplus.local')->count());

        // Normalized email stored in lowercase and trimmed
        $this->assertDatabaseHas('registration_requests', [
            'email' => 'jane.doe@waveplus.local',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Point 10: Stateful session authentication, CSRF, and logout
    // ─────────────────────────────────────────────────────────────────────────

    public function test_stateful_session_login_and_logout_revocation(): void
    {
        $response = $this->withSession([])
            ->postJson('/api/auth/login', [
                'email' => 'john.regular@waveplus.local',
                'password' => 'password123',
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'userData' => ['id', 'email', 'role', 'roles', 'permissions'],
            'permissions',
            'roles',
        ]);

        $this->assertAuthenticatedAs($this->regularUser, 'web');

        // Logout invalidates session
        $logout = $this->withSession([])
            ->actingAs($this->regularUser, 'web')
            ->postJson('/api/auth/logout');
        $logout->assertOk();

        // Following logout, unauthenticated requests are rejected
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/auth/user')->assertStatus(401);
    }
}
