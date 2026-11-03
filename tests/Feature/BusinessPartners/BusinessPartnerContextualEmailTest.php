<?php

declare(strict_types=1);

namespace Tests\Feature\BusinessPartners;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\BpContact;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\BusinessPartners\Models\Organisation;
use App\Modules\BusinessPartners\Models\ServiceProvider;
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
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class BusinessPartnerContextualEmailTest extends TestCase
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

        // Mock DocumentRenderService so PDF bytes are produced reliably in testing environment
        $docRender = Mockery::mock(DocumentRenderService::class)->makePartial();
        $docRender->shouldReceive('renderPdf')->andReturn('%PDF-1.4 simulated-bp-pdf-content');
        $this->app->instance(DocumentRenderService::class, $docRender);
    }

    public function test_client_contextual_email_preparation_and_sending(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'client_id' => 'CLI-0042',
            'legal_name' => ['fr' => 'Polytechnic Industries SARL'],
            'primary_email' => 'contact@polytechnic.tn',
            'primary_phone' => '+216 71 000 111',
            'status' => 'active',
        ]);

        $contact = BpContact::create([
            'contactable_type' => Client::class,
            'contactable_id' => $client->id,
            'first_name' => ['fr' => 'Mohamed'],
            'last_name' => ['fr' => 'Ben Ali'],
            'position' => ['fr' => 'Directeur Général'],
            'email' => 'mohamed@polytechnic.tn',
        ]);

        // 1. Prepare contextual email
        $prep = $this->getJson("/api/bp/clients/{$client->id}/contextual-email?language=fr")
            ->assertOk();

        $data = $prep->json('data');
        $this->assertSame('CLI-0042', $data['partner']['code']);
        $this->assertNotEmpty($data['sender_profiles']);
        $this->assertCount(2, $data['recipients_list']); // main + contact
        $this->assertStringContainsString('Polytechnic Industries', $data['subject']);
        $this->assertStringNotContainsString('{{', $data['subject']);
        $this->assertStringNotContainsString('{{', $data['body_html']);
        $this->assertSame('client-CLI-0042.pdf', $data['pdf_attachment']['filename']);

        // 2. Send contextual email
        $payload = [
            'sender_profile_id' => $data['default_sender_profile_id'],
            'to' => [
                [
                    'email' => 'mohamed@polytechnic.tn',
                    'name' => 'Mohamed Ben Ali',
                    'recipient_type' => 'client',
                    'recipient_id' => (string) $client->id,
                    'contact_id' => (string) $contact->id,
                    'email_source' => 'contact',
                ],
            ],
            'subject' => $data['subject'],
            'body_html' => $data['body_html'],
            'include_pdf' => true,
        ];

        $response = $this->postJson("/api/bp/clients/{$client->id}/send-email", $payload)
            ->assertCreated();

        $messageId = $response->json('data.id');
        $message = EmailMessage::findOrFail($messageId);

        $this->assertContains($message->status, [EmailMessage::STATUS_QUEUED, EmailMessage::STATUS_SENT]);
        $this->assertSame(EmailMessage::FOLDER_SENT, $message->folder);
        $this->assertCount(1, $message->attachments);
        $this->assertSame('client-CLI-0042.pdf', $message->attachments->first()->filename);

        // Verify database associations
        $this->assertDatabaseHas('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => Client::class,
            'associable_id' => $client->id,
        ]);

        $this->assertDatabaseHas('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => BpContact::class,
            'associable_id' => $contact->id,
        ]);

        // 3. Process sending job and verify Mailpit/Mailable delivery
        (new SendEmailMessageJob($messageId))->handle();

        $message->refresh();
        $this->assertSame(EmailMessage::STATUS_SENT, $message->status);
        $this->assertNotNull($message->sent_at);

        Mail::assertSent(WpEmailMailable::class, function (WpEmailMailable $mailable) {
            return $mailable->hasTo('mohamed@polytechnic.tn');
        });
    }

    public function test_supplier_contextual_email_preparation_and_sending(): void
    {
        Sanctum::actingAs($this->admin);

        $supplier = Supplier::create([
            'supplier_id' => 'SUP-0010',
            'legal_name' => ['fr' => 'Global Logistics SA'],
            'primary_email' => 'sales@globallogistics.com',
            'status' => 'active',
        ]);

        $contact = BpContact::create([
            'contactable_type' => Supplier::class,
            'contactable_id' => $supplier->id,
            'first_name' => ['fr' => 'Jean'],
            'last_name' => ['fr' => 'Dupont'],
            'email' => 'jean@globallogistics.com',
        ]);

        // 1. Prepare
        $prep = $this->getJson("/api/bp/suppliers/{$supplier->id}/contextual-email?language=fr")
            ->assertOk();

        $data = $prep->json('data');
        $this->assertSame('SUP-0010', $data['partner']['code']);
        $this->assertSame('supplier-SUP-0010.pdf', $data['pdf_attachment']['filename']);
        $this->assertStringNotContainsString('{{', $data['body_html']);

        // 2. Send
        $payload = [
            'sender_profile_id' => $data['default_sender_profile_id'],
            'to' => [
                [
                    'email' => 'jean@globallogistics.com',
                    'name' => 'Jean Dupont',
                    'recipient_type' => 'supplier',
                    'recipient_id' => (string) $supplier->id,
                    'contact_id' => (string) $contact->id,
                    'email_source' => 'contact',
                ],
            ],
            'subject' => $data['subject'],
            'body_html' => $data['body_html'],
            'include_pdf' => true,
        ];

        $response = $this->postJson("/api/bp/suppliers/{$supplier->id}/send-email", $payload)
            ->assertCreated();

        $messageId = $response->json('data.id');

        $this->assertDatabaseHas('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => Supplier::class,
            'associable_id' => $supplier->id,
        ]);

        $this->assertDatabaseHas('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => BpContact::class,
            'associable_id' => $contact->id,
        ]);

        (new SendEmailMessageJob($messageId))->handle();

        Mail::assertSent(WpEmailMailable::class, function (WpEmailMailable $mailable) {
            return $mailable->hasTo('jean@globallogistics.com');
        });
    }

    public function test_organisation_contextual_email_preparation_and_sending(): void
    {
        Sanctum::actingAs($this->admin);

        $organisation = Organisation::create([
            'organisation_id' => 'ORG-0005',
            'organisation_type' => 'government_agency',
            'scope' => 'national',
            'legal_name' => ['fr' => 'Chambre de Commerce et d\'Industrie'],
            'primary_email' => 'direction@cci.tn',
            'status' => 'active',
        ]);

        // 1. Prepare
        $prep = $this->getJson("/api/bp/organisations/{$organisation->id}/contextual-email?language=fr")
            ->assertOk();

        $data = $prep->json('data');
        $this->assertSame('ORG-0005', $data['partner']['code']);
        $this->assertSame('organisation-ORG-0005.pdf', $data['pdf_attachment']['filename']);
        $this->assertStringNotContainsString('{{', $data['body_html']);

        // 2. Send
        $payload = [
            'sender_profile_id' => $data['default_sender_profile_id'],
            'to' => [
                [
                    'email' => 'direction@cci.tn',
                    'name' => 'CCI Direction',
                    'recipient_type' => 'organisation',
                    'recipient_id' => (string) $organisation->id,
                ],
            ],
            'subject' => $data['subject'],
            'body_html' => $data['body_html'],
            'include_pdf' => true,
        ];

        $response = $this->postJson("/api/bp/organisations/{$organisation->id}/send-email", $payload)
            ->assertCreated();

        $messageId = $response->json('data.id');

        $this->assertDatabaseHas('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => Organisation::class,
            'associable_id' => $organisation->id,
        ]);

        (new SendEmailMessageJob($messageId))->handle();

        Mail::assertSent(WpEmailMailable::class, function (WpEmailMailable $mailable) {
            return $mailable->hasTo('direction@cci.tn');
        });
    }

    public function test_service_provider_contextual_email_preparation_and_sending(): void
    {
        Sanctum::actingAs($this->admin);

        $provider = ServiceProvider::create([
            'service_provider_id' => 'PRV-0008',
            'legal_name' => ['fr' => 'Securitas Guarding SARL'],
            'primary_email' => 'operations@securitas.tn',
            'status' => 'active',
        ]);

        // 1. Prepare
        $prep = $this->getJson("/api/bp/service-providers/{$provider->id}/contextual-email?language=fr")
            ->assertOk();

        $data = $prep->json('data');
        $this->assertSame('PRV-0008', $data['partner']['code']);
        $this->assertSame('service-provider-PRV-0008.pdf', $data['pdf_attachment']['filename']);
        $this->assertStringNotContainsString('{{', $data['body_html']);

        // 2. Send
        $payload = [
            'sender_profile_id' => $data['default_sender_profile_id'],
            'to' => [
                [
                    'email' => 'operations@securitas.tn',
                    'name' => 'Securitas Operations',
                    'recipient_type' => 'service_provider',
                    'recipient_id' => (string) $provider->id,
                ],
            ],
            'subject' => $data['subject'],
            'body_html' => $data['body_html'],
            'include_pdf' => true,
        ];

        $response = $this->postJson("/api/bp/service-providers/{$provider->id}/send-email", $payload)
            ->assertCreated();

        $messageId = $response->json('data.id');

        $this->assertDatabaseHas('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => ServiceProvider::class,
            'associable_id' => $provider->id,
        ]);

        (new SendEmailMessageJob($messageId))->handle();

        Mail::assertSent(WpEmailMailable::class, function (WpEmailMailable $mailable) {
            return $mailable->hasTo('operations@securitas.tn');
        });
    }

    public function test_manual_email_recipient_does_not_create_contact_association(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'client_id' => 'CLI-0099',
            'legal_name' => ['en' => 'Acme Test Corp'],
            'primary_email' => 'info@acmetest.com',
            'status' => 'active',
        ]);

        $payload = [
            'to' => [
                [
                    'email' => 'external-consultant@external.com',
                    'name' => 'External Consultant',
                    'recipient_type' => 'other',
                ],
            ],
            'subject' => 'Manual update',
            'body_html' => '<p>Manual message</p>',
            'include_pdf' => false,
        ];

        $response = $this->postJson("/api/bp/clients/{$client->id}/send-email", $payload)
            ->assertCreated();

        $messageId = $response->json('data.id');

        // Client record is associated (originating partner)
        $this->assertDatabaseHas('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => Client::class,
            'associable_id' => $client->id,
        ]);

        // But NO contact or 'other' association is artificially created
        $this->assertDatabaseMissing('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => BpContact::class,
        ]);
        $this->assertDatabaseMissing('email_associations', [
            'email_message_id' => $messageId,
            'associable_type' => 'other',
        ]);
    }

    public function test_unresolved_placeholders_are_rejected(): void
    {
        Sanctum::actingAs($this->admin);

        $client = Client::create([
            'client_id' => 'CLI-0002',
            'legal_name' => ['en' => 'Test SARL'],
            'primary_email' => 'client@test.com',
            'status' => 'active',
        ]);

        $payload = [
            'to' => [['email' => 'client@test.com']],
            'subject' => 'Message with unresolved {{ missing.variable }}',
            'body_html' => '<p>Content</p>',
        ];

        $this->postJson("/api/bp/clients/{$client->id}/send-email", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body_html']);
    }

    public function test_unauthorized_user_cannot_send_contextual_email(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);

        $client = Client::create([
            'client_id' => 'CLI-0003',
            'legal_name' => ['en' => 'Protected SARL'],
            'primary_email' => 'prot@test.com',
            'status' => 'active',
        ]);

        $this->getJson("/api/bp/clients/{$client->id}/contextual-email")
            ->assertForbidden();

        $this->postJson("/api/bp/clients/{$client->id}/send-email", [
            'to' => [['email' => 'prot@test.com']],
            'subject' => 'Hello',
            'body_html' => '<p>Hi</p>',
        ])->assertForbidden();
    }
}
