<?php

namespace App\Support;

/**
 * Makes the white label say its own name everywhere a person can read it, without editing hundreds of views and language lines.
 *
 * The shipped copy still carries the source tokens ("Naara", "NaaraSim", "Supreme Ideas"). This swaps them for the licensee's brand
 * (Admin > Activation and branding) at the moment text leaves the app: HTML pages, Livewire updates, translated lines and emails.
 * Only text nodes and human-facing attributes are touched, never script, style, URLs, class names or ids, so nothing breaks.
 */
class BrandWordFilter
{
    /** Attributes whose value is read by a person. */
    private const ATTRS = ['alt', 'title', 'placeholder', 'aria-label', 'aria-description', 'content', 'label', 'data-title', 'data-label', 'data-name'];

    /** @return array{0: list<string>, 1: list<string>} */
    private static function map(): array
    {
        $company = BrandSettings::company();

        return [
            ['Supreme Ideas Agency', 'Supreme Ideas', 'NaaraSim', 'Naara'],
            [$company, $company, BrandSettings::name(), BrandSettings::word()],
        ];
    }

    /** A plain string (a translated line, an email subject, a JSON value). */
    public static function text(?string $s): string
    {
        $s = (string) $s;
        if ($s === '' || ! (str_contains($s, 'Naara') || str_contains($s, 'Supreme Ideas'))) {
            return $s;
        }
        [$from, $to] = self::map();

        return str_replace($from, $to, $s);
    }

    /** A whole HTML document or fragment. */
    public static function html(string $html): string
    {
        if ($html === '' || ! (str_contains($html, 'Naara') || str_contains($html, 'Supreme Ideas'))) {
            return $html;
        }
        [$from, $to] = self::map();

        // Split into tags and text; track script/style/textarea blocks that must stay byte for byte.
        $parts = preg_split('/(<!--.*?-->|<[^>]+>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        $raw = false;
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<' && ! str_starts_with($part, '<!--')) {
                if (preg_match('/^<\s*(script|style)\b/i', $part)) {
                    $raw = true;
                } elseif (preg_match('/^<\s*\/\s*(script|style)\b/i', $part)) {
                    $raw = false;
                }
                $out .= $raw ? $part : self::attrs($part, $from, $to);

                continue;
            }
            $out .= ($raw || str_starts_with($part, '<!--')) ? $part : str_replace($from, $to, $part);
        }

        return $out;
    }

    private static function attrs(string $tag, array $from, array $to): string
    {
        if (! (str_contains($tag, 'Naara') || str_contains($tag, 'Supreme Ideas'))) {
            return $tag;
        }

        return preg_replace_callback('/\b(' . implode('|', array_map('preg_quote', self::ATTRS)) . ')=("([^"]*)"|\'([^\']*)\')/i', function ($m) use ($from, $to) {
            $q = $m[2][0];
            $val = $m[3] !== '' ? $m[3] : ($m[4] ?? '');

            return $m[1].'='.$q.str_replace($from, $to, $val).$q;
        }, $tag) ?? $tag;
    }
}
