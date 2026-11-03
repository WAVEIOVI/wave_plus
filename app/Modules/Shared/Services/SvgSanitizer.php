<?php

declare(strict_types=1);

namespace App\Modules\Shared\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Http\UploadedFile;

class SvgSanitizer
{
    /**
     * Tags that must be removed entirely from SVGs.
     */
    protected const DANGEROUS_TAGS = [
        'script',
        'foreignobject',
        'iframe',
        'embed',
        'object',
        'applet',
        'meta',
        'link',
    ];

    /**
     * Protocols that must be removed from URL-bearing attributes.
     */
    protected const DANGEROUS_PROTOCOLS = [
        'javascript:',
        'data:text/html',
        'data:text/xml',
        'data:image/svg+xml',
        'data:application/xml',
        'vbscript:',
    ];

    /**
     * Determine if an uploaded file is an SVG.
     */
    public static function isSvg(UploadedFile|string $file): bool
    {
        if ($file instanceof UploadedFile) {
            $mime = $file->getMimeType();
            $ext = strtolower($file->getClientOriginalExtension());
            if ($mime === 'image/svg+xml' || $mime === 'image/svg' || $ext === 'svg') {
                return true;
            }

            $path = $file->getRealPath();
        } else {
            $path = $file;
        }

        if (! is_string($path) || ! file_exists($path) || ! is_readable($path)) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        if ($mime === 'image/svg+xml' || $mime === 'image/svg') {
            return true;
        }

        // Check header content
        $sample = @file_get_contents($path, false, null, 0, 1024);
        if ($sample !== false && (str_contains($sample, '<svg') || str_contains($sample, '<?xml'))) {
            return true;
        }

        return false;
    }

    /**
     * Sanitize an uploaded SVG file in place.
     *
     * @throws \InvalidArgumentException If the SVG contains active exploits or is malformed.
     */
    public static function sanitizeUploadedFile(UploadedFile $file): void
    {
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            return;
        }

        $content = file_get_contents($realPath);
        if ($content === false) {
            throw new \InvalidArgumentException('Unable to read uploaded SVG file.');
        }

        $sanitized = static::sanitize($content);

        file_put_contents($realPath, $sanitized);
    }

    /**
     * Sanitize raw SVG XML string.
     *
     * @throws \InvalidArgumentException
     */
    public static function sanitize(string $svg): string
    {
        // 1. Check for XXE (external entity injections)
        if (preg_match('/<!ENTITY\s+/i', $svg) || preg_match('/<!DOCTYPE[^>]*\[/i', $svg)) {
            throw new \InvalidArgumentException('SVG files containing DOCTYPE entity declarations are forbidden.');
        }

        // 2. Load into DOMDocument with entity resolution disabled
        $dom = new DOMDocument;
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = true;

        $previousEntityLoader = libxml_disable_entity_loader(true);
        $internalErrors = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOCDATA);
            if (! $loaded) {
                // Try wrapping if XML prolog caused issues
                throw new \InvalidArgumentException('Invalid SVG XML structure.');
            }
        } finally {
            libxml_use_internal_errors($internalErrors);
            libxml_disable_entity_loader($previousEntityLoader);
        }

        $root = $dom->documentElement;
        if (! $root || strtolower($root->tagName) !== 'svg') {
            throw new \InvalidArgumentException('Root element of SVG must be <svg>.');
        }

        // 3. Clean elements recursively
        static::cleanNode($root);

        $cleanSvg = $dom->saveXML($root);
        if (! $cleanSvg) {
            throw new \InvalidArgumentException('Failed to generate sanitized SVG XML.');
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".$cleanSvg;
    }

    /**
     * Clean a DOM node and its descendants.
     */
    protected static function cleanNode(DOMNode $node): void
    {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        /** @var DOMElement $node */
        $tagName = strtolower($node->tagName);

        // Remove dangerous tags
        if (in_array($tagName, static::DANGEROUS_TAGS, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        // Clean attributes
        if ($node->hasAttributes()) {
            $attributesToRemove = [];

            foreach ($node->attributes as $attr) {
                $name = strtolower($attr->nodeName ?: $attr->name);
                $value = trim($attr->nodeValue ?: $attr->value);

                // Remove on* event handlers (e.g. onload, onclick, onerror)
                if (str_starts_with($name, 'on') || str_starts_with(strtolower($attr->localName ?: ''), 'on')) {
                    $attributesToRemove[] = $attr;

                    continue;
                }

                // Check URL protocols in href, xlink:href, src, formaction, etc.
                if (str_contains($name, 'href') || $name === 'src' || $name === 'formaction' || ($attr->localName && str_contains(strtolower($attr->localName), 'href'))) {
                    $normalizedValue = strtolower(preg_replace('/\s+/', '', $value));
                    foreach (static::DANGEROUS_PROTOCOLS as $protocol) {
                        if (str_starts_with($normalizedValue, $protocol)) {
                            $attributesToRemove[] = $attr;
                            break;
                        }
                    }
                }
            }

            foreach ($attributesToRemove as $attrNode) {
                $node->removeAttributeNode($attrNode);
            }
        }

        // Clean child nodes
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            static::cleanNode($child);
        }
    }
}
