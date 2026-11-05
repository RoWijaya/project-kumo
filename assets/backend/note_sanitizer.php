<?php
declare(strict_types=1);

/**
 * Allow-list HTML sanitizer for Quill note content. Rich text remains supported,
 * while scripts, event handlers, unsafe URL schemes, and embedded active content
 * are removed before the stored note is rendered again.
 */
function kumo_sanitize_note_html(string $html): string {
    if ($html === '') return '';
    $html = substr($html, 0, 5000000);
    $allowedTags = ['div', 'p', 'br', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'h1', 'h2', 'h3', 'ol', 'ul', 'li', 'blockquote', 'pre', 'code', 'a', 'img'];

    if (class_exists(DOMDocument::class)) {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="kumo-note-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $dom->getElementById('kumo-note-root');
        if (!$root) return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $elements = [];
        foreach ($root->getElementsByTagName('*') as $element) $elements[] = $element;
        for ($i = count($elements) - 1; $i >= 0; $i--) {
            $element = $elements[$i];
            $tag = strtolower($element->tagName);
            if (!in_array($tag, $allowedTags, true)) {
                $parent = $element->parentNode;
                if ($parent) {
                    while ($element->firstChild) $parent->insertBefore($element->firstChild, $element);
                    $parent->removeChild($element);
                }
                continue;
            }
            $attrs = [];
            foreach ($element->attributes as $attribute) $attrs[] = $attribute->name;
            foreach ($attrs as $name) {
                $value = trim($element->getAttribute($name));
                $lowerName = strtolower($name);
                if ($lowerName === 'class') {
                    $classes = preg_split('/\s+/', $value) ?: [];
                    $safeClasses = array_values(array_filter($classes, static fn(string $class): bool => preg_match('/^(ql-[a-z0-9_-]+|poppins|lora|roboto-mono)$/i', $class) === 1));
                    if ($safeClasses) $element->setAttribute('class', implode(' ', $safeClasses));
                    else $element->removeAttribute($name);
                    continue;
                }
                if ($lowerName === 'style') {
                    $styles = [];
                    foreach (explode(';', $value) as $declaration) {
                        if (!str_contains($declaration, ':')) continue;
                        [$property, $styleValue] = array_map('trim', explode(':', $declaration, 2));
                        $property = strtolower($property);
                        if (!in_array($property, ['text-align', 'color', 'background-color', 'font-family', 'font-size'], true)) continue;
                        if (preg_match('/[<>\\{}]|url\s*\(|expression\s*\(/i', $styleValue)) continue;
                        $styles[] = $property . ': ' . $styleValue;
                    }
                    if ($styles) $element->setAttribute('style', implode('; ', $styles));
                    else $element->removeAttribute($name);
                    continue;
                }
                if (in_array($lowerName, ['href', 'src'], true)) {
                    $allowed = false;
                    if ($lowerName === 'src' && $tag === 'img' && preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,[a-z0-9+\/=\r\n]+$/i', $value)) $allowed = true;
                    if (preg_match('/^(https?:|mailto:|tel:)/i', $value)) $allowed = true;
                    if (!$allowed) $element->removeAttribute($name);
                    continue;
                }
                if ($tag === 'img' && in_array($lowerName, ['alt', 'title', 'width', 'height'], true)) continue;
                if ($tag === 'a' && in_array($lowerName, ['title', 'target', 'rel'], true)) continue;
                $element->removeAttribute($name);
            }
            if ($tag === 'a' && $element->hasAttribute('href')) {
                $element->setAttribute('rel', 'noopener noreferrer');
                if ($element->getAttribute('target') === '_blank') $element->setAttribute('target', '_blank');
                else $element->removeAttribute('target');
            }
        }
        $output = '';
        foreach ($root->childNodes as $child) $output .= $dom->saveHTML($child);
        return $output;
    }

    // Conservative fallback for PHP builds without ext-dom.
    $html = preg_replace('#<(script|style|iframe|object|embed|svg|math|form|input|button)\b[^>]*>.*?</\1\s*>#is', '', $html) ?? '';
    $html = preg_replace('#<(script|style|iframe|object|embed|svg|math|form|input|button)\b[^>]*/?>#is', '', $html) ?? '';
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
    $html = preg_replace('/\s+(href|src)\s*=\s*(["\'])\s*(javascript|vbscript|data:text\/html):.*?\2/i', '', $html) ?? '';
    return $html;
}
