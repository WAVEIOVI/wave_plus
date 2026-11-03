<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\BusinessPartners\Models\Client;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Models\EmailRecordLink;
use App\Modules\Communications\Email\Services\EmailRecordLinkService;
use App\Modules\Communications\Email\Services\EmailRecordMatcherService;
use App\Modules\Crm\Models\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextualEmailAssociationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $crmUser;

    private User $unauthorizedUser;

    private EmailConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        // Create core permissions
        Permission::findOrCreate('access-email', 'web');
        Permission::findOrCreate('read-Communications', 'web');
        Permission::findOrCreate('read-BusinessPartners', 'web');
        Permission::findOrCreate('read-CrmSales', 'web');
        Permission::findOrCreate('read-Procurement', 'web');
        Permission::findOrCreate('read-Finance', 'web');
        Permission::findOrCreate('read-OperationalHub', 'web');
        Permission::findOrCreate('read-Hr', 'web');

        $adminRole = Role::findOrCreate('Admin', 'web');

        $this->seed(\Database\Seeders\EmailSenderProfileSeeder::class);

        $this->adminUser = User::factory()->create([
            'email' => 'admin@waveplus.xyz',
        ]);
        $this->adminUser->assignRole($adminRole);

        $this->crmUser = User::factory()->create([
            'email' => 'sales@waveplus.xyz',
        ]);
        $this->crmUser->givePermissionTo(['access-email', 'read-Communications', 'read-CrmSales', 'read-BusinessPartners']);

        $this->unauthorizedUser = User::factory()->create([
            'email' => 'guest@waveplus.xyz',
        ]);

        $this->conversation = EmailConversation::create([
            'subject' => 'Project Discussion Regarding Quotation QT-2026-0099',
            'department' => 'sales',
            'status' => 'open',
            'first_message_at' => now(),
            'last_message_at' => now(),
        ]);
    }

    public function test_authorized_user_can_link_and_unlink_conversation_with_client(): void
    {
        $client = Client::create([
            'client_id' => 'CL-001',
            'legal_name' => 'Acme Corporation',
            'trade_name' => 'Acme Corp',
            'currency' => 'EUR',
            'primary_email' => 'contact@acme.com',
            'status' => 'active',
        ]);

        // Link
        $response = $this->actingAs($this->crmUser)
            ->postJson("/api/communications/email/conversations/{$this->conversation->id}/links", [
                'record_type' => 'client',
                'record_id' => (string) $client->id,
                'reason' => 'Client email link',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('email_associations', [
            'conversation_id' => $this->conversation->id,
            'associable_type' => Client::class,
            'associable_id' => (string) $client->id,
            'is_confirmed' => 1,
        ]);

        // List links
        $listRes = $this->actingAs($this->crmUser)
            ->getJson("/api/communications/email/conversations/{$this->conversation->id}/links");

        $listRes->assertStatus(200);
        $listRes->assertJsonFragment([
            'record_type' => 'client',
            'record_reference' => 'Acme Corporation',
        ]);

        // Unlink
        $delRes = $this->actingAs($this->crmUser)
            ->deleteJson("/api/communications/email/conversations/{$this->conversation->id}/links", [
                'record_type' => 'client',
                'record_id' => (string) $client->id,
            ]);

        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('email_associations', [
            'conversation_id' => $this->conversation->id,
            'associable_type' => Client::class,
            'associable_id' => (string) $client->id,
        ]);
    }

    public function test_user_cannot_link_record_without_module_permission(): void
    {
        $client = Client::create([
            'client_id' => 'CL-002',
            'legal_name' => 'Beta Industries',
            'trade_name' => 'Beta',
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->unauthorizedUser)
            ->postJson("/api/communications/email/conversations/{$this->conversation->id}/links", [
                'record_type' => 'client',
                'record_id' => (string) $client->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_conversation_can_be_associated_with_multiple_records(): void
    {
        $client = Client::create([
            'client_id' => 'CL-003',
            'legal_name' => 'Gamma Solutions',
            'trade_name' => 'Gamma',
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-0099',
            'client_id' => $client->id,
            'status' => 'draft',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 5000,
            'total_tva' => 1000,
            'total_ttc' => 6000,
        ]);

        $service = app(EmailRecordLinkService::class);
        $service->linkRecord($this->conversation, 'client', $client->id, $this->adminUser);
        $service->linkRecord($this->conversation, 'quotation', $quotation->id, $this->adminUser);

        $this->assertDatabaseCount('email_associations', 2);

        $links = $service->getLinksForConversation($this->conversation, $this->adminUser);
        $this->assertCount(2, $links);
    }

    public function test_records_endpoint_returns_relevant_conversations_for_associated_record(): void
    {
        $client = Client::create([
            'client_id' => 'CL-004',
            'legal_name' => 'Delta Tech',
            'trade_name' => 'Delta',
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        app(EmailRecordLinkService::class)->linkRecord(
            $this->conversation,
            'client',
            $client->id,
            $this->adminUser
        );

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/communications/email/records/client/{$client->id}/conversations");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $this->conversation->id,
            'subject' => $this->conversation->subject,
        ]);
    }

    public function test_conversation_history_is_preserved_when_record_is_archived(): void
    {
        $client = Client::create([
            'client_id' => 'CL-005',
            'legal_name' => 'Epsilon Soft',
            'trade_name' => 'Epsilon',
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-0100',
            'client_id' => $client->id,
            'status' => 'draft',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 1000,
            'total_tva' => 200,
            'total_ttc' => 1200,
        ]);

        $service = app(EmailRecordLinkService::class);
        $service->linkRecord($this->conversation, 'quotation', $quotation->id, $this->adminUser);

        // Soft delete/archive the quotation
        $quotation->delete();
        $this->assertSoftDeleted('quotations', ['id' => $quotation->id]);

        // Links and conversations are preserved
        $links = $service->getLinksForConversation($this->conversation, $this->adminUser);
        $this->assertCount(1, $links);
        $this->assertEquals('quotation', $links->first()['record_type']);

        $recordConvs = $service->getConversationsForRecord('quotation', $quotation->id, $this->adminUser);
        $this->assertEquals(1, $recordConvs->total());
    }

    public function test_automatic_matching_finds_verified_document_reference(): void
    {
        $client = Client::create([
            'client_id' => 'CL-006',
            'legal_name' => 'Zeta Corp',
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        $quotation = Quotation::create([
            'reference' => 'QT-2026-0099',
            'client_id' => $client->id,
            'status' => 'draft',
            'valid_until' => now()->addDays(30),
            'payment_terms' => 'net_30',
            'currency' => 'EUR',
            'total_ht' => 2000,
            'total_tva' => 400,
            'total_ttc' => 2400,
        ]);

        $matcher = app(EmailRecordMatcherService::class);
        $matches = $matcher->matchRecords($this->conversation);

        $this->assertNotEmpty($matches);
        $quotationMatch = collect($matches)->firstWhere('record_type', 'quotation');
        $this->assertNotNull($quotationMatch);
        $this->assertEquals((string) $quotation->id, $quotationMatch['record_id']);
        $this->assertTrue($quotationMatch['is_confirmed']);
        $this->assertEquals(1.00, $quotationMatch['confidence_score']);
    }

    public function test_user_can_confirm_and_dismiss_suggested_links(): void
    {
        $client = Client::create([
            'client_id' => 'CL-007',
            'legal_name' => 'Eta Ltd',
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        // Create an unconfirmed suggestion
        $link = EmailRecordLink::create([
            'conversation_id' => $this->conversation->id,
            'associable_type' => Client::class,
            'associable_id' => (string) $client->id,
            'link_type' => 'auto_email',
            'is_confirmed' => false,
            'confidence_score' => 0.70,
            'match_reason' => 'Suggested by email match',
        ]);

        // Confirm
        $confirmRes = $this->actingAs($this->adminUser)
            ->postJson("/api/communications/email/record-links/{$link->id}/confirm");

        $confirmRes->assertStatus(200);
        $this->assertDatabaseHas('email_associations', [
            'id' => $link->id,
            'is_confirmed' => 1,
            'confidence_score' => 1.00,
        ]);

        // Dismiss
        $dismissRes = $this->actingAs($this->adminUser)
            ->postJson("/api/communications/email/record-links/{$link->id}/dismiss");

        $dismissRes->assertStatus(200);
        $this->assertDatabaseMissing('email_associations', [
            'id' => $link->id,
        ]);
    }

    public function test_email_search_filters_by_advanced_criteria(): void
    {
        $message = EmailMessage::create([
            'conversation_id' => $this->conversation->id,
            'subject' => 'Project Discussion Regarding Quotation QT-2026-0099',
            'body_html' => '<p>Please find attached our agreement specifications.</p>',
            'status' => 'sent',
            'folder' => 'sent',
            'direction' => 'outgoing',
            'created_at' => now(),
        ]);
        $message->recipients()->create([
            'type' => 'to',
            'email' => 'client@acme.com',
            'name' => 'Acme Procurement',
        ]);

        // Filter by recipient
        $resRecipient = $this->actingAs($this->adminUser)
            ->getJson('/api/communications/email/messages?recipient=acme.com&filter=sent');
        $resRecipient->assertStatus(200);
        $this->assertCount(1, $resRecipient->json('emails'));

        // Filter by content
        $resContent = $this->actingAs($this->adminUser)
            ->getJson('/api/communications/email/messages?content=specifications&filter=sent');
        $resContent->assertStatus(200);
        $this->assertCount(1, $resContent->json('emails'));

        // Filter by date
        $resDate = $this->actingAs($this->adminUser)
            ->getJson('/api/communications/email/messages?date_from='.now()->subDay()->toDateString().'&filter=sent');
        $resDate->assertStatus(200);
        $this->assertCount(1, $resDate->json('emails'));
    }

    public function test_useful_views_filter_unanswered_and_pending_replies(): void
    {
        // Unanswered conversation (incoming message with no outgoing sent reply)
        $unansweredConv = EmailConversation::create([
            'subject' => 'Incoming urgent client request',
            'status' => 'open',
            'first_message_at' => now(),
            'last_message_at' => now(),
        ]);

        EmailMessage::create([
            'conversation_id' => $unansweredConv->id,
            'subject' => 'Incoming urgent client request',
            'body_html' => 'Can you help us?',
            'status' => 'sent',
            'folder' => 'inbox',
            'direction' => 'incoming',
            'created_at' => now(),
        ]);

        $res = $this->actingAs($this->adminUser)
            ->getJson('/api/communications/email/messages?filter=unanswered');

        $res->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($res->json('emails')));
        $this->assertArrayHasKey('unanswered', $res->json('emailsMeta'));
        $this->assertGreaterThanOrEqual(1, $res->json('emailsMeta.unanswered'));
    }

    public function test_global_search_returns_email_conversations(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/search?q=Quotation');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'title' => 'Email Conversations',
        ]);
        $response->assertJsonFragment([
            'title' => $this->conversation->subject,
        ]);
    }

    public function test_auto_matcher_distinguishes_unique_from_ambiguous_sender_email_matches(): void
    {
        // 1. Unique client match
        $uniqueClient = Client::create([
            'client_id' => 'CL-UNIQ-1',
            'legal_name' => 'Unique Enterprise',
            'primary_email' => 'unique-sender@domain.com',
            'status' => 'active',
        ]);

        $convUnique = EmailConversation::create([
            'subject' => 'Unique sender test',
            'status' => 'open',
        ]);

        $msgUnique = EmailMessage::create([
            'conversation_id' => $convUnique->id,
            'subject' => 'Unique sender test',
            'sender_email' => 'unique-sender@domain.com',
            'body_html' => 'Hello team',
            'status' => 'received',
            'direction' => 'incoming',
        ]);

        $matcher = app(EmailRecordMatcherService::class);
        $matchesUnique = $matcher->matchRecords($convUnique, $msgUnique);

        $clientMatch = collect($matchesUnique)->firstWhere('record_type', 'client');
        $this->assertNotNull($clientMatch);
        $this->assertTrue($clientMatch['is_confirmed'], 'Unique sender email must be confirmed.');
        $this->assertSame(0.95, $clientMatch['confidence_score']);

        // 2. Ambiguous match: two clients share the same email
        Client::create([
            'client_id' => 'CL-AMBIG-1',
            'legal_name' => 'Branch A',
            'primary_email' => 'shared@holding.com',
            'status' => 'active',
        ]);

        Client::create([
            'client_id' => 'CL-AMBIG-2',
            'legal_name' => 'Branch B',
            'primary_email' => 'shared@holding.com',
            'status' => 'active',
        ]);

        $convAmbig = EmailConversation::create([
            'subject' => 'Ambiguous sender test',
            'status' => 'open',
        ]);

        $msgAmbig = EmailMessage::create([
            'conversation_id' => $convAmbig->id,
            'subject' => 'Ambiguous sender test',
            'sender_email' => 'shared@holding.com',
            'body_html' => 'Hello from holding',
            'status' => 'received',
            'direction' => 'incoming',
        ]);

        $matchesAmbig = $matcher->matchRecords($convAmbig, $msgAmbig);
        $ambigMatches = collect($matchesAmbig)->where('record_type', 'client')->values();

        $this->assertCount(2, $ambigMatches);
        $this->assertFalse($ambigMatches[0]['is_confirmed'], 'Ambiguous sender email matches must NOT be confirmed silently.');
        $this->assertFalse($ambigMatches[1]['is_confirmed'], 'Ambiguous sender email matches must NOT be confirmed silently.');
        $this->assertSame(0.50, $ambigMatches[0]['confidence_score']);
        $this->assertStringContainsString('Ambiguous match', $ambigMatches[0]['match_reason']);
    }

    public function test_store_and_show_email_persists_and_returns_associations(): void
    {
        $client = Client::create([
            'client_id' => 'CL-STORE-1',
            'legal_name' => 'Store Assoc Client',
            'primary_email' => 'store-assoc@client.com',
            'status' => 'active',
        ]);

        $res = $this->actingAs($this->adminUser)
            ->postJson('/api/communications/email/messages', [
                'to' => [['email' => 'dest@client.com', 'name' => 'Recipient']],
                'subject' => 'Draft with associations',
                'body_html' => '<p>Body</p>',
                'primary_context_type' => 'client',
                'primary_context_id' => (string) $client->id,
                'associations' => [
                    ['type' => 'client', 'id' => (string) $client->id],
                ],
            ]);

        $res->assertCreated();
        $messageId = $res->json('data.id');

        $this->assertDatabaseHas('email_associations', [
            'associable_type' => Client::class,
            'associable_id' => (string) $client->id,
            'link_type' => 'manual',
            'is_confirmed' => true,
        ]);

        // Show endpoint
        $showRes = $this->actingAs($this->adminUser)
            ->getJson("/api/communications/email/messages/{$messageId}");

        $showRes->assertOk();
        $this->assertNotEmpty($showRes->json('email.associations'));
        $this->assertSame('client', $showRes->json('email.associations.0.record_type'));
        $this->assertSame((string) $client->id, $showRes->json('email.associations.0.record_id'));
        $this->assertSame('client', $showRes->json('email.primary_context.type'));
        $this->assertSame((string) $client->id, $showRes->json('email.primary_context.record_id'));
    }
}
