<?php

namespace App\Console\Commands;

use App\Support\Appearance\AppearanceResolver;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * skins:verify — a read-only, production-safe proof that the skin engine is really present and wired in THIS install
 * (Prompt 22 Batch 2). Prints a pass/fail table and exits non-zero on any failure, so it can gate a deploy. It reports what it
 * finds; it never changes anything.
 */
class SkinsVerify extends Command
{
    protected $signature = 'skins:verify';

    protected $description = 'Prove the skin engine is installed and wired (tables, catalog, defaults, CSS selectors, layouts, Appearance page)';

    /** @var list<array{0:string,1:string,2:string}> */
    private array $rows = [];

    private bool $failed = false;

    public function handle(): int
    {
        $this->tablesAndMigrations();
        $skins = array_keys((array) config('appearance.skins', []));
        $this->check('Catalog size matches config/appearance.php', function () use ($skins) {
            $n = Schema::hasTable('appearance_presets') ? \App\Models\AppearancePreset::query()->where('kind', 'skin')->count() : 0;

            return [$n === count($skins), count($skins).' in config, '.$n.' seeded'];
        });
        $this->defaults();
        $this->cssSelectors($skins);
        $this->wiring();
        $this->appearancePage();

        $this->table(['Check', 'Result', 'Detail'], $this->rows);
        $this->line($this->failed ? '<error>skins:verify FAILED</error>' : '<info>skins:verify passed</info>');

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function tablesAndMigrations(): void
    {
        foreach (['appearance_presets', 'user_appearance'] as $table) {
            $this->check("Table {$table} exists", fn () => [Schema::hasTable($table), '']);
        }
        $this->check('Appearance migration recorded', function () {
            $n = Schema::hasTable('migrations') ? \Illuminate\Support\Facades\DB::table('migrations')->where('migration', 'like', '%create_appearance_tables%')->count() : 0;

            return [$n === 1, $n.' row(s)'];
        });
    }

    private function defaults(): void
    {
        foreach (['skin', 'accent'] as $kind) {
            $this->check("Exactly one default {$kind}", function () use ($kind) {
                if (! Schema::hasTable('appearance_presets')) {
                    return [false, 'no table'];
                }
                $n = \App\Models\AppearancePreset::query()->where('kind', $kind)->where('is_default', true)->count();

                return [$n === 1, $n.' default'];
            });
        }
    }

    /** @param  list<string>  $skins */
    private function cssSelectors(array $skins): void
    {
        $this->check('Built stylesheet found', function () use (&$css) {
            $manifest = public_path('build/manifest.json');
            if (! is_file($manifest)) {
                return [false, 'public/build/manifest.json missing — run npm run build'];
            }
            $entry = json_decode((string) file_get_contents($manifest), true)['resources/css/app.css']['file'] ?? null;
            $file = $entry ? public_path('build/'.$entry) : null;
            $css = ($file && is_file($file)) ? (string) file_get_contents($file) : null;

            return [$css !== null, (string) $entry];
        });
        $this->check('Every catalog skin has a selector in the built CSS', function () use ($skins, &$css) {
            if ($css === null) {
                return [false, 'no stylesheet to read'];
            }
            $missing = array_values(array_filter($skins, fn ($k) => ! str_contains($css, 'data-nx-skin='.$k) && ! str_contains($css, 'data-nx-skin="'.$k.'"')));

            return [$missing === [], $missing === [] ? count($skins).' of '.count($skins) : 'missing: '.implode(', ', $missing)];
        });
    }

    private function wiring(): void
    {
        $this->check('AppearanceResolver is present', fn () => [class_exists(AppearanceResolver::class), '']);
        foreach (['app', 'admin', 'customer'] as $layout) {
            $path = resource_path("views/components/layouts/{$layout}.blade.php");
            $this->check("Layout {$layout} carries the skin attributes", function () use ($path, $layout) {
                if (! is_file($path)) {
                    return [true, 'no such layout in this install'];
                }
                $src = (string) file_get_contents($path);
                // app.blade.php emits the attributes itself; admin/customer set $bodyClass, which makes app.blade.php emit them.
                $ok = $layout === 'app' ? str_contains($src, 'htmlAttributes') : (str_contains($src, 'bodyClass') || str_contains($src, 'skin'));

                return [$ok, ''];
            });
        }
    }

    private function appearancePage(): void
    {
        $this->check('Appearance route is registered', fn () => [Route::has('account.appearance'), '']);
        $this->check('Appearance page renders (read-only GET)', function () {
            $user = \App\Models\User::query()->orderBy('id')->first();
            if (! $user) {
                return [true, 'skipped: no user in this database'];
            }
            Auth::setUser($user);
            try {
                $res = app()->handle(Request::create(route('account.appearance', [], false), 'GET'));
            } catch (\Throwable $e) {
                return [false, class_basename($e).': '.$e->getMessage()];
            } finally {
                Auth::forgetGuards();
            }

            $body = (string) $res->getContent();

            return [$res->getStatusCode() === 200 && str_contains($body, 'data-nx-skin'), 'HTTP '.$res->getStatusCode()];
        });
    }

    /** @param  callable():array{0:bool,1:string}  $fn */
    private function check(string $name, callable $fn): void
    {
        try {
            [$ok, $detail] = $fn();
        } catch (\Throwable $e) {
            [$ok, $detail] = [false, $e->getMessage()];
        }
        $this->failed = $this->failed || ! $ok;
        $this->rows[] = [$name, $ok ? 'PASS' : 'FAIL', (string) $detail];
    }
}
