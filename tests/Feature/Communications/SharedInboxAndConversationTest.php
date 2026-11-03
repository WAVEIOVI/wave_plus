<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Models\EmailMailbox;
use Database\Seeders\EmailSenderProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SharedInboxAndConversationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $salesUser;

    private User $hrUser;

    private User $unauthorizedUser;

    private EmailMailbox $salesMailbox;

    private EmailMailbox $hrMailbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailSenderProfileSeeder::class);

        Role::findOrCreate('Super Admin', 'web');
        foreach (['read-Communications', 'read-CrmSales', 'read-Hr', 'edit-email-messages', 'manage-email-mailboxes'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->salesUser = User::factory()->create();
        $this->salesUser->givePermissionTo(['read-Communications', 'read-CrmSales']);

        $this->hrUser = User::factory()->create();
        $this->hrUser->givePermissionTo(['read-Communications', 'read-Hr']);

        $this->unauthorizedUser = User::factory()->create();

        $this->salesMailbox = EmailMailbox::create([
            'name' => 'Sales Inbox',
            'email' => 'sales@waveplus.xyz',
            'department' => 'sales',
            'is_active' => true,
        ]);

        $this->hrMailbox = EmailMailbox::create([
            'name' => 'HR Inbox',
            'email' => 'hr@waveplus.xyz',
            'department' => 'hr',
            'is_active' => true,
        ]);
    }

    public function test_department_based_access_to_shared_inboxes(): void
    {
        // 1. Sales user only sees Sales mailbox
        Sanctum::actingAs($this->salesUser);
        $res = $this->getJson('/api/communications/email/mailboxes');
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('sales@waveplus.xyz', $res->json('data.0.email'));

        // 2. HR user only sees HR mailbox
        Sanctum::actingAs($this->hrUser);
        $res = $this->getJson('/api/communications/email/mailboxes');
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('hr@waveplus.xyz', $res->json('data.0.email'));

        // 3. Admin sees both
        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/communications/email/mailboxes');
        $res->assertOk();
        $this->assertCount(2, $res->json('data'));

        // 4. Unauthorized user denied access
        Sanctum::actingAs($this->unauthorizedUser);
        $this->getJson('/api/communications/email/mailboxes')->assertForbidden();
    }

    public function test_conversation_assignment_to_employee(): void
    {
        Sanctum::actingAs($this->salesUser);

        $conversation = EmailConversation::create([
            'mailbox_id' => $this->salesMailbox->id,
            'department' => 'sales',
            'subject' => 'New Enterprise Lead',
            'status' => 'open',
        ]);

        $agent = User::factory()->create();
        $agent->givePermissionTo(['read-Communications', 'read-CrmSales']);

        $res = $this->postJson("/api/communications/email/conversations/{$conversation->id}/assign", [
            'assigned_to' => $agent->id,
        ]);

        $res->assertOk();
        $conversation->refresh();
        $this->assertSame($agent->id, $conversation->assigned_to);
        $this->assertSame($this->salesUser->id, $conversation->assigned_by);
        $this->assertNotNull($conversation->assigned_at);

        // Assigning to an unauthorized user (e.g. unauthorizedUser) must be rejected
        $unauthRes = $this->postJson("/api/communications/email/conversations/{$conversation->id}/assign", [
            'assigned_to' => $this->unauthorizedUser->id,
        ]);
        $unauthRes->assertStatus(422);
    }

    public function test_conversation_status_transitions(): void
    {
        Sanctum::actingAs($this->salesUser);

        $conversation = EmailConversation::create([
            'mailbox_id' => $this->salesMailbox->id,
            'department' => 'sales',
            'subject' => 'Quote Negotiation',
            'status' => 'open',
        ]);

        // Transition to pending
        $this->postJson("/api/communications/email/conversations/{$conversation->id}/status", [
            'status' => 'pending',
        ])->assertOk();
        $this->assertSame('pending', $conversation->fresh()->status);

        // Transition to resolved
        $this->postJson("/api/communications/email/conversations/{$conversation->id}/status", [
            'status' => 'resolved',
        ])->assertOk();
        $this->assertSame('resolved', $conversation->fresh()->status);

        // Invalid status rejected
        $this->postJson("/api/communications/email/conversations/{$conversation->id}/status", [
            'status' => 'invalid_status_value',
        ])->assertUnprocessable();
    }

    public function test_internal_notes_on_conversation(): void
    {
        Sanctum::actingAs($this->salesUser);

        $conversation = EmailConversation::create([
            'mailbox_id' => $this->salesMailbox->id,
            'department' => 'sales',
            'subject' => 'Pricing Inquiry',
            'status' => 'open',
        ]);

        $res = $this->postJson("/api/communications/email/conversations/{$conversation->id}/notes", [
            'content' => 'Client requested a 5% discount if they pay upfront via bank transfer.',
        ]);

        $res->assertCreated();
        $this->assertCount(1, $conversation->notes);
        $this->assertSame($this->salesUser->id, $conversation->notes->first()->user_id);
        $this->assertSame('Client requested a 5% discount if they pay upfront via bank transfer.', $conversation->notes->first()->content);
    }

    public function test_user_cannot_access_unauthorized_department_conversation(): void
    {
        $hrConversation = EmailConversation::create([
            'mailbox_id' => $this->hrMailbox->id,
            'department' => 'hr',
            'subject' => 'Confidential Salary Review',
            'status' => 'open',
        ]);

        // Sales user has NO HR permissions
        Sanctum::actingAs($this->salesUser);
        $this->getJson("/api/communications/email/conversations/{$hrConversation->id}")
            ->assertForbidden();

        // HR user CAN access it
        Sanctum::actingAs($this->hrUser);
        $this->getJson("/api/communications/email/conversations/{$hrConversation->id}")
            ->assertOk();
    }
}
