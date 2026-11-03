<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\BpAddress;
use App\Modules\BusinessPartners\Models\BpContact;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecipientSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $bpUser;

    private User $restrictedUser;

    private User $unauthenticated;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Admin', 'web');

        Permission::findOrCreate('read-Communications', 'web');
        Permission::findOrCreate('create-email-messages', 'web');
        Permission::findOrCreate('read-BusinessPartners', 'web');
        Permission::findOrCreate('read-clients', 'web');
        Permission::findOrCreate('read-suppliers', 'web');
        Permission::findOrCreate('read-PeopleSkills', 'web');

        // Admin has all permissions
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        // BP user only has Communications and BusinessPartners access
        $this->bpUser = User::factory()->create();
        $this->bpUser->givePermissionTo(['read-Communications', 'create-email-messages', 'read-BusinessPartners', 'read-clients', 'read-suppliers']);

        // Restricted user has only Communications access (no BP, no HR)
        $this->restrictedUser = User::factory()->create();
        $this->restrictedUser->givePermissionTo(['read-Communications', 'create-email-messages']);
    }

    public function test_unauthenticated_user_cannot_search_recipients(): void
    {
        $this->getJson('/api/communications/email/recipients/search?q=test')
            ->assertUnauthorized();
    }

    public function test_unauthorized_user_gets_403(): void
    {
        $user = User::factory()->create(); // no roles or permissions
        Sanctum::actingAs($user);

        $this->getJson('/api/communications/email/recipients/search?q=test')
            ->assertForbidden();
    }

    public function test_search_returns_empty_when_query_less_than_two_characters(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/communications/email/recipients/search?q=a');

        $response->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_search_finds_business_partner_general_email(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'legal_name' => ['en' => 'Acme Technologies Ltd'],
            'commercial_name' => ['en' => 'Acme Tech'],
            'primary_email' => 'contact@acmetech.com',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/communications/email/recipients/search?q=acmetech');

        $response->assertOk()
            ->assertJsonPath('data.0.email', 'contact@acmetech.com')
            ->assertJsonPath('data.0.entity_type', 'client')
            ->assertJsonPath('data.0.source', 'general')
            ->assertJsonPath('data.0.entity_id', (string) $client->id);
    }

    public function test_search_finds_contact_email_with_contact_name_metadata(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'legal_name' => ['en' => 'Stark Industries'],
            'primary_email' => 'info@stark.com',
            'status' => 'active',
        ]);

        $contact = BpContact::create([
            'contactable_type' => Client::class,
            'contactable_id' => $client->id,
            'first_name' => ['en' => 'Pepper'],
            'last_name' => ['en' => 'Potts'],
            'job_title' => 'Chief Operating Officer',
            'email' => 'pepper@stark.com',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/communications/email/recipients/search?q=pepper');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $item = collect($data)->firstWhere('email', 'pepper@stark.com');
        $this->assertNotNull($item);
        $this->assertEquals('client', $item['entity_type']);
        $this->assertEquals('contact', $item['source']);
        $this->assertEquals('Pepper Potts', $item['contact_name']);
        $this->assertEquals('Chief Operating Officer', $item['job_title']);
        $this->assertEquals((string) $contact->id, $item['contact_id']);
        $this->assertEquals((string) $client->id, $item['entity_id']);
    }

    public function test_search_finds_address_email_with_label_metadata(): void
    {
        Sanctum::actingAs($this->admin);

        $supplier = Supplier::create([
            'legal_name' => ['en' => 'Wayne Enterprises'],
            'primary_email' => 'general@wayne.com',
            'status' => 'active',
        ]);

        BpAddress::create([
            'addressable_type' => Supplier::class,
            'addressable_id' => $supplier->id,
            'label' => ['en' => 'Gotham Branch'],
            'address_street' => ['en' => '100 Wayne Tower'],
            'email' => 'gotham@wayne.com',
            'country' => 'US',
        ]);

        $response = $this->getJson('/api/communications/email/recipients/search?q=gotham');

        $response->assertOk();
        $item = collect($response->json('data'))->firstWhere('email', 'gotham@wayne.com');
        $this->assertNotNull($item);
        $this->assertEquals('supplier', $item['entity_type']);
        $this->assertEquals('address', $item['source']);
        $this->assertEquals('Gotham Branch', $item['contact_name']);
        $this->assertEquals((string) $supplier->id, $item['entity_id']);
    }

    public function test_search_preserves_multiple_owners_sharing_same_email(): void
    {
        Sanctum::actingAs($this->admin);

        $sharedEmail = 'shared-procurement@umbrella.com';

        $client = Client::create([
            'legal_name' => ['en' => 'Umbrella Retail'],
            'primary_email' => $sharedEmail,
            'status' => 'active',
        ]);

        $supplier = Supplier::create([
            'legal_name' => ['en' => 'Umbrella Logistics'],
            'primary_email' => $sharedEmail,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/communications/email/recipients/search?q=umbrella');

        $response->assertOk();
        $data = collect($response->json('data'))->where('email', $sharedEmail)->values();

        // Both owners must be preserved!
        $this->assertCount(2, $data);

        $entityTypes = $data->pluck('entity_type')->all();
        $this->assertContains('client', $entityTypes);
        $this->assertContains('supplier', $entityTypes);
    }

    public function test_search_finds_employees_when_user_has_hr_permission(): void
    {
        Sanctum::actingAs($this->admin);

        Employee::create([
            'first_name' => ['en' => 'Sarah'],
            'last_name' => ['en' => 'Connor'],
            'work_email' => 's.connor@waveplus.xyz',
            'hire_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/communications/email/recipients/search?q=connor');

        $response->assertOk();
        $item = collect($response->json('data'))->firstWhere('email', 's.connor@waveplus.xyz');
        $this->assertNotNull($item);
        $this->assertEquals('employee', $item['entity_type']);
        $this->assertEquals('employee', $item['source']);
        $this->assertEquals('Sarah Connor', $item['entity_display_name']);
    }

    public function test_search_does_not_expose_employees_when_user_lacks_hr_permission(): void
    {
        // $bpUser has BusinessPartners permission but NOT PeopleSkills/HR
        Sanctum::actingAs($this->bpUser);

        Employee::create([
            'first_name' => ['en' => 'John'],
            'last_name' => ['en' => 'Doe'],
            'work_email' => 'johndoe.secret@waveplus.xyz',
            'hire_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/communications/email/recipients/search?q=johndoe');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_search_supports_manual_valid_email_candidate(): void
    {
        Sanctum::actingAs($this->restrictedUser);

        $customEmail = 'external.partner@customdomain.io';

        $response = $this->getJson("/api/communications/email/recipients/search?q={$customEmail}");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals($customEmail, $data[0]['email']);
        $this->assertEquals('custom', $data[0]['entity_type']);
        $this->assertEquals('manual', $data[0]['source']);
    }

    public function test_bp_legacy_email_search_endpoint_returns_enriched_metadata(): void
    {
        Sanctum::actingAs($this->bpUser);

        $client = Client::create([
            'legal_name' => ['en' => 'Legacy Partner SA'],
            'primary_email' => 'legacy@partnersa.com',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/bp/emails/search?q=partnersa');

        $response->assertOk();
        $data = $response->json();

        $this->assertNotEmpty($data);
        $item = collect($data)->firstWhere('email', 'legacy@partnersa.com');
        $this->assertNotNull($item);
        // Has both legacy keys and new enriched keys
        $this->assertEquals('legacy@partnersa.com', $item['email']);
        $this->assertEquals('Client', $item['type']);
        $this->assertEquals('client', $item['entity_type']);
        $this->assertEquals('general', $item['source']);
    }

    public function test_departments_endpoint_returns_active_departments_for_authorized_user(): void
    {
        Sanctum::actingAs($this->admin);

        $dept = Department::create([
            'code' => 'ENG',
            'name' => ['en' => 'Engineering', 'fr' => 'Ingénierie'],
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/communications/email/recipients/departments');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $found = collect($data)->firstWhere('id', (string) $dept->id);
        $this->assertNotNull($found);
        $this->assertEquals('ENG', $found['code']);
    }

    public function test_search_grouped_returns_business_partner_with_nested_contacts_and_addresses(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'legal_name' => ['en' => 'Apex Corporation'],
            'commercial_name' => ['en' => 'Apex'],
            'primary_email' => 'contact@apex.com',
            'status' => 'active',
        ]);

        $contact = BpContact::create([
            'contactable_type' => Client::class,
            'contactable_id' => $client->id,
            'first_name' => ['en' => 'Arthur'],
            'last_name' => ['en' => 'Dent'],
            'email' => 'arthur@apex.com',
            'job_title' => 'Logistics Coordinator',
            'status' => 'active',
        ]);

        $address = BpAddress::create([
            'addressable_type' => Client::class,
            'addressable_id' => $client->id,
            'label' => ['en' => 'Central Depot'],
            'address_street' => ['en' => '123 Apex Way'],
            'email' => 'depot@apex.com',
            'country' => 'FR',
        ]);

        $response = $this->getJson('/api/communications/email/recipients/search?grouped=1&types=client&q=apex');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $bp = collect($data)->firstWhere('id', (string) $client->id);
        $this->assertNotNull($bp);
        $this->assertEquals('client', $bp['entity_type']);
        $this->assertEquals('contact@apex.com', $bp['primary_email']);

        // Contacts must be inside the respective Business Partner
        $this->assertCount(1, $bp['contacts']);
        $this->assertEquals('arthur@apex.com', $bp['contacts'][0]['email']);
        $this->assertEquals((string) $contact->id, $bp['contacts'][0]['id']);

        // Addresses must be inside the respective Business Partner
        $this->assertCount(1, $bp['addresses']);
        $this->assertEquals('depot@apex.com', $bp['addresses'][0]['email']);
        $this->assertEquals((string) $address->id, $bp['addresses'][0]['id']);
    }

    public function test_search_grouped_filters_employees_by_department(): void
    {
        Sanctum::actingAs($this->admin);

        $deptIt = Department::create([
            'code' => 'IT',
            'name' => ['en' => 'Information Technology'],
            'status' => 'active',
        ]);

        $deptHr = Department::create([
            'code' => 'HR',
            'name' => ['en' => 'Human Resources'],
            'status' => 'active',
        ]);

        $pos = Position::create([
            'department_id' => $deptIt->id,
            'code' => 'DEV',
            'title' => ['en' => 'Senior Developer'],
            'status' => 'active',
        ]);

        $empIt = Employee::create([
            'first_name' => ['en' => 'Linus'],
            'last_name' => ['en' => 'Torvalds'],
            'work_email' => 'linus@waveplus.xyz',
            'department_id' => $deptIt->id,
            'position_id' => $pos->id,
            'hire_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $empHr = Employee::create([
            'first_name' => ['en' => 'Jane'],
            'last_name' => ['en' => 'Goodall'],
            'work_email' => 'jane@waveplus.xyz',
            'department_id' => $deptHr->id,
            'hire_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        // Filter by IT department only
        $response = $this->getJson("/api/communications/email/recipients/search?grouped=1&types=employee&departments={$deptIt->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $emails = collect($data)->pluck('work_email')->all();
        $this->assertContains('linus@waveplus.xyz', $emails);
        $this->assertNotContains('jane@waveplus.xyz', $emails);

        $empItem = collect($data)->firstWhere('work_email', 'linus@waveplus.xyz');
        $this->assertEquals('Senior Developer', $empItem['position']);
    }

    public function test_draft_persistence_preserves_contact_id_and_all_recipient_relationships(): void
    {
        Sanctum::actingAs($this->admin);

        $sender = EmailSenderProfile::create([
            'name' => 'WAVE Operations',
            'email' => 'ops@waveplus.xyz',
            'department' => 'Operations',
            'is_default' => true,
            'is_active' => true,
            'is_system' => false,
        ]);

        $payload = [
            'sender_profile_id' => $sender->id,
            'subject' => 'Delivery Confirmation Notice',
            'body_html' => '<p>Your order is ready.</p>',
            'to' => [
                [
                    'email' => 'arthur.contact@apex.com',
                    'name' => 'Arthur Dent',
                    'recipient_type' => 'client',
                    'recipient_id' => 'client-uuid-1234',
                    'contact_id' => 'contact-uuid-5678',
                    'email_source' => 'contact',
                ],
            ],
            'cc' => [
                [
                    'email' => 'depot.address@apex.com',
                    'name' => 'Central Depot',
                    'recipient_type' => 'client',
                    'recipient_id' => 'client-uuid-1234',
                    'contact_id' => 'address-uuid-9999',
                    'email_source' => 'address',
                ],
            ],
            'bcc' => [
                [
                    'email' => 'linus@waveplus.xyz',
                    'name' => 'Linus Torvalds',
                    'recipient_type' => 'employee',
                    'recipient_id' => 'emp-uuid-1111',
                    'contact_id' => null,
                    'email_source' => 'employee',
                ],
            ],
        ];

        $createRes = $this->postJson('/api/communications/email/messages', $payload);
        $createRes->assertCreated();

        $messageId = $createRes->json('data.id');

        // Fetch draft detail via show endpoint
        $showRes = $this->getJson("/api/communications/email/messages/{$messageId}");
        $showRes->assertOk();

        $toItem = $showRes->json('email.to.0');
        $this->assertEquals('arthur.contact@apex.com', $toItem['email']);
        $this->assertEquals('client', $toItem['recipient_type']);
        $this->assertEquals('client-uuid-1234', $toItem['recipient_id']);
        $this->assertEquals('contact-uuid-5678', $toItem['contact_id']);
        $this->assertEquals('contact', $toItem['email_source']);

        $ccItem = $showRes->json('email.cc.0');
        $this->assertEquals('depot.address@apex.com', $ccItem['email']);
        $this->assertEquals('address-uuid-9999', $ccItem['contact_id']);
        $this->assertEquals('address', $ccItem['email_source']);

        $bccItem = $showRes->json('email.bcc.0');
        $this->assertEquals('linus@waveplus.xyz', $bccItem['email']);
        $this->assertEquals('employee', $bccItem['recipient_type']);
        $this->assertEquals('employee', $bccItem['email_source']);
    }
}
