#!/usr/bin/env php
<?php

/**
 * NaaraSim dependency security gate (blueprint Section 30). Runs `composer
 * audit` and FAILS on any vulnerable dependency EXCEPT a small, documented
 * allow-list of advisories we have consciously accepted with a mitigation and a
 * tracking note. This is the "CI fails on a vulnerable dependency" control — any
 * NEW advisory (a package we add, or a new CVE) breaks the build.
 */

$accepted = [
    // Currently empty: the platform runs on Laravel 12 (security-supported), and
    // `composer audit` reports no advisories. The three Laravel 11 EOL advisories
    // that used to live here were resolved by the Laravel 12 upgrade. Any NEW
    // advisory now breaks the build until it is fixed or consciously accepted
    // here with a justification and mitigation note.
];

exec('composer audit --locked --no-scripts --format=json 2>/dev/null', $out);
$json = json_decode(implode("\n", $out), true) ?: [];
$advisories = $json['advisories'] ?? [];

$unaccepted = [];
$acceptedCount = 0;
foreach ($advisories as $package => $items) {
    foreach ($items as $item) {
        $id = $item['advisoryId'] ?? ($item['cve'] ?? 'unknown');
        if (array_key_exists($id, $accepted)) {
            $acceptedCount++;

            continue;
        }
        $unaccepted[] = "{$package}: {$id} — ".($item['title'] ?? '');
    }
}

if ($unaccepted !== []) {
    fwrite(STDERR, "\n[SECURITY] Vulnerable dependency(ies) found — build failed:\n  ".implode("\n  ", $unaccepted)."\n\n");
    fwrite(STDERR, "If one is genuinely accepted, add it to the allow-list in bin/security-audit.php with a justification.\n");
    exit(1);
}

echo "[SECURITY] Dependency audit passed. {$acceptedCount} known advisory(ies) accepted (see bin/security-audit.php).\n";
exit(0);
