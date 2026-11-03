<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Communications\Email\Jobs\SendEmailMessageJob;
use App\Modules\Communications\Email\Models\EmailMessage;
use Database\Seeders\EmailSenderProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OutgoingEmailAndSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailSenderProfileSeeder::class);

        Role::findOrCreate('Super Admin', 'web');
        Permission::findOrCreate('create-email-messages', 'web');
        Permission::findOrCreate('read-Communications', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    public function test_scheduled_email_is_stored_in_scheduled_folder_and_delayed(): void
    {
        Sanctum::actingAs($this->admin);
        Queue::fake();

        $future = now()->addDays(2)->format('Y-m-d H:i:s');

        $res = $this->postJson('/api/communications/email/messages', [
            'to' => [['email' => 'future@partner.com', 'name' => 'Partner']],
            'subject' => 'Scheduled Follow-Up',
            'body_html' => '<p>Checking in on our previous offer.</p>',
            'scheduled_at' => $future,
            'send_immediately' => true,
        ]);

        $res->assertCreated();

        $message = EmailMessage::where('subject', 'Scheduled Follow-Up')->first();
        $this->assertNotNull($message);
        $this->assertSame('scheduled', $message->folder);
        $this->assertSame('queued', $message->status);
        $this->assertNotNull($message->message_id);

        Queue::assertPushed(SendEmailMessageJob::class, function (SendEmailMessageJob $job) {
            return $job->delay > 0;
        });
    }

    public function test_batch_action_updates_read_starred_and_trash_status(): void
    {
        Sanctum::actingAs($this->admin);

        $msg1 = EmailMessage::create([
            'subject' => 'Batch Test 1',
            'body_html' => '<p>1</p>',
            'status' => 'draft',
            'is_read' => false,
            'is_starred' => false,
            'is_deleted' => false,
        ]);

        $msg2 = EmailMessage::create([
            'subject' => 'Batch Test 2',
            'body_html' => '<p>2</p>',
            'status' => 'draft',
            'is_read' => false,
            'is_starred' => false,
            'is_deleted' => false,
        ]);

        // 1. Mark as read
        $this->postJson('/api/communications/email/messages/batch', [
            'ids' => [$msg1->id, $msg2->id],
            'data' => ['isRead' => true],
        ])->assertOk();

        $this->assertTrue($msg1->fresh()->is_read);
        $this->assertTrue($msg2->fresh()->is_read);

        // 2. Star
        $this->postJson('/api/communications/email/messages/batch', [
            'ids' => [$msg1->id],
            'data' => ['isStarred' => true],
        ])->assertOk();

        $this->assertTrue($msg1->fresh()->is_starred);
        $this->assertFalse($msg2->fresh()->is_starred);

        // 3. Move to trash
        $this->postJson('/api/communications/email/messages/batch', [
            'ids' => [$msg1->id, $msg2->id],
            'data' => ['isDeleted' => true, 'folder' => 'trash'],
        ])->assertOk();

        $this->assertTrue($msg1->fresh()->is_deleted);
        $this->assertSame('trash', $msg1->fresh()->folder);
    }
}
