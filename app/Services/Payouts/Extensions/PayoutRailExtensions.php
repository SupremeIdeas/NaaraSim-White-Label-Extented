<?php

namespace App\Services\Payouts\Extensions;

use App\Services\Payouts\DeclaresCapabilities;
use App\Services\Payouts\PayoutGatewayInterface;
use App\Support\PayoutSettings;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Updater-delivered payout rails (Payoneer, Grey, Stripe Global Payouts, ...).
 *
 * A rail ships as a signed `.naaraupdate` package that drops ONE folder into `app/PayoutRails/<Slug>/`:
 *
 *     rail.json                    the manifest (see below)
 *     <Something>PayoutGateway.php implements PayoutGatewayInterface + DeclaresCapabilities
 *     ...                          any helper classes it needs (listed in `files` so they load even when
 *                                  composer's autoloader is optimised/authoritative on shared hosting)
 *
 * plus its own migration/lang files, applied by the normal updater pipeline. NOTHING in the core has to change
 * for the rail to appear: this loader finds the manifest, validates it, registers the gateway with the payout
 * engine and the webhook route.
 *
 * Safety rules (the whole point of this class):
 *   - A broken, mismatched or malicious-looking extension is SKIPPED and reported (`problems()`); it can never
 *     stop the application booting or take another rail down. Paystack/Flutterwave/Stripe/PayPal/Crypto/Manual
 *     are never affected.
 *   - An extension may not claim a core provider name.
 *   - The owner can switch any installed rail off without deleting files (Payout settings -> Rail extensions).
 *   - Until a rail is installed AND configured, the platform shows it as "coming soon"; it is never offered.
 *
 * rail.json:
 *   {
 *     "slug": "payoneer",                    folder name, lower-case
 *     "label": "Payoneer",
 *     "provider": "payoneer",                the gateway's name() and the corridor/account `provider` value
 *     "gateway": "App\\PayoutRails\\Payoneer\\PayoneerPayoutGateway",
 *     "version": "1.0.0",
 *     "min_core": "1.0.0",                   optional, informational
 *     "rail": "global",                      which rail family it belongs to (global)
 *     "files": ["PayoneerPayoutGateway.php"] relative to the folder; require_once'd before the class is resolved
 *   }
 */
class PayoutRailExtensions
{
    /** Names an extension may never register (the core rails). */
    public const CORE_PROVIDERS = ['paystack', 'flutterwave', 'stripe', 'paypal', 'cryptomus', 'manual_external'];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $loaded = null;

    /** @var list<array{slug: string, message: string}> */
    private static array $problems = [];

    public static function path(): string
    {
        return (string) config('payouts.extensions_path', app_path('PayoutRails'));
    }

    public static function flush(): void
    {
        self::$loaded = null;
        self::$problems = [];
    }

    /**
     * Every valid, enabled extension, keyed by provider name.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function manifests(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }
        self::$loaded = [];
        self::$problems = [];

        $dir = self::path();
        $files = is_dir($dir) ? (glob($dir.'/*/rail.json') ?: []) : [];
        sort($files);

        foreach ($files as $file) {
            $slug = basename(dirname($file));
            try {
                $manifest = self::validate($slug, $file);
                self::$loaded[$manifest['provider']] = $manifest;
            } catch (Throwable $e) {
                self::$problems[] = ['slug' => $slug, 'message' => $e->getMessage()];
                Log::warning("Payout rail extension '{$slug}' skipped: ".$e->getMessage());
            }
        }

        return self::$loaded;
    }

    /** @return list<array{slug: string, message: string}> */
    public static function problems(): array
    {
        self::manifests();

        return self::$problems;
    }

    /** @return array<string, mixed> */
    private static function validate(string $slug, string $file): array
    {
        $m = json_decode((string) file_get_contents($file), true);
        if (! is_array($m)) {
            throw new \RuntimeException('rail.json is not valid JSON.');
        }
        foreach (['slug', 'label', 'provider', 'gateway'] as $key) {
            if (! isset($m[$key]) || ! is_string($m[$key]) || $m[$key] === '') {
                throw new \RuntimeException("rail.json is missing '{$key}'.");
            }
        }
        if ($m['slug'] !== $slug || ! preg_match('/^[a-z][a-z0-9_]{1,30}$/', $m['provider'])) {
            throw new \RuntimeException('slug must match its folder and provider must be lower-case letters/digits/underscore.');
        }
        if (in_array($m['provider'], self::CORE_PROVIDERS, true)) {
            throw new \RuntimeException("'{$m['provider']}' is a core provider name and cannot be taken by an extension.");
        }
        if (in_array($m['slug'], PayoutSettings::disabledExtensions(), true) || in_array($m['provider'], PayoutSettings::disabledExtensions(), true)) {
            throw new \RuntimeException('Switched off by an admin.');
        }

        $base = dirname($file);
        foreach ((array) ($m['files'] ?? []) as $rel) {
            $path = realpath($base.'/'.$rel);
            if (! is_string($rel) || $path === false || ! str_starts_with($path, realpath($base).DIRECTORY_SEPARATOR) || ! str_ends_with($path, '.php')) {
                throw new \RuntimeException("Listed file '{$rel}' is missing or outside the rail folder.");
            }
            require_once $path;
        }

        $class = $m['gateway'];
        if (! class_exists($class)) {
            throw new \RuntimeException("Gateway class {$class} was not found.");
        }
        $ref = new \ReflectionClass($class);
        if (! $ref->implementsInterface(PayoutGatewayInterface::class) || ! $ref->implementsInterface(DeclaresCapabilities::class)) {
            throw new \RuntimeException('Gateway must implement PayoutGatewayInterface and DeclaresCapabilities.');
        }

        $m['rail'] = $m['rail'] ?? 'global';
        $m['version'] = (string) ($m['version'] ?? '0');

        return $m;
    }

    /**
     * Gateway instances for every valid extension. A gateway whose constructor throws, or whose name() disagrees with
     * its manifest, is dropped with a recorded problem.
     *
     * @return list<PayoutGatewayInterface>
     */
    public static function gateways(Container $app): array
    {
        $out = [];
        foreach (self::manifests() as $provider => $m) {
            try {
                $gateway = $app->make($m['gateway']);
                if ($gateway->name() !== $provider) {
                    throw new \RuntimeException("Gateway name '{$gateway->name()}' does not match manifest provider '{$provider}'.");
                }
                $app->singleton("payout.{$provider}", fn () => $gateway);
                $out[] = $gateway;
            } catch (Throwable $e) {
                unset(self::$loaded[$provider]);
                self::$problems[] = ['slug' => (string) $m['slug'], 'message' => $e->getMessage()];
                Log::warning("Payout rail extension '{$m['slug']}' could not be built: ".$e->getMessage());
            }
        }

        return $out;
    }

    public static function installed(?string $provider): bool
    {
        return $provider !== null && isset(self::manifests()[$provider]);
    }

    /** Providers the core ships itself. A global-rail provider outside this list needs an installed extension. */
    public static function isCore(?string $provider): bool
    {
        return $provider !== null && in_array($provider, self::CORE_PROVIDERS, true);
    }

    /** Can this provider be offered at all (core, or an installed extension)? */
    public static function offerable(?string $provider): bool
    {
        return self::isCore($provider) || self::installed($provider);
    }

    /**
     * Admin-facing status of every rail we plan to deliver.
     *
     * @return list<array{provider: string, label: string, status: string, note: string, version: ?string}>
     *   status: installed | coming_soon | problem | disabled
     */
    public static function catalogue(): array
    {
        $rows = [];
        $problems = collect(self::problems())->keyBy('slug');
        foreach ((array) config('payouts.planned_rails', []) as $provider => $info) {
            $m = self::manifests()[$provider] ?? null;
            $disabled = in_array($provider, PayoutSettings::disabledExtensions(), true);
            $problem = $problems->get($provider);
            $status = $m !== null ? 'installed' : ($disabled ? 'disabled' : ($problem !== null && $problem['message'] !== 'Switched off by an admin.' ? 'problem' : 'coming_soon'));
            $rows[] = [
                'provider' => $provider,
                'label' => (string) ($info['label'] ?? ucfirst($provider)),
                'status' => $status,
                'note' => $status === 'problem' ? (string) $problem['message'] : (string) ($info['note'] ?? ''),
                'version' => $m['version'] ?? null,
            ];
        }
        // Installed extensions that are not in the planned list still show up.
        foreach (self::manifests() as $provider => $m) {
            if (! isset(config('payouts.planned_rails', [])[$provider])) {
                $rows[] = ['provider' => $provider, 'label' => (string) $m['label'], 'status' => 'installed', 'note' => '', 'version' => $m['version']];
            }
        }

        return $rows;
    }
}
