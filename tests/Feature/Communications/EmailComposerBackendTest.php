<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\BpContact;
use App\Modules\BusinessPartners\Models\BpDocument;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Communications\Email\Jobs\SendEmailMessageJob;
use App\Modules\Communications\Email\Models\EmailAttachment;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailRecordLink;
use App\Modules\Hr\Models\Employee;
use Database\Seeders\EmailSenderProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailComposerBackendTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $composerOnly;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(EmailSenderProfileSeeder::class);

        Role::findOrCreate('Super Admin', 'web');
        foreach (['read-Communications', 'create-email-messages', 'read-CrmSales'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        // Can compose from sales@ but has NO Business Partner permission
        $this->composerOnly = User::factory()->create();
        $this->composerOnly->givePermissionTo(['read-Communications', 'create-email-messages', 'read-CrmSales']);
    }

    private function basePayload(array $extra = []): array
    {
        return array_merge([
            'to' => [['email' => 'client@example.com', 'name' => 'Client']],
            'subject' => 'Composer test',
            'body_html' => '<p>Hello</p>',
        ], $extra);
    }

    private function makePartnerDocument(string $name = 'contract.pdf'): BpDocument
    {
        $client = Client::create([
            'legal_name' => ['en' => 'Docs Client SARL'],
            'primary_email' => 'docs@client.com',
            'status' => 'active',
        ]);

        $document = BpDocument::create([
            'documentable_type' => Client::class,
            'documentable_id' => $client->id,
            'type' => 'contract',
            'title' => ['en' => 'Master Contract'],
            'status' => 'active',
            'file_name' => $name,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 51200,
        ]);

        $document->addMedia(UploadedFile::fake()->create($name, 50, 'application/pdf'))
            ->toMediaCollection('document');

        return $document;
    }

    public function test_store_accepts_uploaded_attachments_and_never_exposes_storage_details(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->post('/api/communications/email/messages', $this->basePayload([
            'attachments' => [UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf')],
        ]), ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(1, 'data.attachments')
            ->assertJsonPath('data.attachments.0.filename', 'brief.pdf');

        $this->assertArrayNotHasKey('storage_path', $response->json('data.attachments.0'));
        $this->assertArrayNotHasKey('content_b64', $response->json('data.attachments.0'));

        $attachment = EmailAttachment::first();
        Storage::disk('local')->assertExists($attachment->storage_path);
        $this->assertSame('upload', $attachment->source);
    }

    public function test_store_rejects_disallowed_attachment_types(): void
    {
        Sanctum::actingAs($this->admin);

        $this->post('/api/communications/email/messages', $this->basePayload([
            'attachments' => [UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload')],
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachments.0']);

        $this->assertSame(0, EmailMessage::count());
    }

    public function test_store_rejects_more_than_ten_attachments(): void
    {
        Sanctum::actingAs($this->admin);

        $files = [];
        for ($i = 0; $i < 11; $i++) {
            $files[] = UploadedFile::fake()->create("f{$i}.pdf", 5, 'application/pdf');
        }

        $this->post('/api/communications/email/messages', $this->basePayload(['attachments' => $files]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attachments']);
    }

    public function test_update_replaces_recipients_and_removes_unkept_attachments(): void
    {
        Sanctum::actingAs($this->admin);

        $created = $this->post('/api/communications/email/messages', $this->basePayload([
            'attachments' => [
                UploadedFile::fake()->create('keep.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('drop.pdf', 10, 'application/pdf'),
            ],
        ]), ['Accept' => 'application/json'])->assertCreated();

        $id = $created->json('data.id');
        $keep = collect($created->json('data.attachments'))->firstWhere('filename', 'keep.pdf');
        $dropPath = EmailAttachment::where('filename', 'drop.pdf')->value('storage_path');

        $this->post("/api/communications/email/messages/{$id}", [
            '_method' => 'PUT',
            'to' => [['email' => 'new@example.com']],
            'subject' => 'Updated subject',
            'body_html' => '<p>Updated</p>',
            'sync_attachments' => 1,
            'keep_attachment_ids' => [$keep['id']],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Updated subject')
            ->assertJsonCount(1, 'data.attachments');

        $this->assertDatabaseHas('email_recipients', ['email_message_id' => $id, 'email' => 'new@example.com']);
        $this->assertDatabaseMissing('email_recipients', ['email_message_id' => $id, 'email' => 'client@example.com']);
        $this->assertDatabaseMissing('email_attachments', ['filename' => 'drop.pdf']);
        Storage::disk('local')->assertMissing($dropPath);
    }

    public function test_sent_messages_cannot_be_edited(): void
    {
        Sanctum::actingAs($this->admin);

        $message = EmailMessage::create([
            'subject' => 'Already sent',
            'body_html' => '<p>x</p>',
            'status' => EmailMessage::STATUS_SENT,
        ]);

        $this->putJson("/api/communications/email/messages/{$message->id}", ['subject' => 'Hacked'])
            ->assertUnprocessable();

        $this->assertSame('Already sent', $message->fresh()->subject);
    }

    public function test_send_immediately_with_attachments_queues_after_persisting(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin);

        $this->post('/api/communications/email/messages', $this->basePayload([
            'send_immediately' => 1,
            'attachments' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonCount(1, 'data.attachments');

        Queue::assertPushed(SendEmailMessageJob::class);
    }

    public function test_existing_document_search_returns_metadata_for_authorized_user(): void
    {
        Sanctum::actingAs($this->admin);
        $document = $this->makePartnerDocument();

        $response = $this->getJson('/api/communications/email/documents/search?q=Master');

        $response->assertOk()->assertJsonPath('meta.total', 1);

        $item = $response->json('data.0');
        $this->assertSame((string) $document->id, $item['id']);
        $this->assertSame('Master Contract', $item['name']);
        $this->assertSame('Docs Client SARL', $item['entity_name']);
        $this->assertSame('client', $item['entity_type']);
        $this->assertSame('business_partners', $item['module']);
        $this->assertSame('contract', $item['category']);
        $this->assertSame('pdf', $item['extension']);
        $this->assertArrayNotHasKey('storage_path', $item);
        $this->assertArrayNotHasKey('file_path', $item);
    }

    public function test_existing_document_search_hides_documents_from_users_without_partner_access(): void
    {
        $this->makePartnerDocument();
        Sanctum::actingAs($this->composerOnly);

        $this->getJson('/api/communications/email/documents/search?q=Master')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_user_cannot_search_documents_without_email_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/communications/email/documents/search?q=x')->assertForbidden();
    }

    public function test_attaching_existing_document_copies_file_privately(): void
    {
        Sanctum::actingAs($this->admin);
        $document = $this->makePartnerDocument('terms.pdf');

        $response = $this->postJson('/api/communications/email/messages', $this->basePayload([
            'document_attachments' => [['source' => 'bp_document', 'id' => (string) $document->id]],
        ]))->assertCreated()->assertJsonCount(1, 'data.attachments');

        $this->assertSame('document', $response->json('data.attachments.0.source'));
        $this->assertSame((string) $document->id, $response->json('data.attachments.0.source_id'));

        $attachment = EmailAttachment::first();
        Storage::disk('local')->assertExists($attachment->storage_path);
        $this->assertNotSame($document->getFirstMedia('document')->getPath(), $attachment->storage_path);
    }

    public function test_cannot_attach_document_without_authorization(): void
    {
        $document = $this->makePartnerDocument();
        Sanctum::actingAs($this->composerOnly);

        $this->postJson('/api/communications/email/messages', $this->basePayload([
            'document_attachments' => [['source' => 'bp_document', 'id' => (string) $document->id]],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['document_attachments']);

        $this->assertSame(0, EmailAttachment::count());
    }

    public function test_cannot_attach_unknown_document_source(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/communications/email/messages', $this->basePayload([
            'document_attachments' => [['source' => 'filesystem', 'id' => '../../.env']],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['document_attachments.0.source']);
    }

    public function test_show_never_exposes_attachment_storage_details(): void
    {
        Sanctum::actingAs($this->admin);

        $created = $this->post('/api/communications/email/messages', $this->basePayload([
            'attachments' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
        ]), ['Accept' => 'application/json'])->assertCreated();

        $show = $this->getJson('/api/communications/email/messages/'.$created->json('data.id'))->assertOk();

        $this->assertArrayNotHasKey('storage_path', $show->json('data.attachments.0'));
        $this->assertArrayNotHasKey('storage_disk', $show->json('data.attachments.0'));
    }

    public function test_deleting_a_draft_removes_its_stored_files(): void
    {
        Sanctum::actingAs($this->admin);

        $created = $this->post('/api/communications/email/messages', $this->basePayload([
            'attachments' => [UploadedFile::fake()->create('gone.pdf', 10, 'application/pdf')],
        ]), ['Accept' => 'application/json'])->assertCreated();

        $path = EmailAttachment::first()->storage_path;

        $this->deleteJson('/api/communications/email/messages/'.$created->json('data.id'))->assertOk();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_cannot_send_a_message_without_recipients(): void
    {
        Sanctum::actingAs($this->admin);

        $message = EmailMessage::create(['subject' => 'No one', 'body_html' => '<p>x</p>', 'status' => EmailMessage::STATUS_DRAFT]);

        $this->postJson("/api/communications/email/messages/{$message->id}/send")
            ->assertUnprocessable();
    }

    public function test_composer_automatically_associates_business_partner_contact_and_employee_recipients_without_document_links(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'legal_name' => ['en' => 'Acme Corp'],
            'primary_email' => 'info@acmewave.com',
            'status' => 'active',
        ]);

        $contact = BpContact::create([
            'contactable_type' => Client::class,
            'contactable_id' => $client->id,
            'first_name' => ['en' => 'Mohamed'],
            'last_name' => ['en' => 'Ben Ali'],
            'email' => 'mohamed@acmewave.com',
        ]);

        $employee = Employee::create([
            'first_name' => ['en' => 'Ahmed'],
            'last_name' => ['en' => 'Employee'],
            'work_email' => 'ahmed@waveplus.xyz',
            'hire_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $attachedDoc = $this->makePartnerDocument('spec.pdf');

        $payload = [
            'to' => [
                [
                    'email' => 'mohamed@acmewave.com',
                    'name' => 'Mohamed Ben Ali (Acme Corp)',
                    'recipient_type' => 'client',
                    'recipient_id' => $client->id,
                    'contact_id' => $contact->id,
                    'email_source' => 'contact',
                ],
                [
                    'email' => 'ahmed@waveplus.xyz',
                    'name' => 'Ahmed Employee',
                    'recipient_type' => 'employee',
                    'recipient_id' => $employee->id,
                    'email_source' => 'employee',
                ],
            ],
            'cc' => [
                [
                    'email' => 'external@vendor.net',
                    'name' => 'Manual Recipient',
                    'recipient_type' => 'other',
                ],
            ],
            'subject' => 'Meeting update',
            'body_html' => '<p>Please find attached specs</p>',
            'document_attachments' => [
                [
                    'source' => 'bp_document',
                    'id' => (string) $attachedDoc->id,
                ],
            ],
        ];

        $response = $this->postJson('/api/communications/email/messages', $payload)
            ->assertCreated();

        $messageId = $response->json('data.id');
        $message = EmailMessage::findOrFail($messageId);

        // Verify attachment is present physically
        $this->assertCount(1, $message->attachments);
        $this->assertSame('spec.pdf', $message->attachments->first()->filename);

        // Verify automatic associations in email_associations (EmailRecordLink)
        // 1. Client must be associated
        $this->assertDatabaseHas('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => Client::class,
            'associable_id' => $client->id,
        ]);

        // 2. BpContact must be associated
        $this->assertDatabaseHas('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => BpContact::class,
            'associable_id' => $contact->id,
        ]);

        // 3. Employee must be associated
        $this->assertDatabaseHas('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => Employee::class,
            'associable_id' => $employee->id,
        ]);

        // 4. Must NOT have any association for attached BpDocument
        $this->assertDatabaseMissing('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => BpDocument::class,
        ]);

        // 5. Must NOT have any association for manual external recipient
        $this->assertDatabaseMissing('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => 'other',
        ]);
    }
}
