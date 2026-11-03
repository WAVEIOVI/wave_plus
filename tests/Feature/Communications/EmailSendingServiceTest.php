<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Role;
use App\Models\User;
use App\Modules\Communications\Email\Jobs\SendEmailMessageJob;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use App\Modules\Communications\Email\Services\EmailSendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailSendingServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private EmailSendingService $service;

    private EmailSenderProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Role::findOrCreate('Super Admin', 'web');
        $this->user->assignRole('Super Admin');
        Sanctum::actingAs($this->user);

        $this->service = app(EmailSendingService::class);

        $this->profile = EmailSenderProfile::create([
            'name' => 'Test Sender',
            'email' => 'test@wave-plus.local',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    // ── createDraft ────────────────────────────────────────────────────

    public function test_create_draft_persists_message_and_recipients(): void
    {
        $message = $this->service->createDraft([
            'to' => [['email' => 'bob@example.com', 'name' => 'Bob']],
            'cc' => [['email' => 'cc@example.com']],
            'subject' => 'Hello World',
            'body_html' => '<p>Hello</p>',
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(EmailMessage::class, $message);
        $this->assertEquals(EmailMessage::STATUS_DRAFT, $message->status);
        $this->assertEquals('Hello World', $message->subject);
        $this->assertDatabaseHas('email_messages', ['id' => $message->id, 'status' => 'draft']);
        $this->assertDatabaseHas('email_recipients', ['email_message_id' => $message->id, 'email' => 'bob@example.com', 'type' => 'to']);
        $this->assertDatabaseHas('email_recipients', ['email_message_id' => $message->id, 'email' => 'cc@example.com', 'type' => 'cc']);
    }

    // ── createAndQueue ─────────────────────────────────────────────────

    public function test_create_and_queue_dispatches_job(): void
    {
        Queue::fake();

        $message = $this->service->createAndQueue([
            'to' => [['email' => 'bob@example.com']],
            'subject' => 'Queued Mail',
            'body_html' => '<p>Queued</p>',
        ]);

        $this->assertEquals(EmailMessage::STATUS_QUEUED, $message->status);
        $this->assertNotNull($message->idempotency_key);
        Queue::assertPushed(SendEmailMessageJob::class, fn ($job) => true);
    }

    // ── idempotency ────────────────────────────────────────────────────

    public function test_sent_message_is_skipped_on_duplicate_job_dispatch(): void
    {
        Mail::fake();

        $message = $this->service->createDraft([
            'to' => [['email' => 'alice@example.com']],
            'subject' => 'Idempotency Test',
            'body_html' => '<p>Idempotent</p>',
        ]);

        $message->update([
            'status' => EmailMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        // Running the job on an already-sent message should not send again
        $job = new SendEmailMessageJob($message->id);
        $job->handle();

        Mail::assertNothingSent();
    }

    // ── retry ──────────────────────────────────────────────────────────

    public function test_retry_queues_failed_message(): void
    {
        Queue::fake();

        $message = $this->service->createDraft([
            'to' => [['email' => 'fail@example.com']],
            'subject' => 'Retry Test',
            'body_html' => '<p>Retry</p>',
        ]);

        $message->update([
            'status' => EmailMessage::STATUS_FAILED,
            'attempts' => 1,
            'max_attempts' => 3,
        ]);

        $this->service->retry($message);

        $this->assertEquals(EmailMessage::STATUS_QUEUED, $message->fresh()->status);
        Queue::assertPushed(SendEmailMessageJob::class);
    }

    public function test_retry_throws_when_max_attempts_reached(): void
    {
        $message = $this->service->createDraft([
            'to' => [['email' => 'fail@example.com']],
            'subject' => 'Max Retries Test',
            'body_html' => '<p>Max</p>',
        ]);

        $message->update([
            'status' => EmailMessage::STATUS_FAILED,
            'attempts' => 3,
            'max_attempts' => 3,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->retry($message);
    }

    // ── job actually sends ─────────────────────────────────────────────

    public function test_job_sends_email_and_updates_status(): void
    {
        Mail::fake();

        $message = $this->service->createDraft([
            'to' => [['email' => 'bob@example.com', 'name' => 'Bob']],
            'subject' => 'Job Test',
            'body_html' => '<p>Content</p>',
            'sender_profile_id' => $this->profile->id,
        ]);

        $message->update([
            'status' => EmailMessage::STATUS_QUEUED,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $job = new SendEmailMessageJob($message->id);
        $job->handle();

        $message->refresh();
        $this->assertEquals(EmailMessage::STATUS_SENT, $message->status);
        $this->assertNotNull($message->sent_at);
        Mail::assertSent(\App\Modules\Communications\Email\Mail\WpEmailMailable::class);
    }
}
