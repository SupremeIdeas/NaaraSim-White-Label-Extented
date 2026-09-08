<?php

namespace App\Console\Commands;

use App\Support\ProviderKeys;
use Illuminate\Console\Command;

/**
 * Dev-time env-sync check (Config/Cron fix pass §2b). Same rule the
 * EnvExampleSyncTest enforces in CI, but as a fast local command a developer can
 * run before opening a PR:
 *
 *   php artisan env:check-example
 *
 * It flags every credential-shaped key read in config/services.php WITHOUT a
 * fallback (env('KEY'), no default) that is documented on NEITHER surface an
 * operator configures — .env.example OR the Admin → API Keys schema
 * (ProviderKeys::schema()). That's the "wired a new provider into code but never
 * added its env slot" drift this whole pass exists to stop.
 *
 * Read-only and non-blocking: it never edits a file and is not part of the boot
 * path, so a normal composer install / deploy is unaffected.
 */
class EnvCheckExampleCommand extends Command
{
    protected $signature = 'env:check-example';

    protected $description = 'Verify every credential key in config/services.php is documented in .env.example or the admin keys schema';

    public function handle(): int
    {
        $src = (string) file_get_contents(config_path('services.php'));
        preg_match_all('/env\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]\s*\)/', $src, $m);
        $configKeys = array_values(array_unique($m[1]));

        $documented = array_merge($this->envExampleKeys(), $this->schemaEnvKeys());
        $undocumented = array_values(array_diff($configKeys, $documented));

        if ($undocumented === []) {
            $this->info('env:check-example — OK. All '.count($configKeys).' credential keys in config/services.php are documented.');

            return self::SUCCESS;
        }

        $this->error('env:check-example — '.count($undocumented).' key(s) read in config/services.php with no default are documented in NEITHER .env.example NOR the Admin → API Keys schema:');
        foreach ($undocumented as $key) {
            $this->line('  - '.$key);
        }
        $this->newLine();
        $this->line('Fix: add a blank slot to .env.example (or a field to ProviderKeys::schema()) so an operator can discover and set them.');

        return self::FAILURE;
    }

    /** @return list<string> */
    private function envExampleKeys(): array
    {
        $src = (string) file_get_contents(base_path('.env.example'));
        preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', $src, $m);

        return array_values(array_unique($m[1]));
    }

    /** @return list<string> */
    private function schemaEnvKeys(): array
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
