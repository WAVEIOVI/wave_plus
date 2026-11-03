<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailTemplate;

class EmailTemplateRendererService
{
    public function __construct(
        private readonly TemplateVariableResolver $variableResolver,
        private readonly EmailSignatureService $signatureService,
        private readonly ?EmailSanitizationService $sanitizer = null,
    ) {}

    /**
     * Render a template preview with context variables and optional signature.
     * The original template is never modified.
     *
     * @param  array<string, mixed>  $context
     * @param  string  $signatureType  ('personal' | 'company' | 'none')
     * @return array{
     *   template_id: int,
     *   category: string,
     *   language: string,
     *   subject: string,
     *   body_html: string,
     *   body_text: string,
     *   signature_type: string,
     *   variables: array{
     *     required: array<int, string>,
     *     provided: array<int, string>,
     *     missing: array<int, string>,
     *   }
     * }
     */
    public function renderPreview(
        EmailTemplate $template,
        array $context,
        string $signatureType,
        User $user
    ): array {
        // 1. Resolve subject, body_html, and body_text safely using the variable resolver
        $renderedSubject = $this->variableResolver->resolve($template->subject, $context);
        $renderedHtml = $this->variableResolver->resolve($template->body_html, $context);
        $renderedText = $this->variableResolver->resolve($template->body_text ?? strip_tags($template->body_html), $context);

        // 2. Append chosen signature
        $withSignature = $this->signatureService->appendSignature(
            $renderedHtml,
            $renderedText,
            $signatureType,
            $user,
            $context['company'] ?? null
        );

        // 3. Validate variable usage
        $tokens = $this->variableResolver->extractTokens($template->subject, $template->body_html);
        $validation = $this->variableResolver->validateContext($tokens, $context);

        return [
            'template_id' => $template->id,
            'category' => (string) $template->category,
            'language' => (string) $template->language,
            'subject' => $renderedSubject,
            'body_html' => $this->sanitizer ? $this->sanitizer->sanitize($withSignature['body_html']) : $withSignature['body_html'],
            'body_text' => $withSignature['body_text'],
            'signature_type' => $signatureType,
            'variables' => $validation,
        ];
    }
}
