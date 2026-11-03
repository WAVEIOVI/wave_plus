<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Communications\Email\Models\EmailAttachment;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Models\EmailMailbox;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Services\EmailSanitizationService;
use Database\Seeders\EmailSenderProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailSecurityAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $salesUser;

    private User $hrUser;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailSenderProfileSeeder::class);

        Role::findOrCreate('Super Admin', 'web');
        foreach (['read-Communications', 'create-email-messages', 'read-CrmSales', 'read-Hr', 'read-Finance'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->salesUser = User::factory()->create();
        $this->salesUser->givePermissionTo(['read-Communications', 'create-email-messages', 'read-CrmSales']);

        $this->hrUser = User::factory()->create();
        $this->hrUser->givePermissionTo(['read-Communications', 'create-email-messages', 'read-Hr']);

        $this->unauthorizedUser = User::factory()->create();
    }

    public function test_email_sanitization_service_strips_scripts_and_malicious_attributes(): void
    {
        $sanitizer = app(EmailSanitizationService::class);

        $malicious = '<p>Hello <script>alert("xss")</script><a href="javascript:alert(1)" onclick="steal()">Click me</a></p>';
        $cleaned = $sanitizer->sanitize($malicious);

        $this->assertStringNotContainsString('<script>', $cleaned);
        $this->assertStringNotContainsString('alert', $cleaned);
        $this->assertStringNotContainsString('onclick', $cleaned);
        $this->assertStringNotContainsString('javascript:', $cleaned);
        $this->assertStringContainsString('<p>Hello <a>Click me</a></p>', $cleaned);
    }

    public function test_email_sanitization_strips_dangerous_css_expressions(): void
    {
        $sanitizer = app(EmailSanitizationService::class);

        $htmlWithBadCss = '<div style="background: url(javascript:alert(1)); expression(alert(2)); color: red;">Test</div>';
        $cleaned = $sanitizer->sanitize($htmlWithBadCss);

        $this->assertStringNotContainsString('javascript:', $cleaned);
        $this->assertStringNotContainsString('expression', $cleaned);
        $this->assertStringContainsString('color: red;', $cleaned);
    }

    public function test_attachment_download_is_protected_and_respects_access_control(): void
    {
        Storage::fake('local');

        $hrMailbox = EmailMailbox::create([
            'name' => 'HR Private Mailbox',
            'email' => 'hr@waveplus.xyz',
            'department' => 'hr',
            'is_active' => true,
        ]);

        $conversation = EmailConversation::create([
            'mailbox_id' => $hrMailbox->id,
            'department' => 'hr',
            'subject' => 'Confidential Salary Review',
            'status' => 'open',
        ]);

        $message = EmailMessage::create([
            'conversation_id' => $conversation->id,
            'mailbox_id' => $hrMailbox->id,
            'direction' => 'incoming',
            'folder' => 'inbox',
            'subject' => 'Confidential Salary Review',
            'body_html' => '<p>Confidential salary data.</p>',
            'status' => 'sent',
        ]);

        $filePath = 'communications/email/attachments/'.$message->id.'/secret_salary.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 mock salary data');

        $attachment = EmailAttachment::create([
            'email_message_id' => $message->id,
            'filename' => 'secret_salary.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 28,
            'storage_disk' => 'local',
            'storage_path' => $filePath,
        ]);

        // 1. Unauthenticated request rejected
        $this->getJson("/api/communications/email/attachments/{$attachment->id}/download")
            ->assertStatus(401);

        // 2. Sales user (not authorized for HR) gets 403
        Sanctum::actingAs($this->salesUser);
        $this->getJson("/api/communications/email/attachments/{$attachment->id}/download")
            ->assertStatus(403);

        // 3. HR user can download successfully
        Sanctum::actingAs($this->hrUser);
        $res = $this->get("/api/communications/email/attachments/{$attachment->id}/download");
        $res->assertStatus(200);
        $res->assertHeader('content-type', 'application/pdf');
    }

    public function test_messages_index_applies_centralized_department_isolation(): void
    {
        $salesMailbox = EmailMailbox::create([
            'name' => 'Sales Inbox',
            'email' => 'sales@waveplus.xyz',
            'department' => 'sales',
            'is_active' => true,
        ]);

        $hrMailbox = EmailMailbox::create([
            'name' => 'HR Inbox',
            'email' => 'hr@waveplus.xyz',
            'department' => 'hr',
            'is_active' => true,
        ]);

        // Create a sales message
        EmailMessage::create([
            'mailbox_id' => $salesMailbox->id,
            'direction' => 'incoming',
            'folder' => 'inbox',
            'subject' => 'Sales Lead Request',
            'body_html' => '<p>New sales inquiry.</p>',
            'status' => 'sent',
            'is_read' => false,
            'is_deleted' => false,
        ]);

        // Create a sensitive HR message
        EmailMessage::create([
            'mailbox_id' => $hrMailbox->id,
            'direction' => 'incoming',
            'folder' => 'inbox',
            'subject' => 'Confidential Employee Grievance',
            'body_html' => '<p>Private grievance details.</p>',
            'status' => 'sent',
            'is_read' => false,
            'is_deleted' => false,
        ]);

        // Sales user should see Sales lead but NEVER HR grievance
        Sanctum::actingAs($this->salesUser);
        $res = $this->getJson('/api/communications/email/messages?filter=inbox');
        $res->assertStatus(200);

        $subjects = collect($res->json('emails'))->pluck('subject')->all();
        $this->assertContains('Sales Lead Request', $subjects);
        $this->assertNotContains('Confidential Employee Grievance', $subjects);

        // HR user should see HR grievance but not Sales lead
        Sanctum::actingAs($this->hrUser);
        $hrRes = $this->getJson('/api/communications/email/messages?filter=inbox');
        $hrRes->assertStatus(200);

        $hrSubjects = collect($hrRes->json('emails'))->pluck('subject')->all();
        $this->assertContains('Confidential Employee Grievance', $hrSubjects);
        $this->assertNotContains('Sales Lead Request', $hrSubjects);
    }
}
