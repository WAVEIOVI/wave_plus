<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Models\User;

class EmailSignatureService
{
    public const TYPE_PERSONAL = 'personal';

    public const TYPE_COMPANY = 'company';

    public const TYPE_NONE = 'none';

    public const TYPES = [
        self::TYPE_PERSONAL,
        self::TYPE_COMPANY,
        self::TYPE_NONE,
    ];

    /**
     * Get all available signature options for a user.
     *
     * @param  array<string, mixed>|null  $companyInfo
     * @return array{
     *   personal: array{ type: string, label: string, html: string, text: string },
     *   company: array{ type: string, label: string, html: string, text: string },
     *   none: array{ type: string, label: string, html: string, text: string },
     * }
     */
    public function getSignaturesFor(User $user, ?array $companyInfo = null): array
    {
        $personal = $this->getPersonalSignature($user);
        $company = $this->getCompanySignature($companyInfo);

        $appConfigName = config('app.name');
        $defaultCompanyName = ($appConfigName && $appConfigName !== 'Laravel') ? $appConfigName : 'WAVE PLUS';

        return [
            self::TYPE_PERSONAL => [
                'type' => self::TYPE_PERSONAL,
                'label' => 'Personal Signature ('.$user->name.')',
                'html' => $personal['html'],
                'text' => $personal['text'],
            ],
            self::TYPE_COMPANY => [
                'type' => self::TYPE_COMPANY,
                'label' => 'Company Signature ('.($companyInfo['name'] ?? $defaultCompanyName).')',
                'html' => $company['html'],
                'text' => $company['text'],
            ],
            self::TYPE_NONE => [
                'type' => self::TYPE_NONE,
                'label' => 'No Signature',
                'html' => '',
                'text' => '',
            ],
        ];
    }

    /**
     * Generate personal signature for user from User and Employee record.
     *
     * @return array{ html: string, text: string }
     */
    public function getPersonalSignature(User $user): array
    {
        $user->loadMissing(['employee']);

        $name = $user->name;
        $email = $user->email;
        $roleName = $user->roles->first()?->name ?? 'Team Member';
        $companyName = config('app.name', 'WAVE PLUS');
        $phone = null;
        $department = null;

        if ($user->employee) {
            $emp = $user->employee;
            $firstName = is_array($emp->first_name) ? ($emp->first_name['en'] ?? reset($emp->first_name)) : $emp->first_name;
            $lastName = is_array($emp->last_name) ? ($emp->last_name['en'] ?? reset($emp->last_name)) : $emp->last_name;
            if ($firstName && $lastName) {
                $name = trim("{$firstName} {$lastName}");
            }
            $phone = $emp->work_phone;
            if ($emp->work_email) {
                $email = $emp->work_email;
            }
        }

        $titleLine = $department ? "{$roleName} &bull; {$department}" : $roleName;
        $phoneHtml = $phone ? " &bull; <span>Tel: {$phone}</span>" : '';
        $phoneText = $phone ? " | Tel: {$phone}" : '';

        $html = <<<HTML
<div class="wp-signature wp-signature-personal" style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #475569;">
    <p style="margin: 0; font-weight: 600; color: #1e293b; font-size: 14px;">{$name}</p>
    <p style="margin: 2px 0; color: #64748b;">{$titleLine}</p>
    <p style="margin: 2px 0; font-weight: 600; color: #2563eb;">{$companyName}</p>
    <p style="margin: 4px 0 0 0; color: #64748b;">
        <span>Email: <a href="mailto:{$email}" style="color: #2563eb; text-decoration: none;">{$email}</a></span>{$phoneHtml}
    </p>
</div>
HTML;

        $text = <<<TEXT

--
{$name}
{$roleName}
{$companyName}
Email: {$email}{$phoneText}
TEXT;

        return [
            'html' => $html,
            'text' => $text,
        ];
    }

    /**
     * Generate corporate company signature.
     *
     * @param  array<string, mixed>|null  $companyInfo
     * @return array{ html: string, text: string }
     */
    public function getCompanySignature(?array $companyInfo = null): array
    {
        $appConfigName = config('app.name');
        $defaultCompanyName = ($appConfigName && $appConfigName !== 'Laravel') ? $appConfigName : 'WAVE PLUS';

        $name = $companyInfo['name'] ?? $defaultCompanyName;
        $tagline = $companyInfo['tagline'] ?? 'Enterprise Resource Planning & Operations Platform';
        $website = $companyInfo['website'] ?? 'https://waveplus.xyz';
        $email = $companyInfo['email'] ?? 'contact@waveplus.xyz';
        $phone = $companyInfo['phone'] ?? '+216 71 000 000';

        $html = <<<HTML
<div class="wp-signature wp-signature-company" style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #475569;">
    <p style="margin: 0; font-weight: 700; color: #1e293b; font-size: 15px; letter-spacing: 0.5px;">{$name}</p>
    <p style="margin: 2px 0; color: #64748b; font-style: italic;">{$tagline}</p>
    <p style="margin: 4px 0 0 0; color: #64748b;">
        <span>Web: <a href="{$website}" style="color: #2563eb; text-decoration: none;">{$website}</a></span> &bull;
        <span>Email: <a href="mailto:{$email}" style="color: #2563eb; text-decoration: none;">{$email}</a></span> &bull;
        <span>Tel: {$phone}</span>
    </p>
</div>
HTML;

        $text = <<<TEXT

--
{$name}
{$tagline}
Web: {$website} | Email: {$email} | Tel: {$phone}
TEXT;

        return [
            'html' => $html,
            'text' => $text,
        ];
    }

    /**
     * Remove any existing WAVE PLUS signature markup from HTML and text bodies
     * to prevent duplicate signatures when changing templates or signature options.
     *
     * @return array{ html: string, text: string }
     */
    public function stripExistingSignature(string $bodyHtml, ?string $bodyText = null): array
    {
        // Remove <div class="wp-signature ...">...</div> including nested elements
        $cleanHtml = preg_replace('/<div\s+class="wp-signature[^"]*"[\s\S]*?<\/div>\s*<\/div>|<div\s+class="wp-signature[^"]*"[\s\S]*?<\/div>/i', '', $bodyHtml);
        $cleanHtml = trim($cleanHtml ?? $bodyHtml);

        $text = $bodyText ?? strip_tags($bodyHtml);
        // Strip trailing signature starting with "\n--\n"
        $cleanText = preg_replace('/\n*--\s*\n[\s\S]*$/', '', $text);
        $cleanText = trim($cleanText ?? $text);

        return [
            'html' => $cleanHtml,
            'text' => $cleanText,
        ];
    }

    /**
     * Append or replace chosen signature in HTML and plain text bodies without duplicates.
     *
     * @param  string  $type  ('personal' | 'company' | 'none')
     * @param  array<string, mixed>|null  $companyInfo
     * @return array{ body_html: string, body_text: string }
     */
    public function appendSignature(
        string $bodyHtml,
        ?string $bodyText,
        string $type,
        User $user,
        ?array $companyInfo = null
    ): array {
        // Always strip existing signature first to guarantee idempotency
        $stripped = $this->stripExistingSignature($bodyHtml, $bodyText);
        $cleanHtml = $stripped['html'];
        $cleanText = $stripped['text'];

        if ($type === self::TYPE_NONE) {
            return [
                'body_html' => $cleanHtml,
                'body_text' => $cleanText,
            ];
        }

        $sig = ($type === self::TYPE_COMPANY)
            ? $this->getCompanySignature($companyInfo)
            : $this->getPersonalSignature($user);

        $renderedHtml = $cleanHtml."\n".$sig['html'];
        $renderedText = $cleanText."\n".$sig['text'];

        return [
            'body_html' => $renderedHtml,
            'body_text' => $renderedText,
        ];
    }
}
