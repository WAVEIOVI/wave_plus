<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Centralized HTML sanitization service for incoming, outgoing, and preview email content.
 * Prevents XSS, unsafe schemes (javascript:, vbscript:, data:text/html), iframes, and script injections.
 */
class EmailSanitizationService
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowMediaSchemes(['http', 'https', 'data', 'cid'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowAttribute('class', '*')
            ->allowAttribute('style', '*')
            ->allowAttribute('id', '*')
            ->allowAttribute('target', 'a')
            ->allowAttribute('rel', 'a')
            ->allowAttribute('align', '*')
            ->allowAttribute('valign', '*')
            ->allowAttribute('width', '*')
            ->allowAttribute('height', '*')
            ->allowAttribute('cellpadding', 'table')
            ->allowAttribute('cellspacing', 'table')
            ->allowAttribute('border', 'table')
            ->allowAttribute('colspan', ['td', 'th'])
            ->allowAttribute('rowspan', ['td', 'th']);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    /**
     * Sanitize general HTML email body.
     */
    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Clean out dangerous style attributes before / after sanitizer
        $cleaned = $this->stripDangerousStyles($html);

        return $this->sanitizer->sanitize($cleaned);
    }

    /**
     * Sanitize outgoing email body before sending or queuing.
     */
    public function sanitizeOutgoing(?string $html): string
    {
        return $this->sanitize($html);
    }

    /**
     * Sanitize incoming email body received from IMAP.
     */
    public function sanitizeIncoming(?string $html): string
    {
        return $this->sanitize($html);
    }

    /**
     * Strips dangerous CSS constructs (expression, url(javascript:), behavior, @import) from style attributes.
     */
    private function stripDangerousStyles(string $html): string
    {
        return preg_replace_callback('/style\s*=\s*([\'"])(.*?)\1/si', function (array $matches) {
            $quote = $matches[1];
            $css = $matches[2];

            // Remove expressions, url with javascript/vbscript/data, behaviors, bindings, and @import
            $css = preg_replace('/expression\s*\(.*?\)/i', '', $css);
            $css = preg_replace('/url\s*\(\s*[\'"]?\s*(?:javascript|vbscript|data\s*:\s*text\/html).*?[\'"]?\s*\)/si', '', $css);
            $css = preg_replace('/-moz-binding\s*:[^;]+/i', '', $css);
            $css = preg_replace('/behavior\s*:[^;]+/i', '', $css);
            $css = preg_replace('/@import[^;]+/i', '', $css);

            return "style={$quote}{$css}{$quote}";
        }, $html) ?? $html;
    }
}
