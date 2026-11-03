<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Communications\Email\Models\EmailMailbox;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use Illuminate\Database\Seeder;

class EmailMailboxSeeder extends Seeder
{
    public function run(): void
    {
        $mailboxes = [
            [
                'name' => 'Sales & Inquiries',
                'email' => 'sales@waveplus.xyz',
                'department' => 'sales',
                'sender_email' => 'sales@waveplus.xyz',
                'imap_host' => env('IMAP_HOST', 'mail.waveplus.xyz'),
                'imap_port' => (int) env('IMAP_PORT', 993),
                'imap_encryption' => env('IMAP_ENCRYPTION', 'ssl'),
                'imap_username' => 'sales@waveplus.xyz',
                'imap_folder' => 'INBOX',
                'is_active' => true,
            ],
            [
                'name' => 'Purchasing & Supply Chain',
                'email' => 'purchasing@waveplus.xyz',
                'department' => 'purchasing',
                'sender_email' => 'purchasing@waveplus.xyz',
                'imap_host' => env('IMAP_HOST', 'mail.waveplus.xyz'),
                'imap_port' => (int) env('IMAP_PORT', 993),
                'imap_encryption' => env('IMAP_ENCRYPTION', 'ssl'),
                'imap_username' => 'purchasing@waveplus.xyz',
                'imap_folder' => 'INBOX',
                'is_active' => true,
            ],
            [
                'name' => 'Finance & Invoicing',
                'email' => 'finance@waveplus.xyz',
                'department' => 'finance',
                'sender_email' => 'finance@waveplus.xyz',
                'imap_host' => env('IMAP_HOST', 'mail.waveplus.xyz'),
                'imap_port' => (int) env('IMAP_PORT', 993),
                'imap_encryption' => env('IMAP_ENCRYPTION', 'ssl'),
                'imap_username' => 'finance@waveplus.xyz',
                'imap_folder' => 'INBOX',
                'is_active' => true,
            ],
            [
                'name' => 'HR & Recruitment',
                'email' => 'hr@waveplus.xyz',
                'department' => 'hr',
                'sender_email' => 'hr@waveplus.xyz',
                'imap_host' => env('IMAP_HOST', 'mail.waveplus.xyz'),
                'imap_port' => (int) env('IMAP_PORT', 993),
                'imap_encryption' => env('IMAP_ENCRYPTION', 'ssl'),
                'imap_username' => 'hr@waveplus.xyz',
                'imap_folder' => 'INBOX',
                'is_active' => true,
            ],
            [
                'name' => 'Executive & Direction',
                'email' => 'direction@waveplus.xyz',
                'department' => 'direction',
                'sender_email' => 'direction@waveplus.xyz',
                'imap_host' => env('IMAP_HOST', 'mail.waveplus.xyz'),
                'imap_port' => (int) env('IMAP_PORT', 993),
                'imap_encryption' => env('IMAP_ENCRYPTION', 'ssl'),
                'imap_username' => 'direction@waveplus.xyz',
                'imap_folder' => 'INBOX',
                'is_active' => true,
            ],
        ];

        foreach ($mailboxes as $data) {
            $senderEmail = $data['sender_email'];
            unset($data['sender_email']);

            $sender = EmailSenderProfile::where('email', $senderEmail)->first();
            $data['sender_profile_id'] = $sender?->id;

            EmailMailbox::firstOrCreate(
                ['email' => $data['email']],
                $data
            );
        }
    }
}
