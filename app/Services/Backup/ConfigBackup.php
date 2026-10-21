<?php

namespace App\Services\Backup;

use App\Models\Setting;
use App\Support\Auditor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Portable configuration backup: everything the owner configured in the admin (settings, content, themes, menus, plans) in ONE passphrase-encrypted file
 * that restores on a fresh install even though that install has a different APP_KEY.
 *
 * Why this exists next to the database backup: every settings value is encrypted with the install's APP_KEY, so a raw copy of the table is unreadable
 * anywhere else. This export decrypts, packs the values into an envelope sealed with the admin's passphrase (AES-256-GCM, PBKDF2), and the restore
 * re-encrypts them with the target's own key.
 *
 * Deliberately NOT in the file: the Admin > API Keys store (`providers.keys`, the critical provider/payment secrets), users, wallets, orders, messages
 * and any other customer or money data. Those belong to the full database backup.
 */
class ConfigBackup
{
    public const FORMAT = 'naara-config';

    public const VERSION = 1;

    /** Setting keys that are never exported and never imported, even from a hand-edited file. */
    public const SKIP_SETTINGS = ['providers.keys'];

    /** Parents before children. Restore replaces these tables wholesale, keeping ids so relations survive. */
    public const TABLES = [
        'theme_presets', 'nav_slots', 'nav_item_overrides', 'page_sections', 'page_section_versions', 'custom_pages', 'posts',
        'faq_categories', 'faq_items', 'coupons', 'banners', 'mail_template_overrides', 'user_guides', 'changelog_entries',
        'home_videos', 'esim_country_images', 'esim_region_images', 'more_apps', 'esim_plans', 'esim_compatible_devices', 'numbers_bento_cards',
        'notification_tones', 'brain_prompts', 'gif_categories', 'gif_library_items', 'master_marketing_video_sections', 'brand_subscription_plans',
        'payout_corridors', 'gift_card_products', 'gift_card_brand_logos',
    ];

    /** Columns that point at a user row. Users are not in the file, so on another install those ids mean nothing: they are cleared rather than left dangling. */
    private const USER_REFERENCES = ['author_id', 'published_by', 'created_by', 'updated_by', 'uploaded_by'];

    private const PBKDF2_ROUNDS = 200000;

    /** @return list<string> the allow-listed tables that exist in this install */
    public static function tables(): array
    {
        return array_values(array_filter(self::TABLES, fn ($t) => Schema::hasTable($t)));
    }

    /** The sealed file contents. */
    public function export(string $passphrase): string
    {
        $this->assertPassphrase($passphrase);

        $settings = Setting::query()->whereNotIn('key', self::SKIP_SETTINGS)->orderBy('key')->get()->map(fn (Setting $s) => [
            'key' => $s->key,
            'value' => $s->value['value'] ?? null,
            'group' => $s->group,
            'description' => $s->description,
            'is_public' => (bool) $s->is_public,
        ])->all();

        $tables = [];
        foreach (self::tables() as $t) {
            $tables[$t] = DB::table($t)->get()->map(fn ($r) => (array) $r)->all();
        }

        $payload = json_encode([
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'source' => config('app.url'),
            'skipped' => self::SKIP_SETTINGS,
            'settings' => $settings,
            'tables' => $tables,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->seal($payload, $passphrase);
    }

    /**
     * What a restore would do, without writing anything.
     *
     * @return array{exported_at:string, source:?string, settings:array{new:int,changed:int,same:int}, tables:array<string,array{current:int,incoming:int}>, skipped:list<string>}
     */
    public function preview(string $file, string $passphrase): array
    {
        $data = $this->open($file, $passphrase);

        $new = $changed = $same = 0;
        foreach ($this->importableSettings($data) as $row) {
            $current = Setting::query()->where('key', $row['key'])->first();
            if ($current === null) {
                $new++;
            } elseif (($current->value['value'] ?? null) === $row['value']) {
                $same++;
            } else {
                $changed++;
            }
        }

        $tables = [];
        foreach ($this->importableTables($data) as $t => $rows) {
            $tables[$t] = ['current' => DB::table($t)->count(), 'incoming' => count($rows)];
        }

        return [
            'exported_at' => (string) ($data['exported_at'] ?? ''),
            'source' => $data['source'] ?? null,
            'settings' => ['new' => $new, 'changed' => $changed, 'same' => $same],
            'tables' => $tables,
            'skipped' => self::SKIP_SETTINGS,
        ];
    }

    /**
     * Write the file's settings and tables into this install, all or nothing. Settings are upserted (nothing this install has that the file lacks is
     * removed); the allow-listed content tables are replaced so the site matches the backup.
     *
     * @return array{settings:int, tables:array<string,int>}
     */
    public function restore(string $file, string $passphrase): array
    {
        $data = $this->open($file, $passphrase);
        $settings = $this->importableSettings($data);
        $tables = $this->importableTables($data);

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($settings, $tables) {
                foreach ($settings as $row) {
                    Setting::setValue($row['key'], $row['value'], $row['group'] ?? null, $row['description'] ?? null, (bool) ($row['is_public'] ?? false));
                }
                foreach ($tables as $t => $rows) {
                    $columns = Schema::getColumnListing($t);
                    DB::table($t)->delete();
                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table($t)->insert(array_map(fn ($r) => $this->shape($r, $columns), $chunk));
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        foreach ($settings as $row) {
            Cache::forget('setting:'.$row['key']);
        }
        try {
            Artisan::call('optimize:clear');
        } catch (\Throwable) {
            // a read-only host can't clear every cache; the restore itself is already committed
        }

        $result = ['settings' => count($settings), 'tables' => array_map('count', $tables)];
        Auditor::log('backup.config_restored', null, null, $result);

        return $result;
    }

    /** @return list<array<string,mixed>> */
    private function importableSettings(array $data): array
    {
        return array_values(array_filter((array) ($data['settings'] ?? []), fn ($r) => is_array($r) && isset($r['key']) && is_string($r['key']) && $r['key'] !== '' && ! in_array($r['key'], self::SKIP_SETTINGS, true)));
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function importableTables(array $data): array
    {
        $allowed = self::tables();
        $out = [];
        foreach ((array) ($data['tables'] ?? []) as $t => $rows) {
            if (in_array($t, $allowed, true) && is_array($rows)) {
                $out[$t] = array_values(array_filter($rows, 'is_array'));
            }
        }

        return $out;
    }

    /** Keep only columns this install has, and store arrays as JSON, so a backup from a slightly different version still restores. */
    private function shape(array $row, array $columns): array
    {
        $row = array_intersect_key($row, array_flip($columns));
        foreach (self::USER_REFERENCES as $c) {
            if (array_key_exists($c, $row)) {
                $row[$c] = null;
            }
        }

        return array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $row);
    }

    private function assertPassphrase(string $p): void
    {
        if (mb_strlen($p) < 8) {
            throw new RuntimeException('Use a passphrase of at least 8 characters. You need it again to restore.');
        }
    }

    private function seal(string $payload, string $passphrase): string
    {
        $salt = random_bytes(16);
        $iv = random_bytes(12);
        $key = hash_pbkdf2('sha256', $passphrase, $salt, self::PBKDF2_ROUNDS, 32, true);
        $tag = '';
        $cipher = openssl_encrypt(gzencode($payload, 9), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Could not encrypt the backup.');
        }

        return json_encode([
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'kdf' => 'pbkdf2-sha256',
            'rounds' => self::PBKDF2_ROUNDS,
            'salt' => base64_encode($salt),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipher),
        ], JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string,mixed> */
    private function open(string $file, string $passphrase): array
    {
        $env = json_decode($file, true);
        if (! is_array($env) || ($env['format'] ?? null) !== self::FORMAT || ! isset($env['data'], $env['salt'], $env['iv'], $env['tag'])) {
            throw new RuntimeException('This is not a Naara configuration backup.');
        }
        if ((int) ($env['version'] ?? 0) > self::VERSION) {
            throw new RuntimeException('This backup was made by a newer version. Update this install first.');
        }
        $rounds = max(1000, min(1000000, (int) ($env['rounds'] ?? self::PBKDF2_ROUNDS)));
        $key = hash_pbkdf2('sha256', $passphrase, (string) base64_decode($env['salt']), $rounds, 32, true);
        $plain = openssl_decrypt((string) base64_decode($env['data']), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, (string) base64_decode($env['iv']), (string) base64_decode($env['tag']));
        if ($plain === false) {
            throw new RuntimeException('Wrong passphrase, or the file was changed.');
        }
        $json = @gzdecode($plain);
        $data = $json === false ? null : json_decode($json, true);
        if (! is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('The backup could not be read.');
        }

        return $data;
    }
}
