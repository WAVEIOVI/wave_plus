<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

class EmailLayoutBuilder
{
    /**
     * Wrap message content into a professional WAVE PLUS branded HTML email layout.
     *
     * @param  array{
     *   title?: string,
     *   greeting?: string,
     *   lead?: string,
     *   content_html: string,
     *   document_summary?: array<string, string>,
     *   cta_button?: array{ label: string, url: string },
     *   language?: string,
     *   company?: array<string, string>
     * }  $params
     */
    public static function buildHtml(array $params): string
    {
        $lang = $params['language'] ?? 'fr';
        $isRtl = ($lang === 'ar');
        $dir = $isRtl ? 'rtl' : 'ltr';
        $textAlign = $isRtl ? 'right' : 'left';
        $fontFamily = $isRtl
            ? "'Segoe UI', Tahoma, Arial, sans-serif"
            : "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

        $company = $params['company'] ?? [
            'name' => 'WAVE PLUS',
            'email' => 'contact@waveplus.xyz',
            'phone' => '+216 71 000 000',
            'website' => 'https://waveplus.xyz',
            'address' => 'Tunis, Tunisia',
        ];

        $companyName = htmlspecialchars($company['name'] ?? 'WAVE PLUS', ENT_QUOTES, 'UTF-8');
        $companyEmail = htmlspecialchars($company['email'] ?? 'contact@waveplus.xyz', ENT_QUOTES, 'UTF-8');
        $companyPhone = htmlspecialchars($company['phone'] ?? '+216 71 000 000', ENT_QUOTES, 'UTF-8');
        $companyWebsite = htmlspecialchars($company['website'] ?? 'https://waveplus.xyz', ENT_QUOTES, 'UTF-8');

        $title = ! empty($params['title']) ? htmlspecialchars($params['title'], ENT_QUOTES, 'UTF-8') : '';
        $greeting = ! empty($params['greeting']) ? $params['greeting'] : '';
        $contentHtml = $params['content_html'] ?? '';

        // Optional document summary highlight card
        $summaryHtml = '';
        if (! empty($params['document_summary']) && is_array($params['document_summary'])) {
            $summaryRows = '';
            foreach ($params['document_summary'] as $label => $val) {
                if ($val !== '' && $val !== null) {
                    $sLabel = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
                    $sVal = htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
                    $summaryRows .= <<<ROW
                    <tr>
                        <td style="padding: 6px 12px; color: #64748b; font-size: 13px; font-weight: 500; border-bottom: 1px solid #f1f5f9; width: 40%; text-align: {$textAlign};">{$sLabel}</td>
                        <td style="padding: 6px 12px; color: #0f172a; font-size: 13px; font-weight: 600; border-bottom: 1px solid #f1f5f9; text-align: {$textAlign};">{$sVal}</td>
                    </tr>
ROW;
                }
            }

            if ($summaryRows !== '') {
                $summaryTitle = match ($lang) {
                    'ar' => 'ملخص المستند',
                    'en' => 'Document Summary',
                    default => 'Récapitulatif du Document',
                };

                $summaryHtml = <<<SUMMARY
                <div style="margin: 20px 0; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                    <div style="background-color: #f1f5f9; padding: 8px 14px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; text-align: {$textAlign};">
                        {$summaryTitle}
                    </div>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                        {$summaryRows}
                    </table>
                </div>
SUMMARY;
            }
        }

        // Optional CTA button
        $ctaHtml = '';
        if (! empty($params['cta_button']) && ! empty($params['cta_button']['label'])) {
            $ctaLabel = htmlspecialchars($params['cta_button']['label'], ENT_QUOTES, 'UTF-8');
            $ctaUrl = htmlspecialchars($params['cta_button']['url'] ?? '#', ENT_QUOTES, 'UTF-8');
            $ctaHtml = <<<CTA
            <div style="margin: 24px 0 16px 0; text-align: center;">
                <a href="{$ctaUrl}" style="display: inline-block; background-color: #0B5ED7; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; letter-spacing: 0.3px;">
                    {$ctaLabel}
                </a>
            </div>
CTA;
        }

        // Confidentiality text
        $disclaimer = match ($lang) {
            'ar' => 'هذه الرسالة ومرفقاتها سرية ومخصصة حصراً للشخص أو الجهة المرسل إليها. إذا تلقيت هذه الرسالة عن طريق الخطأ، يرجى إخطار المرسل فوراً وحذفها من جهازك.',
            'en' => 'This email and any attachments are confidential and intended solely for the use of the individual or entity to whom they are addressed. If you have received this message in error, please notify the sender immediately and delete it.',
            default => "Ce message et ses pièces jointes sont strictement confidentiels et établis à l'intention exclusive de ses destinataires. Si vous avez reçu ce message par erreur, merci d'en avertir immédiatement l'expéditeur et de le supprimer.",
        };

        $legalText = match ($lang) {
            'ar' => 'منصة WAVE PLUS لإدارة العمليات والمؤسسات &bull; جميع الحقوق محفوظة',
            'en' => 'WAVE PLUS ERP & Operations Platform &bull; All rights reserved',
            default => 'Plateforme WAVE PLUS ERP & Opérations &bull; Tous droits réservés',
        };

        return <<<HTML
<!DOCTYPE html>
<html lang="{$lang}" dir="{$dir}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: {$fontFamily}; -webkit-font-smoothing: antialiased; line-height: 1.6; color: #1e293b;" dir="{$dir}">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 620px; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <!-- Brand Header -->
                    <tr>
                        <td style="background-color: #0B5ED7; padding: 18px 24px; text-align: {$textAlign};">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="{$textAlign}">
                                        <span style="font-size: 20px; font-weight: 800; color: #ffffff; letter-spacing: 0.5px; font-family: {$fontFamily};">{$companyName}</span>
                                    </td>
                                    <td align="right" style="text-align: right;">
                                        <span style="font-size: 11px; color: #bfdbfe; font-weight: 500; text-transform: uppercase; letter-spacing: 1px;">Enterprise Communications</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Main Body Content -->
                    <tr>
                        <td style="padding: 28px 24px; text-align: {$textAlign}; font-size: 14px; line-height: 1.65; color: #1e293b;">
                            {$greeting}
                            <div style="margin-top: 12px;">
                                {$contentHtml}
                            </div>
                            {$summaryHtml}
                            {$ctaHtml}
                        </td>
                    </tr>

                    <!-- Footer Section -->
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 24px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: {$textAlign};">
                            <p style="margin: 0 0 6px 0; font-weight: 600; color: #334155;">{$companyName}</p>
                            <p style="margin: 0 0 10px 0; color: #64748b;">
                                <span>{$companyWebsite}</span> &bull; 
                                <span>{$companyEmail}</span> &bull; 
                                <span>{$companyPhone}</span>
                            </p>
                            <p style="margin: 0 0 6px 0; font-size: 11px; line-height: 1.4; color: #94a3b8;">
                                {$disclaimer}
                            </p>
                            <p style="margin: 0; font-size: 11px; color: #94a3b8;">
                                {$legalText}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Build formatted plain-text counterpart.
     *
     * @param  array{
     *   greeting?: string,
     *   content_text: string,
     *   document_summary?: array<string, string>,
     *   language?: string,
     *   company?: array<string, string>
     * }  $params
     */
    public static function buildText(array $params): string
    {
        $lang = $params['language'] ?? 'fr';
        $companyName = $params['company']['name'] ?? 'WAVE PLUS';
        $companyWebsite = $params['company']['website'] ?? 'https://waveplus.xyz';
        $companyEmail = $params['company']['email'] ?? 'contact@waveplus.xyz';
        $companyPhone = $params['company']['phone'] ?? '+216 71 000 000';

        $greeting = ! empty($params['greeting']) ? $params['greeting']."\n\n" : '';
        $body = trim($params['content_text'] ?? '');

        $summaryText = '';
        if (! empty($params['document_summary'])) {
            $summaryText = "\n\n----------------------------------------\n";
            $summaryText .= match ($lang) {
                'ar' => "ملخص المستند:\n",
                'en' => "DOCUMENT SUMMARY:\n",
                default => "RECAPITULATIF DU DOCUMENT :\n",
            };
            foreach ($params['document_summary'] as $k => $v) {
                if ($v !== '' && $v !== null) {
                    $summaryText .= "  * {$k}: {$v}\n";
                }
            }
            $summaryText .= "----------------------------------------\n";
        }

        $disclaimer = match ($lang) {
            'ar' => 'هذه الرسالة ومرفقاتها سرية ومخصصة حصراً للشخص أو الجهة المرسل إليها.',
            'en' => 'This email and any attachments are confidential and intended solely for the recipient.',
            default => 'Ce message et ses pièces jointes sont strictement confidentiels.',
        };

        return <<<TEXT
{$greeting}{$body}{$summaryText}

--
{$companyName}
Web: {$companyWebsite} | Email: {$companyEmail} | Tel: {$companyPhone}

{$disclaimer}
TEXT;
    }
}
