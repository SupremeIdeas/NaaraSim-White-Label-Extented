<?php

/**
 * NaaraSim — install-integrity guard.
 *
 * Fails (non-zero exit) if any install-critical file has silently drifted out
 * of the repo, or a new migration timestamp collision has been introduced. This
 * exists because four install-blocking files once vanished from the repo without
 * anyone noticing until a real cPanel install failed (see docs/PLATFORM-STATE.md
 * and docs/CPANEL-INSTALL.md → "Why these files must never be deleted").
 *
 * Pure filesystem + tokenizer checks — no Laravel bootstrap, no database — so it
 * runs anywhere (CI, a pre-commit hook, or the release packager) in a fraction
 * of a second.
 *
 * Usage:  php bin/check-install-integrity.php
 */
$root = dirname(__DIR__);
$errors = [];
$checks = 0;

/** Assert a path exists. */
$mustExist = function (string $rel, string $why) use ($root, &$errors, &$checks): void {
    $checks++;
    if (! file_exists($root.'/'.$rel)) {
        $errors[] = "MISSING: {$rel} — {$why}";
    }
};

/** Assert a file contains a needle. */
$mustContain = function (string $rel, string $needle, string $why) use ($root, &$errors, &$checks): void {
    $checks++;
    $path = $root.'/'.$rel;
    if (! is_file($path)) {
        $errors[] = "MISSING: {$rel} — {$why}";

        return;
    }
    if (! str_contains((string) file_get_contents($path), $needle)) {
        $errors[] = "CONTENT: {$rel} no longer contains \"{$needle}\" — {$why}";
    }
};

// 1) Core view config — without it Laravel falls back to a compiled path wrapped
//    in realpath(), which is false on a fresh install where the dir is absent.
$mustExist('config/view.php', 'Blade compiled-view path breaks on a fresh install without it.');
$mustContain('config/view.php', "storage_path('framework/views')", 'the compiled path must resolve even before the dir exists (no realpath()).');

// 2) The DB-free installer layout + every install step wired to it. x-layouts.app
//    would paint the wizard with splash/preloader/manifest and hit the settings
//    table before it is migrated.
$mustExist('resources/views/components/layouts/install.blade.php', 'the install wizard cannot render without its layout component.');
foreach (['welcome', 'requirements', 'setup', 'done'] as $step) {
    $mustContain("resources/views/install/{$step}.blade.php", 'x-layouts.install',
        'install steps must use the DB-free install layout, not x-layouts.app.');
}

// 3) Runtime directories must survive a git export — each keeps a tracked
//    .gitignore (or .keep) placeholder, or the dir vanishes on clone.
foreach ([
    'bootstrap/cache',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
] as $dir) {
    $checks++;
    if (! file_exists("{$root}/{$dir}/.gitignore") && ! file_exists("{$root}/{$dir}/.keep")) {
        $errors[] = "MISSING: {$dir}/.gitignore (or .keep) — the runtime dir will not survive a clone/export.";
    }
}

// 4) No migration timestamp collisions. A tie forces alphabetical ordering, which
//    can run a child table before its parent (the partner_earnings → partners FK
//    failure). The 2026_07_12_141334 pair is a documented, order-independent
//    exception (two-factor columns + personal access tokens; no cross-FK).
$allowedCollisions = ['2026_07_12_141334'];
$stamps = [];
foreach (glob("{$root}/database/migrations/*.php") as $file) {
    if (preg_match('/(\d{4}_\d{2}_\d{2}_\d{6})_/', basename($file), $m)) {
        $stamps[$m[1]][] = basename($file);
    }
}
foreach ($stamps as $stamp => $files) {
    $checks++;
    if (count($files) > 1 && ! in_array($stamp, $allowedCollisions, true)) {
        $errors[] = "MIGRATION COLLISION: {$stamp} used by ".count($files).' migrations ('
            .implode(', ', $files).') — re-timestamp one so foreign-key order is deterministic.';
    }
}

// ---- Report ----
if ($errors === []) {
    fwrite(STDOUT, "install-integrity: OK ({$checks} checks passed)\n");
    exit(0);
}

fwrite(STDERR, "install-integrity: FAILED — install-critical drift detected:\n\n");
foreach ($errors as $e) {
    fwrite(STDERR, "  • {$e}\n");
}
fwrite(STDERR, "\nSee docs/PLATFORM-STATE.md for why these must never be removed.\n");
exit(1);
