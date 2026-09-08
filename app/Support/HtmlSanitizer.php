<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist HTML sanitizer for the Custom-HTML section type (Section Builder
 * prompt §2 — explicitly flagged as the one type with real XSS exposure). This
 * is NOT a naive raw-render: we parse the markup, drop any tag not on the
 * allowlist, strip every attribute not on the per-tag allowlist, reject
 * javascript:/data: (except image data) URIs, and remove all inline event
 * handlers. Anything we can't vouch for is discarded, not escaped-and-kept.
 */
class HtmlSanitizer
{
    /** Tags an admin may use in a marketing section. Everything else is unwrapped or dropped. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'span', 'div', 'section', 'article', 'header', 'footer',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'strong', 'b', 'em', 'i', 'u', 'small', 'sub', 'sup', 'mark',
        'a', 'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
    ];

    /** Attributes allowed per tag (plus the global set below). */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
    ];

    /** Attributes allowed on any tag. `style` is deliberately excluded (CSS injection surface). */
    private const GLOBAL_ATTRS = ['class', 'id'];

    /** Tags whose entire subtree is removed (never just unwrapped). */
    private const FORBIDDEN_SUBTREES = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'link', 'meta', 'base', 'svg', 'math',
        'noscript', 'template',
    ];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);

        // Wrap so DOMDocument doesn't inject <html>/<body> we then have to strip,
        // and force UTF-8 handling.
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__nx_root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $loaded) {
            return '';
        }

        $root = $dom->getElementById('__nx_root');
        if (! $root) {
            return '';
        }

        self::sanitizeChildren($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function sanitizeChildren(DOMNode $node): void
    {
        // Snapshot children first — we mutate the tree while iterating.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                self::sanitizeElement($child);
            }
        }
    }

    private static function sanitizeElement(DOMElement $el): void
    {
        $tag = strtolower($el->nodeName);

        // Whole subtree removed (script/style/etc.).
        if (in_array($tag, self::FORBIDDEN_SUBTREES, true)) {
            $el->parentNode?->removeChild($el);

            return;
        }

        // Unknown-but-harmless tag: keep its text/children, drop the tag itself.
        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            self::sanitizeChildren($el);
            self::unwrap($el);

            return;
        }

        self::scrubAttributes($el, $tag);
        self::sanitizeChildren($el);
    }

    private static function scrubAttributes(DOMElement $el, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRS, self::ALLOWED_ATTRS[$tag] ?? []);

        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $name = strtolower($attr->nodeName);

            // Every on* handler and anything not explicitly allowed is stripped.
            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            if (($name === 'href' || $name === 'src') && ! self::safeUrl($attr->nodeValue, $name)) {
                $el->removeAttribute($attr->nodeName);
            }
        }

        // Harden any surviving links so a target=_blank can't reach window.opener.
        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    /** Reject javascript:/vbscript:/data: (data: allowed only for inline images). */
    private static function safeUrl(?string $url, string $attr): bool
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }

        // Relative, anchor, protocol-relative and mailto/tel are fine.
        if (preg_match('#^(/|\#|\?|\.|mailto:|tel:)#i', $url) || preg_match('#^https?://#i', $url) || str_starts_with($url, '//')) {
            return true;
        }

        // Inline images may be data: URIs; nothing else may be.
        if ($attr === 'src' && preg_match('#^data:image/(png|jpe?g|gif|webp|svg\+xml);base64,#i', $url)) {
            // Disallow SVG data URIs (script vector) even though images are allowed.
            return ! str_contains(strtolower($url), 'svg');
        }

        // Anything with a scheme we didn't allow (javascript:, vbscript:, data:text, …) is rejected.
        return ! preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url);
    }

    private static function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;
        if (! $parent) {
            return;
        }
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }
}
