<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Communications\Email\Models\EmailSenderProfile;
use Illuminate\Database\Seeder;

class EmailSenderProfileSeeder extends Seeder
{
    /**
     * Predefined sender identities for WAVE PLUS.
     */
    public function run(): void
    {
        $profiles = [
            [
                'name' => 'WAVE PLUS System',
                'email' => 'system@waveplus.xyz',
                'reply_to' => null,
                'department' => 'System',
                'is_system' => true,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'WAVE PLUS Commercial',
                'email' => 'sales@waveplus.xyz',
                'reply_to' => 'sales@waveplus.xyz',
                'department' => 'Commercial',
                'is_system' => false,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'WAVE PLUS Achats',
                'email' => 'purchasing@waveplus.xyz',
                'reply_to' => 'purchasing@waveplus.xyz',
                'department' => 'Procurement',
                'is_system' => false,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'WAVE PLUS Ressources Humaines',
                'email' => 'hr@waveplus.xyz',
                'reply_to' => 'hr@waveplus.xyz',
                'department' => 'Human Resources',
                'is_system' => false,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'WAVE PLUS Finances',
                'email' => 'finance@waveplus.xyz',
                'reply_to' => 'finance@waveplus.xyz',
                'department' => 'Finance',
                'is_system' => false,
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'WAVE PLUS Direction',
                'email' => 'direction@waveplus.xyz',
                'reply_to' => 'direction@waveplus.xyz',
                'department' => 'Direction',
                'is_system' => false,
                'is_default' => true,
                'is_active' => true,
            ],
        ];

        foreach ($profiles as $profile) {
            EmailSenderProfile::updateOrCreate(
                ['email' => $profile['email']],
                $profile
            );
        }
    }
}
