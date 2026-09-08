<?php

namespace App\Support;

use App\Models\Contact;

/**
 * Parses a CSV or vCard (.vcf) file into [{name, phone}] rows for the contact
 * book (Live Voice — Part C). Deliberately forgiving — real exported address
 * books are messy — but never trusts the input: names are trimmed/clamped and
 * phones are normalised through Contact::normalizePhone. Rows with no usable
 * phone are dropped.
 */
class ContactImport
{
    /** Pick the right parser from a filename/extension. */
    public static function parse(string $contents, string $extension): array
    {
        return strtolower($extension) === 'vcf'
            ? self::fromVcard($contents)
            : self::fromCsv($contents);
    }

    /**
     * CSV: optional header row; columns are [name, phone] (extra columns
     * ignored). A single column is treated as phone-only (name falls back to the
     * number). Handles quoted fields via str_getcsv.
     *
     * @return array<int, array{name: string, phone: string}>
     */
    public static function fromCsv(string $contents): array
    {
        $rows = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = array_map('trim', str_getcsv($line));

            // Skip an obvious header row.
            if ($i === 0 && self::looksLikeHeader($cols)) {
                continue;
            }

            [$name, $phone] = count($cols) >= 2
                ? [$cols[0], $cols[1]]
                : ['', $cols[0]];

            $row = self::row($name, $phone);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return self::dedupe($rows);
    }

    /**
     * vCard: one or more BEGIN:VCARD…END:VCARD blocks. Uses FN (formatted name),
     * falling back to N, and the first TEL value per card.
     *
     * @return array<int, array{name: string, phone: string}>
     */
    public static function fromVcard(string $contents): array
    {
        $rows = [];
        if (preg_match_all('/BEGIN:VCARD(.*?)END:VCARD/is', $contents, $cards)) {
            foreach ($cards[1] as $card) {
                $name = self::vcardValue($card, 'FN') ?: self::vcardName($card);
                $phone = self::vcardTel($card);
                $row = self::row($name, (string) $phone);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }
        }

        return self::dedupe($rows);
    }

    /** Build a clean row, or null if there's no usable phone. */
    private static function row(string $name, string $phone): ?array
    {
        $phone = Contact::normalizePhone($phone);
        if ($phone === '' || $phone === '+') {
            return null;
        }
        $name = trim($name);
        if ($name === '') {
            $name = $phone;
        }

        return ['name' => mb_substr($name, 0, 120), 'phone' => mb_substr($phone, 0, 32)];
    }

    private static function looksLikeHeader(array $cols): bool
    {
        $joined = strtolower(implode(' ', $cols));

        return str_contains($joined, 'name') || str_contains($joined, 'phone') || str_contains($joined, 'number');
    }

    private static function vcardValue(string $card, string $field): ?string
    {
        // Matches "FN:Value" and "FN;CHARSET=…:Value".
        if (preg_match('/^'.$field.'[^:\r\n]*:(.+)$/im', $card, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private static function vcardName(string $card): string
    {
        $n = self::vcardValue($card, 'N');
        if ($n === null) {
            return '';
        }
        // N is "Family;Given;…" — render "Given Family".
        $parts = array_map('trim', explode(';', $n));
        $given = $parts[1] ?? '';
        $family = $parts[0] ?? '';

        return trim($given.' '.$family);
    }

    private static function vcardTel(string $card): ?string
    {
        if (preg_match('/^TEL[^:\r\n]*:(.+)$/im', $card, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /** De-dupe within a single import by normalised phone (last name wins). */
    private static function dedupe(array $rows): array
    {
        $byPhone = [];
        foreach ($rows as $row) {
            $byPhone[$row['phone']] = $row;
        }

        return array_values($byPhone);
    }
}
