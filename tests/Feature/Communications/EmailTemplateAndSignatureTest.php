<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Communications\Email\Models\EmailTemplate;
use App\Modules\Communications\Email\Services\EmailSignatureService;
use App\Modules\Communications\Email\Services\TemplateVariableResolver;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailTemplateAndSignatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    private User $unauthorized;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed templates
        $this->seed(EmailTemplateSeeder::class);

        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Admin', 'web');

        Permission::findOrCreate('read-Communications', 'web');
        Permission::findOrCreate('create-email-messages', 'web');

        $this->admin = User::factory()->create(['name' => 'Admin Manager', 'email' => 'admin@waveplus.xyz']);
        $this->admin->assignRole('Super Admin');

        $this->user = User::factory()->create(['name' => 'Jane Agent', 'email' => 'jane@waveplus.xyz']);
        $this->user->givePermissionTo(['read-Communications', 'create-email-messages']);

        $this->unauthorized = User::factory()->create();
    }

    public function test_all_eleven_categories_are_seeded_in_french_english_and_arabic(): void
    {
        $expectedCategories = [
            'general_communication',
            'quotation_submission',
            'purchase_order_submission',
            'sales_order_communication',
            'invoice_delivery',
            'payment_reminder',
            'supplier_request',
            'delivery_confirmation',
            'mission_confirmation',
            'maintenance_notification',
            'system_notification',
        ];

        foreach ($expectedCategories as $category) {
            $this->assertDatabaseHas('email_templates', [
                'category' => $category,
                'language' => 'fr',
                'is_active' => true,
            ]);

            $this->assertDatabaseHas('email_templates', [
                'category' => $category,
                'language' => 'en',
                'is_active' => true,
            ]);

            $this->assertDatabaseHas('email_templates', [
                'category' => $category,
                'language' => 'ar',
                'is_active' => true,
            ]);
        }

        $this->assertEquals(33, EmailTemplate::count());
    }

    public function test_unauthenticated_user_cannot_access_templates(): void
    {
        $this->getJson('/api/communications/email/templates')->assertUnauthorized();
        $this->getJson('/api/communications/email/signatures')->assertUnauthorized();
    }

    public function test_unauthorized_user_gets_403_when_listing_templates(): void
    {
        Sanctum::actingAs($this->unauthorized);

        $this->getJson('/api/communications/email/templates')->assertForbidden();
    }

    public function test_authorized_user_can_list_and_filter_templates_by_category_and_language(): void
    {
        Sanctum::actingAs($this->user);

        // List all
        $response = $this->getJson('/api/communications/email/templates');
        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'slug', 'category', 'language', 'name', 'subject']]]);
        $this->assertCount(33, $response->json('data'));

        // Filter by category
        $catResponse = $this->getJson('/api/communications/email/templates?filter[category]=quotation_submission');
        $catResponse->assertOk();
        $this->assertCount(3, $catResponse->json('data'));

        // Filter by category AND language
        $langResponse = $this->getJson('/api/communications/email/templates?filter[category]=quotation_submission&filter[language]=fr');
        $langResponse->assertOk();
        $this->assertCount(1, $langResponse->json('data'));
        $this->assertEquals('quotation_submission_fr', $langResponse->json('data.0.slug'));

        // Filter by Arabic
        $arResponse = $this->getJson('/api/communications/email/templates?filter[category]=quotation_submission&filter[language]=ar');
        $arResponse->assertOk();
        $this->assertCount(1, $arResponse->json('data'));
        $this->assertEquals('quotation_submission_ar', $arResponse->json('data.0.slug'));
    }

    public function test_policy_denies_create_update_delete_to_enforce_read_only_system_catalog(): void
    {
        $template = EmailTemplate::first();

        // Standard user denied
        $this->assertFalse(Gate::forUser($this->user)->allows('create', EmailTemplate::class));
        $this->assertFalse(Gate::forUser($this->user)->allows('update', $template));
        $this->assertFalse(Gate::forUser($this->user)->allows('delete', $template));

        // Admin role (non Super Admin) denied
        $regularAdmin = User::factory()->create();
        $regularAdmin->assignRole('Admin');
        $this->assertFalse(Gate::forUser($regularAdmin)->allows('create', EmailTemplate::class));
        $this->assertFalse(Gate::forUser($regularAdmin)->allows('update', $template));
        $this->assertFalse(Gate::forUser($regularAdmin)->allows('delete', $template));
    }

    public function test_variable_resolver_safely_interpolates_nested_tokens(): void
    {
        $resolver = app(TemplateVariableResolver::class);

        $template = 'Hello {{ recipient.name }} from {{ company.name }}, your doc {{ document.reference }} totals {{ document.total }}.';
        $context = [
            'recipient' => ['name' => 'Acme Corp'],
            'company' => ['name' => 'WAVE PLUS'],
            'document' => [
                'reference' => 'QUO-2026-0042',
                'total' => '1 250,00 TND',
            ],
        ];

        $resolved = $resolver->resolve($template, $context);

        $this->assertEquals('Hello Acme Corp from WAVE PLUS, your doc QUO-2026-0042 totals 1 250,00 TND.', $resolved);
    }

    public function test_variable_resolver_never_executes_arbitrary_code(): void
    {
        $resolver = app(TemplateVariableResolver::class);

        $malicious = 'Test: {{ system("whoami") }} and {{ phpinfo() }} and {{ unknown_token }}';
        $context = ['safe' => 'value'];

        $resolved = $resolver->resolve($malicious, $context);

        // Valid token syntax without matching context key is replaced with empty string
        // Malicious non-identifier syntax like function calls remains inert and unexecuted
        $this->assertStringContainsString('{{ system("whoami") }}', $resolved);
        $this->assertStringContainsString('{{ phpinfo() }}', $resolved);
        $this->assertStringNotContainsString('unknown_token', $resolved);
    }

    public function test_variable_resolver_validates_required_provided_and_missing_tokens(): void
    {
        $resolver = app(TemplateVariableResolver::class);

        $template = 'Dear {{ recipient.name }}, your invoice {{ document.reference }} of {{ document.total }} is due on {{ document.due_date }}.';
        $context = [
            'recipient' => ['name' => 'Global Logistics'],
            'document' => [
                'reference' => 'INV-001',
                // missing: total, due_date
            ],
        ];

        $validation = $resolver->validateContext($template, $context);

        $this->assertContains('recipient.name', $validation['provided']);
        $this->assertContains('document.reference', $validation['provided']);
        $this->assertContains('document.total', $validation['missing']);
        $this->assertContains('document.due_date', $validation['missing']);
    }

    public function test_render_preview_endpoint_returns_rendered_preview_and_does_not_modify_original(): void
    {
        Sanctum::actingAs($this->user);

        $template = EmailTemplate::findBySlug('quotation_submission_fr');
        $this->assertNotNull($template);
        $originalSubject = $template->subject;
        $originalHtml = $template->body_html;

        $response = $this->postJson("/api/communications/email/templates/{$template->id}/render", [
            'context' => [
                'recipient' => ['name' => 'Société Alpha'],
                'document' => [
                    'reference' => 'DEV-2026-0099',
                    'date' => '31/10/2026',
                    'total' => '8 400,00 TND',
                ],
            ],
            'signature_type' => 'personal',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.template_id', $template->id)
            ->assertJsonPath('data.signature_type', 'personal');

        $data = $response->json('data');

        // Check that variables were resolved in rendered text
        $this->assertStringContainsString('DEV-2026-0099', $data['subject']);
        $this->assertStringContainsString('Société Alpha', $data['body_html']);
        $this->assertStringContainsString('8 400,00 TND', $data['body_html']);

        // Check personal signature was appended
        $this->assertStringContainsString('wp-signature-personal', $data['body_html']);
        $this->assertStringContainsString('Jane Agent', $data['body_html']);

        // Strictly verify original template in database was untouched!
        $fresh = $template->fresh();
        $this->assertEquals($originalSubject, $fresh->subject);
        $this->assertEquals($originalHtml, $fresh->body_html);
    }

    public function test_signature_service_supports_personal_company_and_none(): void
    {
        $signatureService = app(EmailSignatureService::class);

        $signatures = $signatureService->getSignaturesFor($this->user);

        $this->assertArrayHasKey('personal', $signatures);
        $this->assertArrayHasKey('company', $signatures);
        $this->assertArrayHasKey('none', $signatures);

        $this->assertStringContainsString('Jane Agent', $signatures['personal']['html']);
        $this->assertStringContainsString('WAVE PLUS', $signatures['company']['html']);
        $this->assertEmpty($signatures['none']['html']);

        // Test appending
        $base = '<p>Email message body.</p>';
        $appendedNone = $signatureService->appendSignature($base, null, 'none', $this->user);
        $this->assertEquals($base, $appendedNone['body_html']);

        $appendedCompany = $signatureService->appendSignature($base, null, 'company', $this->user);
        $this->assertStringContainsString('wp-signature-company', $appendedCompany['body_html']);
    }

    public function test_signatures_api_endpoint_returns_available_signatures(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/communications/email/signatures');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['personal', 'company', 'none']]);

        $this->assertStringContainsString('Jane Agent', $response->json('data.personal.html'));
    }

    public function test_templates_api_returns_description_and_requires_record_context_attributes(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/communications/email/templates');
        $response->assertOk();

        $templates = collect($response->json('data'));

        // Check general communication template
        $general = $templates->firstWhere('slug', 'general_communication_fr');
        $this->assertNotNull($general);
        $this->assertNotEmpty($general['description']);
        $this->assertFalse($general['requires_record_context']);

        // Check quotation template
        $quotation = $templates->firstWhere('slug', 'quotation_submission_fr');
        $this->assertNotNull($quotation);
        $this->assertNotEmpty($quotation['description']);
        $this->assertTrue($quotation['requires_record_context']);
    }

    public function test_templates_support_french_english_and_arabic_with_metadata(): void
    {
        Sanctum::actingAs($this->user);

        foreach (['fr', 'en', 'ar'] as $lang) {
            $response = $this->getJson("/api/communications/email/templates?filter[language]={$lang}");
            $response->assertOk();
            $data = $response->json('data');

            $this->assertCount(11, $data);
            foreach ($data as $tmpl) {
                $this->assertEquals($lang, $tmpl['language']);
                $this->assertNotEmpty($tmpl['name']);
                $this->assertNotEmpty($tmpl['subject']);
                $this->assertNotEmpty($tmpl['description']);
                $this->assertIsBool($tmpl['requires_record_context']);
            }
        }
    }

    public function test_template_immutability_enforces_read_only_system_definitions(): void
    {
        $regularAdmin = User::factory()->create();
        $regularAdmin->assignRole('Admin');

        $template = EmailTemplate::first();

        // 1. Policy denies create, update, delete for users and Admin role
        $this->assertFalse(Gate::forUser($this->user)->allows('create', EmailTemplate::class));
        $this->assertFalse(Gate::forUser($this->user)->allows('update', $template));
        $this->assertFalse(Gate::forUser($this->user)->allows('delete', $template));

        $this->assertFalse(Gate::forUser($regularAdmin)->allows('create', EmailTemplate::class));
        $this->assertFalse(Gate::forUser($regularAdmin)->allows('update', $template));
        $this->assertFalse(Gate::forUser($regularAdmin)->allows('delete', $template));

        // 2. Unroutable mutation endpoints return 405 Method Not Allowed
        Sanctum::actingAs($regularAdmin);

        $this->postJson('/api/communications/email/templates', [
            'name' => 'Hacked Template',
        ])->assertStatus(405);

        $this->putJson("/api/communications/email/templates/{$template->id}", [
            'name' => 'Modified Template',
        ])->assertStatus(405);

        $this->deleteJson("/api/communications/email/templates/{$template->id}")->assertStatus(405);
    }
}
