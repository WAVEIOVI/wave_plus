<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use App\Modules\Communications\Email\Services\SenderProfileService;
use Database\Seeders\EmailSenderProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SenderProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $salesUser;

    private User $hrUser;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed sender profiles
        $this->seed(EmailSenderProfileSeeder::class);

        // Setup roles & permissions
        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Admin', 'web');
        Role::findOrCreate('Responsable', 'web');

        Permission::findOrCreate('read-Communications', 'web');
        Permission::findOrCreate('create-email-messages', 'web');
        Permission::findOrCreate('read-CrmSales', 'web');
        Permission::findOrCreate('create-crm-quotations', 'web');
        Permission::findOrCreate('read-PeopleSkills', 'web');
        Permission::findOrCreate('read-FinancialCenter', 'web');
        Permission::findOrCreate('read-SupplyChainProcurement', 'web');

        // 1. Super Admin
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        // 2. Sales User
        $this->salesUser = User::factory()->create();
        $this->salesUser->givePermissionTo(['read-Communications', 'create-email-messages', 'read-CrmSales', 'create-crm-quotations']);

        // 3. HR User
        $this->hrUser = User::factory()->create();
        $this->hrUser->givePermissionTo(['read-Communications', 'create-email-messages', 'read-PeopleSkills']);

        // 4. Unauthorized User (no permissions)
        $this->unauthorizedUser = User::factory()->create();
    }

    public function test_predefined_sender_identities_are_seeded_with_expected_metadata(): void
    {
        $expectedEmails = [
            'system@waveplus.xyz',
            'sales@waveplus.xyz',
            'purchasing@waveplus.xyz',
            'hr@waveplus.xyz',
            'finance@waveplus.xyz',
            'direction@waveplus.xyz',
        ];

        foreach ($expectedEmails as $email) {
            $this->assertDatabaseHas('email_sender_profiles', [
                'email' => $email,
                'is_active' => true,
            ]);
        }

        $systemProfile = EmailSenderProfile::where('email', 'system@waveplus.xyz')->first();
        $this->assertNotNull($systemProfile);
        $this->assertTrue($systemProfile->is_system);
        $this->assertEquals('System', $systemProfile->department);

        $directionProfile = EmailSenderProfile::where('email', 'direction@waveplus.xyz')->first();
        $this->assertNotNull($directionProfile);
        $this->assertTrue($directionProfile->is_default);
        $this->assertFalse($directionProfile->is_system);
    }

    public function test_system_sender_profile_can_never_be_used_by_any_user_for_manual_sending(): void
    {
        $systemProfile = EmailSenderProfile::where('email', 'system@waveplus.xyz')->first();
        $service = app(SenderProfileService::class);

        // Regular users cannot use system@ via policy
        $this->assertFalse(Gate::forUser($this->salesUser)->allows('use', $systemProfile));
        $this->assertFalse(Gate::forUser($this->hrUser)->allows('use', $systemProfile));

        // System profile is never returned in authorized profiles list even for Super Admin
        $adminProfiles = $service->getAuthorizedProfiles($this->admin);
        $this->assertFalse($adminProfiles->contains('email', 'system@waveplus.xyz'));

        // Attempting to resolve system profile throws ValidationException
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->resolveAuthorizedProfile($this->admin, $systemProfile->id);
    }

    public function test_sales_user_is_authorized_for_sales_profile_only(): void
    {
        $salesProfile = EmailSenderProfile::where('email', 'sales@waveplus.xyz')->first();
        $hrProfile = EmailSenderProfile::where('email', 'hr@waveplus.xyz')->first();
        $financeProfile = EmailSenderProfile::where('email', 'finance@waveplus.xyz')->first();

        $this->assertTrue(Gate::forUser($this->salesUser)->allows('use', $salesProfile));
        $this->assertFalse(Gate::forUser($this->salesUser)->allows('use', $hrProfile));
        $this->assertFalse(Gate::forUser($this->salesUser)->allows('use', $financeProfile));
    }

    public function test_hr_user_is_authorized_for_hr_profile_only(): void
    {
        $salesProfile = EmailSenderProfile::where('email', 'sales@waveplus.xyz')->first();
        $hrProfile = EmailSenderProfile::where('email', 'hr@waveplus.xyz')->first();

        $this->assertTrue(Gate::forUser($this->hrUser)->allows('use', $hrProfile));
        $this->assertFalse(Gate::forUser($this->hrUser)->allows('use', $salesProfile));
    }

    public function test_admin_is_authorized_for_all_interactive_profiles(): void
    {
        $interactiveProfiles = EmailSenderProfile::where('is_system', false)->get();

        foreach ($interactiveProfiles as $profile) {
            $this->assertTrue(
                Gate::forUser($this->admin)->allows('use', $profile),
                "Admin should be authorized to use profile: {$profile->email}"
            );
        }
    }

    public function test_sender_profiles_endpoint_returns_only_authorized_interactive_profiles(): void
    {
        Sanctum::actingAs($this->salesUser);

        $response = $this->getJson('/api/communications/email/sender-profiles');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'department']]]);

        $emails = collect($response->json('data'))->pluck('email')->all();

        // Must include sales
        $this->assertContains('sales@waveplus.xyz', $emails);

        // Must strictly exclude system@waveplus.xyz
        $this->assertNotContains('system@waveplus.xyz', $emails);

        // Must strictly exclude hr@waveplus.xyz and finance@waveplus.xyz for salesUser
        $this->assertNotContains('hr@waveplus.xyz', $emails);
        $this->assertNotContains('finance@waveplus.xyz', $emails);
    }

    public function test_store_email_message_rejects_unauthorized_sender_profile(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->salesUser);

        $hrProfile = EmailSenderProfile::where('email', 'hr@waveplus.xyz')->first();

        // Sales user attempts to send as HR
        $response = $this->postJson('/api/communications/email/messages', [
            'to' => [['email' => 'client@example.com']],
            'subject' => 'Impersonation Attempt',
            'body_html' => '<p>Hello</p>',
            'sender_profile_id' => $hrProfile->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sender_profile_id']);
    }

    public function test_store_email_message_rejects_system_sender_profile(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $systemProfile = EmailSenderProfile::where('email', 'system@waveplus.xyz')->first();

        // Even admin cannot manually select system@
        $response = $this->postJson('/api/communications/email/messages', [
            'to' => [['email' => 'client@example.com']],
            'subject' => 'System Impersonation',
            'body_html' => '<p>System</p>',
            'sender_profile_id' => $systemProfile->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sender_profile_id']);
    }

    public function test_store_email_message_resolves_default_authorized_profile_when_omitted(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->salesUser);

        $response = $this->postJson('/api/communications/email/messages', [
            'to' => [['email' => 'client@example.com']],
            'subject' => 'Auto Sender Resolution',
            'body_html' => '<p>Content</p>',
        ]);

        $response->assertCreated();

        $messageId = $response->json('data.id');
        $this->assertDatabaseHas('email_messages', [
            'id' => $messageId,
            'sender_profile_id' => EmailSenderProfile::where('email', 'sales@waveplus.xyz')->value('id'),
        ]);
    }
}
