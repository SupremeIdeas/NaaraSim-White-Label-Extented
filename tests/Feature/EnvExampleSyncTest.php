<?php

namespace Tests\Feature;

use App\Support\ProviderKeys;
use Tests\TestCase;

/**
 * Env-sync guard (Config/Cron fix pass §2b). The drift bug it prevents: a new
 * provider gets wired into config/services.php with an env('KEY') call, but
 * nobody adds a slot for it to .env.example (or the admin API-keys screen), so
 * an operator has no way to discover the key exists — it silently reads null and
 * the provider is dead with no signal.
 *
 * Rule: every credential-shaped key in config/services.php that is read WITHOUT a
 * fallback — env('KEY') with no default — must be documented on one of the two
 * surfaces an operator actually configures:
 *   1. .env.example  (self-hosted / VPS — edit the file), OR
 *   2. ProviderKeys::schema()  (the Admin → API Keys UI — paste it in, stored in DB).
 *
 * Scope: config/services.php only. That's where NaaraSim's own integration
 * credentials live; the stock Laravel config files (database, cache, queue,
 * session, mail, logging, horizon, filesystems) carry framework tuning knobs
 * that Laravel documents itself and that degrade to sane built-in defaults.
 * Keys read WITH a default (env('X_BASE_URL', 'https://…')) are intentionally
 * skipped — they already have a working value out of the box.
 */
class EnvExampleSyncTest extends TestCase
{
    public function test_every_credential_key_in_services_config_is_documented(): void
    {
        $configKeys = $this->noDefaultEnvKeys(config_path('services.php'));
        $this->assertNotEmpty($configKeys, 'Sanity: services.php should reference some no-default env keys.');

        $documented = array_merge(
            $this->envExampleKeys(),
            $this->providerSchemaEnvKeys(),
        );

        $undocumented = array_values(array_diff($configKeys, $documented));

        $this->assertSame(
            [],
            $undocumented,
            'These credential keys are read in config/services.php with no default but are documented in NEITHER '
            .'.env.example NOR the Admin → API Keys schema (ProviderKeys::schema()). Add a blank slot to .env.example '
            ."(or a field to the schema) so an operator can find and set them:\n  - ".implode("\n  - ", $undocumented)
        );
    }

    /**
     * Keys referenced as env('KEY') with NO second argument (no fallback) in the
     * given config file.
     *
     * @return list<string>
     */
    private function noDefaultEnvKeys(string $path): array
    {
        $src = (string) file_get_contents($path);
        preg_match_all('/env\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]\s*\)/', $src, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * Every KEY= declared in .env.example.
     *
     * @return list<string>
     */
    private function envExampleKeys(): array
    {
        $src = (string) file_get_contents(base_path('.env.example'));
        preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', $src, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * Every env name wired into the Admin → API Keys schema.
     *
     * @return list<string>
     */
    private function providerSchemaEnvKeys(): array
    {
        $keys = [];
        foreach (ProviderKeys::schema() as $group) {
            foreach ($group['fields'] ?? [] as $field) {
                if (! empty($field['env'])) {
                    $keys[] = $field['env'];
                }
            }
        }

        return array_values(array_unique($keys));
    }
}
