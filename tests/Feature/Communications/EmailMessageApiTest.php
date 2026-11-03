<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Role;
use App\Models\User;
use App\Modules\Communications\Email\Models\EmailMessage;
use Database\Seeders\EmailSenderProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailMessageApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $unauthorized;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Admin', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->seed(EmailSenderProfileSeeder::class);

        $this->unauthorized = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_access_email_messages(): void
    {
        $this->getJson('/api/communications/email/messages')
            ->assertUnauthorized();
    }

    public function test_admin_can_list_email_messages(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/communications/email/messages')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_can_create_draft_email(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/communications/email/messages', [
            'to' => [['email' => 'recipient@example.com', 'name' => 'Recipient']],
            'subject' => 'Test Subject',
            'body_html' => '<p>Hello</p>',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_admin_can_create_and_queue_email(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/communications/email/messages', [
            'to' => [['email' => 'recipient@example.com']],
            'subject' => 'Queued Test',
            'body_html' => '<p>Hello</p>',
            'send_immediately' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'queued');
    }

    public function test_unauthorized_user_gets_403_on_create(): void
    {
        Sanctum::actingAs($this->unauthorized);

        $this->postJson('/api/communications/email/messages', [
            'to' => [['email' => 'x@x.com']],
            'subject' => 'Test',
            'body_html' => '<p>x</p>',
        ])->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/communications/email/messages', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to', 'subject', 'body_html']);
    }

    public function test_store_validates_email_format(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/communications/email/messages', [
            'to' => [['email' => 'not-an-email']],
            'subject' => 'Test',
            'body_html' => '<p>Hello</p>',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['to.0.email']);
    }

    public function test_can_send_a_draft_message(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $message = EmailMessage::create([
            'subject' => 'Draft to Send',
            'body_html' => '<p>test</p>',
            'status' => EmailMessage::STATUS_DRAFT,
        ]);

        $message->recipients()->create([
            'type' => 'to',
            'email' => 'someone@example.com',
        ]);

        $this->postJson("/api/communications/email/messages/{$message->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'queued');
    }

    public function test_cannot_delete_a_sent_message_without_permission(): void
    {
        Sanctum::actingAs($this->admin);

        $message = EmailMessage::create([
            'subject' => 'Sent',
            'body_html' => '<p>sent</p>',
            'status' => EmailMessage::STATUS_SENT,
        ]);

        // Unauthorized user (no role) should be forbidden
        Sanctum::actingAs($this->unauthorized);
        $this->deleteJson("/api/communications/email/messages/{$message->id}")
            ->assertForbidden();
    }
}
