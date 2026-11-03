<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessPartners;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Jobs\ProcessBpBulkEmailItemJob;
use App\Modules\BusinessPartners\Models\BpBulkEmailBatch;
use App\Modules\BusinessPartners\Models\BpBulkEmailBatchItem;
use App\Modules\BusinessPartners\Models\BpContact;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Supplier;
use App\Modules\Communications\Email\Jobs\SendEmailMessageJob;
use App\Modules\Communications\Email\Mail\WpEmailMailable;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use App\Modules\Shared\Services\DocumentRenderService;
use Database\Seeders\EmailSenderProfileSeeder;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class BusinessPartnerBulkEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();

        $this->seed(EmailSenderProfileSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        Role::findOrCreate('Super Admin', 'web');

        foreach ([
            'read-BusinessPartners',
            'read-clients',
            'read-suppliers',
            'read-organisations',
            'read-service-providers',
            'create-email-messages',
            'read-Communications',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');

        $this->unauthorizedUser = User::factory()->create();
        $this->unauthorizedUser->givePermissionTo(['read-BusinessPartners', 'read-clients']);

        $docRender = Mockery::mock(DocumentRenderService::class)->makePartial();
        $docRender->shouldReceive('renderPdf')->andReturn('%PDF-1.4 simulated-bulk-pdf-content');
        $this->app->instance(DocumentRenderService::class, $docRender);
    }

    public function test_bulk_preview_with_multiple_clients_and_skipped_record(): void
    {
        Sanctum::actingAs($this->admin);

        // 1. Client with valid email
        $c1 = Client::create([
            'client_id' => 'CLI-001',
            'legal_name' => ['fr' => 'Alpha Industries'],
            'primary_email' => 'contact@alpha.tn',
            'status' => 'active',
        ]);

        // 2. Client with no direct email but valid contact
        $c2 = Client::create([
            'client_id' => 'CLI-002',
            'legal_name' => ['fr' => 'Beta Logistics'],
            'primary_email' => null,
            'status' => 'active',
        ]);
        BpContact::create([
            'contactable_type' => Client::class,
            'contactable_id' => $c2->id,
            'first_name' => ['fr' => 'Sami'],
            'last_name' => ['fr' => 'Trabelsi'],
            'email' => 'sami@beta.tn',
            'is_primary' => true,
        ]);

        // 3. Client without ANY email
        $c3 = Client::create([
            'client_id' => 'CLI-003',
            'legal_name' => ['fr' => 'Gamma Zero Email'],
            'primary_email' => null,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/bp/clients/bulk-email/preview', [
            'partner_ids' => [$c1->id, $c2->id, $c3->id],
            'recipient_rule' => 'primary_email_first',
            'language' => 'fr',
        ])->assertOk();

        $data = $response->json('data');

        $this->assertSame(3, $data['total_count']);
        $this->assertSame(2, $data['ready_count']);
        $this->assertSame(1, $data['skipped_count']);

        // Assert items
        $items = collect($data['items'])->keyBy('partner_id');
        $this->assertSame('ready', $items[$c1->id]['status']);
        $this->assertSame('contact@alpha.tn', $items[$c1->id]['recipient_email']);

        $this->assertSame('ready', $items[$c2->id]['status']);
        $this->assertSame('sami@beta.tn', $items[$c2->id]['recipient_email']);
        $this->assertSame('contact', $items[$c2->id]['email_source']);

        $this->assertSame('skipped', $items[$c3->id]['status']);
        $this->assertSame('no_valid_email', $items[$c3->id]['skip_reason']);

        // Sample preview
        $this->assertNotNull($data['sample_preview']);
        $this->assertStringContainsString('Alpha Industries', $data['sample_preview']['subject']);
    }

    public function test_bulk_sending_multiple_clients_dispatches_jobs_and_creates_isolated_emails(): void
    {
        Sanctum::actingAs($this->admin);

        $c1 = Client::create([
            'client_id' => 'CLI-101',
            'legal_name' => ['fr' => 'Atlas Steel'],
            'primary_email' => 'info@atlas.tn',
            'status' => 'active',
        ]);
        $c2 = Client::create([
            'client_id' => 'CLI-102',
            'legal_name' => ['fr' => 'Boreal Tech'],
            'primary_email' => 'support@boreal.tn',
            'status' => 'active',
        ]);
        $c3NoEmail = Client::create([
            'client_id' => 'CLI-103',
            'legal_name' => ['fr' => 'Desert Silent'],
            'primary_email' => null,
            'status' => 'active',
        ]);

        $defaultProfile = EmailSenderProfile::where('is_active', true)->where('is_system', false)->first();

        // Launch bulk batch
        $response = $this->postJson('/api/bp/clients/bulk-email', [
            'partner_ids' => [$c1->id, $c2->id, $c3NoEmail->id],
            'sender_profile_id' => $defaultProfile->id,
            'recipient_rule' => 'primary_email_first',
            'language' => 'fr',
            'include_pdf' => true,
            'idempotency_key' => 'test-idemp-001',
        ])->assertCreated();

        $batchId = $response->json('data.id');
        $batch = BpBulkEmailBatch::with('items')->findOrFail($batchId);

        $this->assertSame(3, $batch->total_count);

        // Process pending item jobs
        foreach ($batch->items->where('status', BpBulkEmailBatchItem::STATUS_PENDING) as $item) {
            (new ProcessBpBulkEmailItemJob($item->id))->handle(
                app(\App\Modules\BusinessPartners\Services\BusinessPartnerContextualEmailService::class)
            );
        }

        $batch->refresh();
        $this->assertSame(2, $batch->sent_count);
        $this->assertSame(1, $batch->skipped_count);
        $this->assertSame(0, $batch->failed_count);
        $this->assertSame(BpBulkEmailBatch::STATUS_COMPLETED, $batch->status);

        // Check messages: each client has their own distinct message & conversation
        $items = $batch->items()->get()->keyBy('partner_id');
        $this->assertSame(BpBulkEmailBatchItem::STATUS_SENT, $items[$c1->id]->status);
        $this->assertSame(BpBulkEmailBatchItem::STATUS_SENT, $items[$c2->id]->status);
        $this->assertSame(BpBulkEmailBatchItem::STATUS_SKIPPED, $items[$c3NoEmail->id]->status);

        $msg1 = EmailMessage::find($items[$c1->id]->email_message_id);
        $msg2 = EmailMessage::find($items[$c2->id]->email_message_id);

        $this->assertNotNull($msg1);
        $this->assertNotNull($msg2);
        $this->assertNotSame($msg1->id, $msg2->id);
        $this->assertNotSame($msg1->conversation_id, $msg2->conversation_id);

        $this->assertStringContainsString('Atlas Steel', $msg1->subject);
        $this->assertStringContainsString('Boreal Tech', $msg2->subject);

        // Check separate PDF attachments
        $this->assertSame('client-CLI-101.pdf', $msg1->attachments->first()->filename);
        $this->assertSame('client-CLI-102.pdf', $msg2->attachments->first()->filename);

        // Check recipients
        $this->assertSame('info@atlas.tn', $msg1->recipients->first()->email);
        $this->assertSame('support@boreal.tn', $msg2->recipients->first()->email);

        // Execute queued mail sending job
        (new SendEmailMessageJob($msg1->id))->handle();
        (new SendEmailMessageJob($msg2->id))->handle();

        Mail::assertSent(WpEmailMailable::class, function (WpEmailMailable $m) {
            return $m->hasTo('info@atlas.tn');
        });
        Mail::assertSent(WpEmailMailable::class, function (WpEmailMailable $m) {
            return $m->hasTo('support@boreal.tn');
        });
    }

    public function test_bulk_sending_multiple_suppliers_with_contact_rule(): void
    {
        Sanctum::actingAs($this->admin);

        $sup1 = Supplier::create([
            'supplier_id' => 'SUP-501',
            'legal_name' => ['fr' => 'Global Metal SA'],
            'primary_email' => 'general@globalmetal.com',
            'status' => 'active',
        ]);
        $c1 = BpContact::create([
            'contactable_type' => Supplier::class,
            'contactable_id' => $sup1->id,
            'first_name' => ['fr' => 'Marc'],
            'last_name' => ['fr' => 'Vidal'],
            'email' => 'marc@globalmetal.com',
            'is_primary' => true,
        ]);

        $sup2 = Supplier::create([
            'supplier_id' => 'SUP-502',
            'legal_name' => ['fr' => 'Delta Pack SARL'],
            'primary_email' => 'sales@deltapack.com',
            'status' => 'active',
        ]);

        // Launch bulk batch with 'primary_contact_first'
        $response = $this->postJson('/api/bp/suppliers/bulk-email', [
            'partner_ids' => [$sup1->id, $sup2->id],
            'recipient_rule' => 'primary_contact_first',
            'language' => 'fr',
        ])->assertCreated();

        $batchId = $response->json('data.id');
        $batch = BpBulkEmailBatch::with('items')->findOrFail($batchId);

        foreach ($batch->items as $item) {
            if ($item->status === BpBulkEmailBatchItem::STATUS_PENDING) {
                (new ProcessBpBulkEmailItemJob($item->id))->handle(
                    app(\App\Modules\BusinessPartners\Services\BusinessPartnerContextualEmailService::class)
                );
            }
        }

        $batch->refresh();
        $this->assertSame(2, $batch->sent_count);

        $items = $batch->items->keyBy('partner_id');
        // SUP-501 used the contact email because rule is primary_contact_first
        $this->assertSame('marc@globalmetal.com', $items[$sup1->id]->resolved_email);
        $this->assertSame((string) $c1->id, $items[$sup1->id]->contact_id);

        // SUP-502 fell back to primary_email because it has no contacts
        $this->assertSame('sales@deltapack.com', $items[$sup2->id]->resolved_email);
    }

    public function test_bulk_email_handles_individual_pdf_generation_failure_gracefully(): void
    {
        Sanctum::actingAs($this->admin);

        $cGood = Client::create([
            'client_id' => 'CLI-G01',
            'legal_name' => ['fr' => 'Good Client SARL'],
            'primary_email' => 'good@client.tn',
            'status' => 'active',
        ]);

        $cFail = Client::create([
            'client_id' => 'CLI-F02',
            'legal_name' => ['fr' => 'Fail Client SARL'],
            'primary_email' => 'fail@client.tn',
            'status' => 'active',
        ]);

        // Mock DocumentRenderService to fail only on cFail
        $docRender = Mockery::mock(DocumentRenderService::class)->makePartial();
        $docRender->shouldReceive('renderPdf')->andReturnUsing(function ($html) {
            if (str_contains($html, 'CLI-F02') || str_contains($html, 'Fail Client')) {
                throw new \RuntimeException('PDF Rendering engine timeout');
            }
            return '%PDF-1.4 good-pdf';
        });
        $this->app->instance(DocumentRenderService::class, $docRender);

        $response = $this->postJson('/api/bp/clients/bulk-email', [
            'partner_ids' => [$cGood->id, $cFail->id],
            'recipient_rule' => 'primary_email_first',
            'language' => 'fr',
        ])->assertCreated();

        $batchId = $response->json('data.id');
        $batch = BpBulkEmailBatch::with('items')->findOrFail($batchId);

        foreach ($batch->items as $item) {
            (new ProcessBpBulkEmailItemJob($item->id))->handle(
                app(\App\Modules\BusinessPartners\Services\BusinessPartnerContextualEmailService::class)
            );
        }

        $batch->refresh();
        $this->assertSame(1, $batch->sent_count);
        $this->assertSame(1, $batch->failed_count);
        $this->assertSame(BpBulkEmailBatch::STATUS_PARTIAL_FAILURE, $batch->status);

        $items = $batch->items->keyBy('partner_id');
        $this->assertSame(BpBulkEmailBatchItem::STATUS_SENT, $items[$cGood->id]->status);
        $this->assertSame(BpBulkEmailBatchItem::STATUS_FAILED, $items[$cFail->id]->status);
        $this->assertStringContainsString('PDF Rendering engine timeout', $items[$cFail->id]->error_message);
    }

    public function test_duplicate_submission_prevention_via_idempotency_key(): void
    {
        Sanctum::actingAs($this->admin);

        $c = Client::create([
            'client_id' => 'CLI-IDEMP',
            'legal_name' => ['fr' => 'Idempotent Client'],
            'primary_email' => 'idem@client.tn',
            'status' => 'active',
        ]);

        $payload = [
            'partner_ids' => [$c->id],
            'recipient_rule' => 'primary_email_first',
            'idempotency_key' => 'unique-req-key-999',
        ];

        $res1 = $this->postJson('/api/bp/clients/bulk-email', $payload)->assertCreated();
        $batchId1 = $res1->json('data.id');

        // Repeat identical request with same idempotency_key
        $res2 = $this->postJson('/api/bp/clients/bulk-email', $payload)->assertCreated();
        $batchId2 = $res2->json('data.id');

        $this->assertSame($batchId1, $batchId2);
        $this->assertSame(1, BpBulkEmailBatch::where('idempotency_key', 'unique-req-key-999')->count());
    }

    public function test_show_batch_and_list_batches_endpoints(): void
    {
        Sanctum::actingAs($this->admin);

        $c = Client::create([
            'client_id' => 'CLI-SHOW',
            'legal_name' => ['fr' => 'Show Client'],
            'primary_email' => 'show@client.tn',
            'status' => 'active',
        ]);

        $res = $this->postJson('/api/bp/clients/bulk-email', [
            'partner_ids' => [$c->id],
        ])->assertCreated();

        $batchId = $res->json('data.id');

        $this->getJson("/api/bp/bulk-email/batches/{$batchId}")
            ->assertOk()
            ->assertJsonPath('data.id', $batchId)
            ->assertJsonPath('data.total_count', 1);

        $this->getJson('/api/bp/bulk-email/batches')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_email_history_available_through_communications_record_link_service(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'client_id' => 'CLI-HIST-01',
            'legal_name' => ['fr' => 'History Check Client'],
            'primary_email' => 'history@client.tn',
            'status' => 'active',
        ]);

        $res = $this->postJson('/api/bp/clients/bulk-email', [
            'partner_ids' => [$client->id],
            'language' => 'fr',
        ])->assertCreated();

        $batchId = $res->json('data.id');
        $batch = BpBulkEmailBatch::with('items')->findOrFail($batchId);
        $item = $batch->items->first();

        (new ProcessBpBulkEmailItemJob($item->id))->handle(
            app(\App\Modules\BusinessPartners\Services\BusinessPartnerContextualEmailService::class)
        );

        // Verify conversation is retrievable via central communications link service
        $linkService = app(\App\Modules\Communications\Email\Services\EmailRecordLinkService::class);
        $paginator = $linkService->getConversationsForRecord('client', $client->id, $this->admin);

        $this->assertSame(1, $paginator->total());
        $conversation = $paginator->first();
        $this->assertNotNull($conversation);
        $this->assertStringContainsString('History Check Client', $conversation->subject);

        // Verify NO document association was created
        $this->assertDatabaseMissing('email_associations', [
            'conversation_id' => $conversation->id,
            'associable_type' => 'bp_document',
        ]);
        $this->assertDatabaseMissing('email_associations', [
            'conversation_id' => $conversation->id,
            'associable_type' => 'document',
        ]);
    }
}
