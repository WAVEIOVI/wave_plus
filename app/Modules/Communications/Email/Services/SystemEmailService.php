<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Modules\Communications\Email\Models\EmailSenderProfile;
use App\Modules\Communications\Email\Models\EmailTemplate;
use Illuminate\Support\Facades\Log;

class SystemEmailService
{
    public function __construct(
        private readonly EmailSendingService $sender,
    ) {}

    /**
     * Send a transactional/system email using a named template.
     * Variables in subject and body are replaced with $variables.
     * Template placeholders use {{ variable_name }} syntax.
     *
     * @param  string  $templateSlug  — email_templates.slug
     * @param  array<array{email: string, name?: string}|string>  $to
     * @param  array<string, string>  $variables  — template variable substitutions
     * @param  array  $options  — additional payload options (cc, bcc, attachments, associations, created_by)
     */
    public function sendTemplate(
        string $templateSlug,
        array $to,
        array $variables = [],
        array $options = [],
    ): void {
        $template = EmailTemplate::findBySlug($templateSlug);

        if (! $template || ! $template->is_active) {
            Log::warning('SystemEmailService: template not found or inactive', ['slug' => $templateSlug]);

            return;
        }

        $profile = EmailSenderProfile::getSystemProfile() ?? EmailSenderProfile::getDefault();

        $subject = $this->interpolate($template->subject, $variables);
        $bodyHtml = $this->interpolate($template->body_html, $variables);
        $bodyText = $template->body_text
            ? $this->interpolate($template->body_text, $variables)
            : null;

        $payload = array_merge([
            'to' => $to,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'template_id' => $template->id,
            'sender_profile_id' => $profile?->id,
        ], $options);

        $this->sender->createAndQueue($payload);
    }

    /**
     * Send a direct HTML email (no template), queued.
     *
     * @param  array<array{email: string, name?: string}|string>  $to
     */
    public function sendDirect(
        array $to,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        array $options = [],
    ): void {
        $profile = EmailSenderProfile::getSystemProfile() ?? EmailSenderProfile::getDefault();

        $payload = array_merge([
            'to' => $to,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'sender_profile_id' => $profile?->id,
        ], $options);

        $this->sender->createAndQueue($payload);
    }

    private function interpolate(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{ '.$key.' }}', (string) $value, $template);
            $template = str_replace('{{'.$key.'}}', (string) $value, $template);
        }

        return $template;
    }
}
