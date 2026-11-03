<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Communications\Email\Contracts\MailboxReaderInterface;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Models\EmailMailbox;
use App\Modules\Communications\Email\Models\EmailMessage;
use App\Modules\Communications\Email\Services\EmailSynchronizationService;
use Database\Seeders\EmailSenderProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailMailboxAndSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(EmailSenderProfileSeeder::class);

        Role::findOrCreate('Super Admin', 'web');
        Permission::findOrCreate('read-Communications', 'web');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    public function test_mailbox_password_is_encrypted_and_never_exposed_in_api(): void
    {
        Sanctum::actingAs($this->admin);

        $mailbox = EmailMailbox::create([
            'name' => 'Sales Inbox',
            'email' => 'sales@waveplus.xyz',
            'department' => 'sales',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'sales@waveplus.xyz',
            'imap_password' => 'superSecretPassword123',
            'is_active' => true,
        ]);

        // Decrypted accessor works in backend service
        $this->assertSame('superSecretPassword123', $mailbox->decrypted_password);

        // Database stores ciphertext, not plaintext
        $raw = EmailMailbox::withoutGlobalScopes()->where('id', $mailbox->id)->value('imap_password');
        $this->assertNotSame('superSecretPassword123', $raw);
        $this->assertSame('superSecretPassword123', Crypt::decryptString($raw));

        // API never exposes imap_password
        $response = $this->getJson('/api/communications/email/mailboxes');
        $response->assertOk();
        $this->assertStringNotContainsString('superSecretPassword123', $response->getContent());
        $this->assertArrayNotHasKey('imap_password', $response->json('data.0'));
    }

    public function test_synchronization_imports_messages_and_tracks_uid(): void
    {
        $mailbox = EmailMailbox::create([
            'name' => 'Support Mailbox',
            'email' => 'support@waveplus.xyz',
            'department' => 'support',
            'is_active' => true,
            'last_synced_uid' => 100,
        ]);

        $mockReader = new class implements MailboxReaderInterface
        {
            public function connect(EmailMailbox $mailbox): bool
            {
                return true;
            }

            public function fetchNewMessages(EmailMailbox $mailbox, int $limit = 50): array
            {
                return [
                    [
                        'uid' => 101,
                        'message_id' => '<msg-101@example.com>',
                        'in_reply_to' => null,
                        'references' => null,
                        'subject' => 'Help with order #4490',
                        'from_email' => 'customer@client.com',
                        'from_name' => 'Alice Customer',
                        'to' => [['email' => 'support@waveplus.xyz', 'name' => 'Support']],
                        'cc' => [],
                        'bcc' => [],
                        'body_html' => '<p>Please assist with my order.</p>',
                        'body_text' => 'Please assist with my order.',
                        'date' => '2026-10-31 10:00:00',
                        'attachments' => [
                            [
                                'filename' => 'invoice-copy.pdf',
                                'mime_type' => 'application/pdf',
                                'content' => '%PDF-1.4 mock pdf data',
                                'size_bytes' => 24,
                            ],
                        ],
                    ],
                ];
            }

            public function disconnect(): void {}

            public function getLastError(): ?string
            {
                return null;
            }

            public function getFolderUidValidity(EmailMailbox $mailbox): ?int
            {
                return null;
            }
        };

        $service = new EmailSynchronizationService($mockReader);
        $result = $service->syncMailbox($mailbox);

        $this->assertSame(1, $result['synced']);
        $this->assertSame(0, $result['skipped']);

        // Mailbox UID is updated
        $mailbox->refresh();
        $this->assertSame(101, $mailbox->last_synced_uid);
        $this->assertNotNull($mailbox->last_synced_at);

        // EmailMessage is created with folder inbox and incoming direction
        $msg = EmailMessage::where('message_id', '<msg-101@example.com>')->first();
        $this->assertNotNull($msg);
        $this->assertSame('incoming', $msg->direction);
        $this->assertSame('inbox', $msg->folder);
        $this->assertSame('Help with order #4490', $msg->subject);
        $this->assertFalse($msg->is_read);

        // Attachment is stored securely on private disk
        $att = $msg->attachments->first();
        $this->assertNotNull($att);
        $this->assertSame('invoice-copy.pdf', $att->filename);
        $this->assertSame('incoming', $att->source);
        Storage::disk('local')->assertExists($att->storage_path);
        $this->assertSame('%PDF-1.4 mock pdf data', Storage::disk('local')->get($att->storage_path));

        // Conversation thread was created
        $conv = $msg->conversation;
        $this->assertNotNull($conv);
        $this->assertSame('Help with order #4490', $conv->subject);
        $this->assertSame('support', $conv->department);
        $this->assertSame(EmailConversation::STATUS_OPEN, $conv->status);
    }

    public function test_duplicate_prevention_skips_already_synced_messages(): void
    {
        $mailbox = EmailMailbox::create([
            'name' => 'Support Mailbox',
            'email' => 'support@waveplus.xyz',
            'department' => 'support',
            'is_active' => true,
        ]);

        $mockReader = new class implements MailboxReaderInterface
        {
            public function connect(EmailMailbox $mailbox): bool
            {
                return true;
            }

            public function fetchNewMessages(EmailMailbox $mailbox, int $limit = 50): array
            {
                return [
                    [
                        'uid' => 200,
                        'message_id' => '<unique-id-999@client.com>',
                        'in_reply_to' => null,
                        'references' => null,
                        'subject' => 'Quote Request',
                        'from_email' => 'procurement@client.com',
                        'from_name' => 'Bob Procurement',
                        'to' => [['email' => 'support@waveplus.xyz', 'name' => 'Support']],
                        'cc' => [],
                        'bcc' => [],
                        'body_html' => '<p>Requesting quote</p>',
                        'body_text' => 'Requesting quote',
                        'date' => '2026-10-31 11:00:00',
                        'attachments' => [],
                    ],
                ];
            }

            public function disconnect(): void {}

            public function getLastError(): ?string
            {
                return null;
            }

            public function getFolderUidValidity(EmailMailbox $mailbox): ?int
            {
                return null;
            }
        };

        $service = new EmailSynchronizationService($mockReader);

        // First sync: 1 message imported
        $firstResult = $service->syncMailbox($mailbox);
        $this->assertSame(1, $firstResult['synced']);
        $this->assertSame(0, $firstResult['skipped']);
        $this->assertSame(1, EmailMessage::count());

        // Second sync: duplicate skipped
        $secondResult = $service->syncMailbox($mailbox);
        $this->assertSame(0, $secondResult['synced']);
        $this->assertSame(1, $secondResult['skipped']);
        $this->assertSame(1, EmailMessage::count());
    }

    public function test_conversation_thread_matching_via_in_reply_to_and_references(): void
    {
        $mailbox = EmailMailbox::create([
            'name' => 'Sales Mailbox',
            'email' => 'sales@waveplus.xyz',
            'department' => 'sales',
            'is_active' => true,
        ]);

        // Original conversation and outgoing message
        $conversation = EmailConversation::create([
            'mailbox_id' => $mailbox->id,
            'department' => 'sales',
            'subject' => 'Quotation #QT-2026-001',
            'status' => EmailConversation::STATUS_OPEN,
        ]);

        $parentMessage = EmailMessage::create([
            'conversation_id' => $conversation->id,
            'mailbox_id' => $mailbox->id,
            'direction' => 'outgoing',
            'folder' => 'sent',
            'subject' => 'Quotation #QT-2026-001',
            'body_html' => '<p>Here is your quote.</p>',
            'message_id' => '<qt-parent-msg@waveplus.xyz>',
            'status' => 'sent',
        ]);

        $mockReader = new class implements MailboxReaderInterface
        {
            public function connect(EmailMailbox $mailbox): bool
            {
                return true;
            }

            public function fetchNewMessages(EmailMailbox $mailbox, int $limit = 50): array
            {
                return [
                    [
                        'uid' => 305,
                        'message_id' => '<reply-305@client.com>',
                        'in_reply_to' => '<qt-parent-msg@waveplus.xyz>',
                        'references' => '<qt-parent-msg@waveplus.xyz>',
                        'subject' => 'Re: Quotation #QT-2026-001',
                        'from_email' => 'buyer@client.com',
                        'from_name' => 'Buyer',
                        'to' => [['email' => 'sales@waveplus.xyz', 'name' => 'Sales']],
                        'cc' => [],
                        'bcc' => [],
                        'body_html' => '<p>We accept the quote!</p>',
                        'body_text' => 'We accept the quote!',
                        'date' => '2026-10-31 12:00:00',
                        'attachments' => [],
                    ],
                ];
            }

            public function disconnect(): void {}

            public function getLastError(): ?string
            {
                return null;
            }

            public function getFolderUidValidity(EmailMailbox $mailbox): ?int
            {
                return null;
            }
        };

        $service = new EmailSynchronizationService($mockReader);
        $result = $service->syncMailbox($mailbox);

        $this->assertSame(1, $result['synced']);

        // The incoming message joined the EXISTING conversation rather than creating a new one
        $incomingMsg = EmailMessage::where('message_id', '<reply-305@client.com>')->first();
        $this->assertNotNull($incomingMsg);
        $this->assertSame($conversation->id, $incomingMsg->conversation_id);
        $this->assertSame(1, EmailConversation::count());
    }
}
