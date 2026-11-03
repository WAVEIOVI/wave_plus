<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Communications\Email\Mail\WpEmailMailable;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use App\Modules\Communications\Email\Models\EmailTemplate;
use App\Modules\Communications\Email\Services\EmailSignatureService;
use App\Modules\Crm\Enums\QuotationStatus;
use App\Modules\Crm\Models\Quotation;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailTemplateAndCompositionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $salesAgent;

    private User $restrictedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
        $this->seed(\Database\Seeders\EmailSenderProfileSeeder::class);

        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Admin', 'web');

        Permission::findOrCreate('read-Communications', 'web');
        Permission::findOrCreate('create-email-messages', 'web');
        Permission::findOrCreate('read-CrmSales', 'web');
        Permission::findOrCreate('access-CrmSales', 'web');

        $this->admin = User::factory()->create(['name' => 'Admin Boss', 'email' => 'admin@waveplus.xyz']);
        $this->admin->assignRole('Super Admin');

        $this->salesAgent = User::factory()->create(['name' => 'Sonia Commercial', 'email' => 'sonia@waveplus.xyz']);
        $this->salesAgent->givePermissionTo(['read-Communications', 'create-email-messages', 'read-CrmSales', 'access-CrmSales']);

        $this->restrictedUser = User::factory()->create(['name' => 'Restricted Bob', 'email' => 'bob@waveplus.xyz']);
        $this->restrictedUser->givePermissionTo(['read-Communications', 'create-email-messages']);
    }

    public function test_arabic_template_rendering_produces_rtl_layout_and_arabic_content(): void
    {
        Sanctum::actingAs($this->salesAgent);

        $template = EmailTemplate::where('slug', 'quotation_submission_ar')->firstOrFail();

        $response = $this->postJson("/api/communications/email/templates/{$template->id}/render", [
            'recipient' => ['name' => 'شركة النجاح الصناعية'],
            'context' => [
                'document' => [
                    'reference' => 'DEV-2026-AR-001',
                    'date' => '2026-11-01',
                    'due_date' => '2026-11-30',
                    'total' => '12 500,000 TND',
                ],
            ],
            'signature_type' => 'company',
        ]);

        $response->assertOk();
        $data = $response->json('data');

        $this->assertStringContainsString('DEV-2026-AR-001', $data['subject']);
        $this->assertStringContainsString('شركة النجاح الصناعية', $data['body_html']);
        $this->assertStringContainsString('dir="rtl"', $data['body_html']);
        $this->assertStringContainsString('ملخص المستند', $data['body_html']);
        $this->assertStringContainsString('المبلغ الإجمالي', $data['body_html']);
        $this->assertStringContainsString('12 500,000 TND', $data['body_html']);
        $this->assertStringContainsString('wp-signature-company', $data['body_html']);
    }

    public function test_primary_business_context_extracts_real_database_records(): void
    {
        Sanctum::actingAs($this->salesAgent);

        $client = Client::create([
            'client_id' => 'CLI-0077',
            'legal_name' => 'Société Tunisienne de Technologie',
            'commercial_name' => 'Tunisia Tech',
            'tax_id' => '7788990A/P/M/000',
            'primary_email' => 'contact@tunisiatech.tn',
            'primary_phone' => '+216 71 888 999',
            'hq_address' => 'Berges du Lac 2',
            'hq_city' => 'Tunis',
            'status' => 'active',
        ]);

        $quotation = Quotation::create([
            'reference' => 'QUO-2026-TEST-99',
            'client_id' => (string) $client->id,
            'version' => 1,
            'valid_until' => now()->addDays(30),
            'currency' => 'TND',
            'total_ht' => 5000.00,
            'total_tva' => 950.00,
            'total_ttc' => 5950.00,
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Immediate,
            'status' => QuotationStatus::Draft,
        ]);

        $template = EmailTemplate::where('slug', 'quotation_submission_fr')->firstOrFail();

        $response = $this->postJson("/api/communications/email/templates/{$template->id}/render", [
            'record_type' => 'quotation',
            'record_id' => (string) $quotation->id,
            'signature_type' => 'personal',
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $summary = $response->json('summary');
        $resolved = $response->json('resolved_variables');

        $this->assertEquals('quotation', $summary['record_type']);
        $this->assertEquals('QUO-2026-TEST-99', $summary['reference']);
        $this->assertEquals('QUO-2026-TEST-99', $resolved['document.reference']);
        $this->assertStringContainsString('5,950.00 TND', $resolved['document.total']);
        $this->assertStringContainsString('Société Tunisienne de Technologie', $resolved['client.name']);

        $this->assertStringContainsString('QUO-2026-TEST-99', $data['subject']);
        $this->assertStringContainsString('5,950.00 TND', $data['body_html']);
    }

    public function test_unauthorized_user_cannot_access_unauthorized_module_record_context(): void
    {
        Sanctum::actingAs($this->restrictedUser);

        $client = Client::create([
            'client_id' => 'CLI-0099',
            'legal_name' => 'Secret Defense Corp',
            'status' => 'active',
        ]);

        $quotation = Quotation::create([
            'reference' => 'QUO-SECRET-001',
            'client_id' => (string) $client->id,
            'version' => 1,
            'valid_until' => now()->addDays(15),
            'currency' => 'TND',
            'total_ht' => 100000.00,
            'total_tva' => 19000.00,
            'total_ttc' => 119000.00,
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Immediate,
            'status' => QuotationStatus::Draft,
        ]);

        $template = EmailTemplate::where('slug', 'quotation_submission_fr')->firstOrFail();

        // User lacks read-CrmSales permission, must be forbidden
        $response = $this->postJson("/api/communications/email/templates/{$template->id}/render", [
            'record_type' => 'quotation',
            'record_id' => (string) $quotation->id,
        ]);

        $response->assertForbidden();
    }

    public function test_signature_service_prevents_duplicate_signatures(): void
    {
        $sigService = app(EmailSignatureService::class);

        $body = '<p>Initial greeting message.</p>';

        // 1st append
        $step1 = $sigService->appendSignature($body, null, 'personal', $this->salesAgent);
        $this->assertEquals(1, substr_count($step1['body_html'], 'wp-signature-personal'));

        // 2nd append with company signature
        $step2 = $sigService->appendSignature($step1['body_html'], $step1['body_text'], 'company', $this->salesAgent);
        $this->assertEquals(0, substr_count($step2['body_html'], 'wp-signature-personal'));
        $this->assertEquals(1, substr_count($step2['body_html'], 'wp-signature-company'));

        // 3rd append with 'none' strips signature completely
        $step3 = $sigService->appendSignature($step2['body_html'], $step2['body_text'], 'none', $this->salesAgent);
        $this->assertEquals(0, substr_count($step3['body_html'], 'wp-signature'));
        $this->assertEquals(0, substr_count($step3['body_text'], '--'));
    }

    public function test_context_records_search_endpoint_respects_permissions(): void
    {
        Sanctum::actingAs($this->salesAgent);

        $client = Client::create([
            'client_id' => 'CLI-0055',
            'legal_name' => 'Auto Search Client',
            'status' => 'active',
        ]);

        Quotation::create([
            'reference' => 'QUO-SEARCH-777',
            'client_id' => (string) $client->id,
            'version' => 1,
            'valid_until' => now()->addDays(10),
            'currency' => 'TND',
            'total_ht' => 1000.00,
            'total_tva' => 190.00,
            'total_ttc' => 1190.00,
            'payment_terms' => \App\Modules\Crm\Enums\PaymentTerms::Immediate,
            'status' => QuotationStatus::Draft,
        ]);

        $response = $this->getJson('/api/communications/email/templates/context-records?type=quotation&query=777');
        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $this->assertEquals('QUO-SEARCH-777', $data[0]['reference']);
        $this->assertStringContainsString('Auto Search Client', $data[0]['label']);
    }

    public function test_dynamic_variables_endpoint_returns_catalog_and_resolved_values(): void
    {
        Sanctum::actingAs($this->salesAgent);

        $response = $this->getJson('/api/communications/email/templates/variables');
        $response->assertOk();

        $catalog = $response->json('data.catalog');
        $this->assertArrayHasKey('recipient', $catalog);
        $this->assertArrayHasKey('document', $catalog);
        $this->assertArrayHasKey('client', $catalog);
        $this->assertArrayHasKey('company', $catalog);
    }

    public function test_wp_email_mailable_provides_both_html_and_text_multipart(): void
    {
        $profile = EmailSenderProfile::create([
            'name' => 'Wave Support',
            'email' => 'support@waveplus.xyz',
            'type' => 'shared_mailbox',
            'department' => 'Operations',
            'is_active' => true,
        ]);

        $message = EmailMessage::create([
            'sender_profile_id' => $profile->id,
            'direction' => EmailMessage::DIRECTION_OUTGOING,
            'status' => 'draft',
            'subject' => 'Multipart Test Subject',
            'body_html' => '<p>Hello <strong>World</strong> in HTML</p>',
            'body_text' => "Hello World in Plain Text\n\nBest Regards",
            'to_recipients' => [['email' => 'test@example.com', 'name' => 'Test User']],
            'created_by' => $this->salesAgent->id,
        ]);

        $mailable = new WpEmailMailable($message);
        $content = $mailable->content();

        $this->assertEquals('<p>Hello <strong>World</strong> in HTML</p>', $content->htmlString);
        $this->assertEquals("Hello World in Plain Text\n\nBest Regards", $mailable->getTextBody());
    }

    public function test_template_render_identifies_missing_record_context_and_missing_variables(): void
    {
        Sanctum::actingAs($this->salesAgent);

        $template = EmailTemplate::where('slug', 'quotation_submission_fr')->firstOrFail();

        // Render WITHOUT record_id or context document
        $response = $this->postJson("/api/communications/email/templates/{$template->id}/render", [
            'recipient' => ['name' => 'Pending Client'],
            'signature_type' => 'none',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('requires_record_context'));
        $this->assertTrue($response->json('has_missing_record_context'));
        $this->assertContains('document.reference', $response->json('missing_variables'));
    }

    public function test_subject_modification_and_draft_persistence(): void
    {
        Sanctum::actingAs($this->salesAgent);

        $payload = [
            'to' => [['email' => 'partner@example.com', 'name' => 'Partner Corp']],
            'subject' => 'Custom Handcrafted Subject (Modified by User)',
            'body_html' => '<p>Custom message content written in Step 3 editor.</p>',
            'send_immediately' => false,
        ];

        $response = $this->postJson('/api/communications/email/messages', $payload);
        $response->assertStatus(201);

        $messageId = $response->json('data.id');
        $this->assertNotNull($messageId);

        // Fetch draft to verify subject and content persistence
        $getRes = $this->getJson("/api/communications/email/messages/{$messageId}");
        $getRes->assertOk();
        $this->assertEquals('Custom Handcrafted Subject (Modified by User)', $getRes->json('data.subject'));
        $this->assertStringContainsString('Custom message content', $getRes->json('data.body_html'));
        $this->assertNotEmpty($getRes->json('data.body_text'));
    }
}
