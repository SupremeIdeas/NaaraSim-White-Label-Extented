<?php

namespace App\Support;

use App\Models\MailTemplateOverride;
use Illuminate\Support\Facades\Cache;

/**
 * Email Studio template registry + override resolver (NAARA-BUILD-20 §3.1). The
 * transactional Blade templates stay the real defaults; admin edits are stored as
 * overrides and merged in here. Every accessor falls back to the Blade default, so
 * a template can never break because no override exists.
 *
 * A special 'global' key holds cross-template styling (accent colour).
 */
class MailTemplates
{
    public const GLOBAL_KEY = 'global';

    private const CACHE_KEY = 'mail.template.overrides';

    private const BRAND_ACCENT = '#0A6E6E';

    /** In-memory preview override (Email Studio live preview of unsaved edits). */
    private static ?array $preview = null;

    /** Temporarily layer unsaved form fields over the stored override for a render. */
    public static function preview(?string $key, ?array $fields): void
    {
        self::$preview = ($key !== null) ? [$key => $fields] : null;
    }

    /**
     * The admin-editable transactional templates: the practical set an operator
     * actually tunes. Each carries its Blade view, default subject/heading, and
     * sample data for the live preview.
     *
     * @return array<string, array{label:string, view:string, subject:string, heading:string, sample:array<string,mixed>}>
     */
    public static function templates(): array
    {
        $app = config('app.name', 'NaaraSim');

        return [
            'verify' => [
                'label' => 'Email verification', 'view' => 'emails.verify',
                'subject' => 'Confirm your email', 'heading' => 'Welcome, Ada — one quick step',
                'sample' => ['name' => 'Ada', 'url' => 'https://example.com/verify/demo'],
            ],
            'welcome' => [
                'label' => 'Welcome', 'view' => 'emails.welcome',
                'subject' => 'Welcome to '.$app, 'heading' => 'Welcome to '.$app,
                'sample' => ['name' => 'Ada', 'url' => 'https://example.com/dashboard'],
            ],
            'reset' => [
                'label' => 'Password reset', 'view' => 'emails.reset',
                'subject' => 'Reset your password', 'heading' => 'Reset your password',
                'sample' => ['name' => 'Ada', 'url' => 'https://example.com/reset/demo', 'expires' => 60],
            ],
            'order-placed' => [
                'label' => 'Order confirmation', 'view' => 'emails.order-placed',
                'subject' => 'Your order is confirmed', 'heading' => 'Order confirmed',
                'sample' => ['name' => 'Ada', 'product' => 'esim', 'itemName' => 'Nigeria 5GB / 30 days', 'amount' => 12.50, 'currency' => 'USD', 'url' => 'https://example.com/dashboard'],
            ],
            'top-up' => [
                'label' => 'Wallet top-up', 'view' => 'emails.top-up',
                'subject' => 'Your wallet has been topped up', 'heading' => 'Wallet topped up',
                'sample' => ['name' => 'Ada', 'amount' => 20.00, 'currency' => 'USD', 'newBalance' => 32.50, 'gateway' => 'Paystack', 'url' => 'https://example.com/wallet'],
            ],
            'refund' => [
                'label' => 'Refund', 'view' => 'emails.refund',
                'subject' => 'Your refund has been processed', 'heading' => 'Refund processed',
                'sample' => ['name' => 'Ada', 'amount' => 5.00, 'currency' => 'USD', 'reason' => 'Order could not be fulfilled', 'url' => 'https://example.com/wallet'],
            ],
        ];
    }

    public static function isEditable(string $key): bool
    {
        return $key === self::GLOBAL_KEY || array_key_exists($key, self::templates());
    }

    /** @return array<string, array<string, mixed>> key => override fields */
    private static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return MailTemplateOverride::all()
                    ->keyBy('template_key')
                    ->map(fn ($o) => $o->only(['subject', 'heading', 'intro', 'button_text', 'accent_color']))
                    ->toArray();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /** @return array<string, mixed> */
    public static function override(string $key): array
    {
        $base = self::map()[$key] ?? [];
        if (self::$preview !== null && isset(self::$preview[$key])) {
            $base = array_merge($base, array_filter(self::$preview[$key], fn ($v) => $v !== null && $v !== ''));
        }

        return $base;
    }

    public static function subject(string $key, string $default): string
    {
        return filled(self::override($key)['subject'] ?? null) ? self::override($key)['subject'] : $default;
    }

    public static function heading(string $key, ?string $default): ?string
    {
        return filled(self::override($key)['heading'] ?? null) ? self::override($key)['heading'] : $default;
    }

    /** An optional admin lead paragraph (plain text) prepended to the body, or null. */
    public static function intro(string $key): ?string
    {
        $v = self::override($key)['intro'] ?? null;

        return filled($v) ? $v : null;
    }

    public static function buttonText(string $key, string $default): string
    {
        return filled(self::override($key)['button_text'] ?? null) ? self::override($key)['button_text'] : $default;
    }

    /** Accent colour: per-template → global → brand teal. Always a safe hex. */
    public static function accent(string $key): string
    {
        $c = self::override($key)['accent_color'] ?? null;
        if (self::validHex($c)) {
            return $c;
        }
        $g = self::override(self::GLOBAL_KEY)['accent_color'] ?? null;

        return self::validHex($g) ? $g : self::BRAND_ACCENT;
    }

    public static function save(string $key, array $fields): void
    {
        if (! self::isEditable($key)) {
            return;
        }
        $clean = [];
        foreach (['subject', 'heading', 'intro', 'button_text', 'accent_color'] as $f) {
            $v = $fields[$f] ?? null;
            $clean[$f] = is_string($v) ? (trim($v) ?: null) : null;
        }
        if (isset($clean['accent_color']) && ! self::validHex($clean['accent_color'])) {
            $clean['accent_color'] = null;
        }

        MailTemplateOverride::updateOrCreate(['template_key' => $key], $clean);
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function validHex(?string $c): bool
    {
        return is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c) === 1;
    }
}
