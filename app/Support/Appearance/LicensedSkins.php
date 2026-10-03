<?php

namespace App\Support\Appearance;

use App\Models\AppearancePreset;
use App\Models\Setting;
use App\Support\Auditor;
use App\Support\FeatureEntitlements;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The CONSUMER side of the white-label skin allowance (Prompt 22). Master is the only authority for how many skins a licence unlocks;
 * this class stores that one number, lets the licensee choose which skins fill it, and exposes the ONE list the rest of the app may use.
 * There is deliberately no tier-to-count map, no override and no decision logic here: the number arrives in the entitlement payload
 * (`skins.allowance`) and is obeyed. See docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md.
 *
 *  - Not yet activated (no allowance ever received): ONE skin, the platform default. Fail closed to one, never open to all.
 *  - Allowance known, nothing chosen yet: still only the platform default until the licensee saves a selection.
 *  - The first selected skin is the licensee's default; the rest are the skins their end users may switch to.
 *  - Hidden is enforced on the server: AppearanceResolver::platform() (used by the resolver, UpdateUserAppearance and the Appearance
 *    pages) intersects with available(). A saved user skin outside the list resolves to the default and its row is never touched.
 *  - A failed refresh never wipes or invents anything: the last-known allowance and selection stay.
 *
 * Inert on the master platform (available() imposes no limit there).
 */
class LicensedSkins
{
    public const ALLOWANCE_KEY = 'white_label.skins.allowance';

    public const SELECTED_KEY = 'white_label.skins.selected';

    public const VERSION_KEY = 'white_label.skins.version';

    public const FALLBACK_ALLOWANCE = 1;

    /** Are the limits in force here? False on the master platform, which is never limited. */
    public static function enforced(): bool
    {
        return ! FeatureEntitlements::isMaster();
    }

    /** True once master has told this install how many skins it may use. */
    public static function activated(): bool
    {
        return self::storedAllowance() !== null;
    }

    public static function allowance(): int
    {
        return self::storedAllowance() ?? self::FALLBACK_ALLOWANCE;
    }

    /** Skins this install can offer at all: shipped (built) and in the catalogue. */
    public static function catalogue(): array
    {
        $built = (array) config('appearance.built', []);

        return array_values(array_filter(array_keys((array) config('appearance.skins', [])), fn ($k) => in_array($k, $built, true)));
    }

    /** The licensee's chosen skins in saved order (first = default), cleaned and trimmed to the allowance. Empty when nothing was chosen. */
    public static function chosen(): array
    {
        $stored = self::read(self::SELECTED_KEY, []);
        $clean = [];
        foreach (is_array($stored) ? $stored : [] as $key) {
            if (is_string($key) && in_array($key, self::catalogue(), true) && ! in_array($key, $clean, true)) {
                $clean[] = $key;
            }
        }

        return array_slice($clean, 0, self::allowance());
    }

    /** What end users may use. The ONLY list the rest of the app consults. */
    public static function available(): array
    {
        if (! self::enforced()) {
            return self::catalogue();
        }
        $chosen = self::chosen();

        return $chosen !== [] ? $chosen : [self::platformDefault()];
    }

    public static function default(): string
    {
        return self::available()[0];
    }

    /** Bumped on every allowance/selection change so every cached resolved appearance is dropped at once. */
    public static function version(): int
    {
        return (int) self::read(self::VERSION_KEY, 0);
    }

    /** @return array{ok: bool, errors: list<string>, keys: list<string>} */
    public static function validateSelection(array $keys): array
    {
        $errors = [];
        $keys = array_values(array_unique(array_filter($keys, 'is_string')));
        if ($keys === []) {
            $errors[] = 'Choose at least one skin.';
        }
        if (count($keys) > self::allowance()) {
            $errors[] = 'Your licence unlocks '.self::allowance().' skin(s); you chose '.count($keys).'.';
        }
        foreach ($keys as $k) {
            if (! in_array($k, self::catalogue(), true)) {
                $errors[] = "\"{$k}\" is not an available skin.";
            }
        }

        return ['ok' => $errors === [], 'errors' => $errors, 'keys' => $keys];
    }

    /** Persist the licensee's choice (first key = default), then make the presets table agree. */
    public static function saveSelection(array $keys, ?int $actorId = null): array
    {
        $check = self::validateSelection($keys);
        if (! $check['ok']) {
            return $check;
        }
        $before = self::chosen();
        Setting::setValue(self::SELECTED_KEY, $check['keys'], 'white_label');
        self::touch();
        self::syncPresets();
        Auditor::log('skins.selection_updated', null, null, ['before' => $before, 'after' => $check['keys'], 'default' => $check['keys'][0], 'allowance' => self::allowance(), 'by' => $actorId]);

        return $check;
    }

    /**
     * Store the allowance master sent. Ignored unless it is an integer of at least 1 (a malformed value keeps the last-known one).
     * A fall below the saved selection trims it to the first N, which always keeps the default, and writes an audit entry.
     */
    public static function storeAllowance(mixed $value): bool
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return false;
        }
        $n = (int) $value;
        if ($n < 1) {
            return false;
        }
        $previous = self::storedAllowance();
        if ($previous === $n) {
            return true;
        }
        $selected = self::read(self::SELECTED_KEY, []);
        Setting::setValue(self::ALLOWANCE_KEY, $n, 'white_label');
        if (is_array($selected) && count($selected) > $n) {
            Setting::setValue(self::SELECTED_KEY, array_slice($selected, 0, $n), 'white_label');
            Auditor::log('skins.selection_trimmed', null, null, ['allowance' => $n, 'kept' => array_slice($selected, 0, $n), 'dropped' => array_slice($selected, $n)]);
        }
        self::touch();
        self::syncPresets();

        return true;
    }

    /** Make appearance_presets agree with the licence: only available() skins are enabled, and the first is the default. */
    public static function syncPresets(): void
    {
        if (! self::enforced() || ! Schema::hasTable('appearance_presets')) {
            return;
        }
        $available = self::available();
        DB::transaction(function () use ($available) {
            AppearancePreset::query()->where('kind', AppearancePreset::SKIN)->get()->each(function (AppearancePreset $row) use ($available) {
                $row->forceFill(['enabled' => in_array($row->key, $available, true), 'is_default' => $row->key === $available[0]])->save();
            });
        });
        AppearanceResolver::forgetPlatform();
    }

    private static function platformDefault(): string
    {
        return (string) config('appearance.default_skin', 'surface');
    }

    private static function storedAllowance(): ?int
    {
        $v = self::read(self::ALLOWANCE_KEY, null);

        return is_numeric($v) && (int) $v >= 1 ? (int) $v : null;
    }

    private static function touch(): void
    {
        Setting::setValue(self::VERSION_KEY, self::version() + 1, 'white_label');
        Cache::forget('white_label.skins.local');
        AppearanceResolver::forgetPlatform();
    }

    private static function read(string $key, mixed $default): mixed
    {
        try {
            return Setting::getValue($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
