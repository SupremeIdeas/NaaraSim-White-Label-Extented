<?php

namespace App\Support\CookieConsent;

use App\Models\CookieConsentLog;
use App\Models\Setting;
use App\Support\Tracking;

/**
 * Cookie consent (generic platform feature). Opt-in for everything that is not strictly necessary: nothing in `functional`, `analytics` or `marketing` loads or sets a cookie
 * until the visitor says yes, "Reject all" sits beside "Accept all" on the first screen with equal weight, choices are granular and changeable at any time, every choice is
 * logged as proof, a changed policy asks everyone again, and the choice expires so people are asked again after the renewal period. One admin page controls all of it.
 */
class CookieConsent
{
    public const CATEGORIES = ['functional', 'analytics', 'marketing'];

    public const THEMES = ['skin' => 'Follow the site skin', 'navy' => 'Midnight navy', 'teal' => 'Deep teal', 'light' => 'Light', 'custom' => 'My own colours'];

    public const LAYOUTS = ['card' => 'Corner card', 'bar' => 'Full-width bar', 'modal' => 'Centre dialog'];

    private static function get(string $key, mixed $default): mixed
    {
        try {
            return Setting::getValue('cookies.'.$key, $default);
        } catch (\Throwable) {
            return $default;   // before install or on a database hiccup: fall back to the safe defaults, never break a page
        }
    }

    public static function cookieName(): string
    {
        return (string) config('cookie_consent.cookie', 'naara_consent');
    }

    /** The banner is on unless an admin turns it off (for a private deployment that needs no consent). */
    public static function enabled(): bool
    {
        return (bool) self::get('enabled', true);
    }

    public static function version(): int
    {
        return max(1, (int) self::get('policy_version', 1));
    }

    /** Ask every visitor again (the policy or the services changed). */
    public static function bumpVersion(): int
    {
        $v = self::version() + 1;
        Setting::setValue('cookies.policy_version', $v, 'cookies');

        return $v;
    }

    /** @return array<string, mixed> */
    public static function design(): array
    {
        $d = (array) config('cookie_consent.defaults');
        $custom = (array) self::get('custom', $d['custom']);
        $theme = (string) self::get('theme', $d['theme']);
        $layout = (string) self::get('layout', $d['layout']);

        return [
            'title' => self::text('title', $d['title'], 80), 'body' => self::text('body', $d['body'], 400),
            'accept' => self::text('accept', $d['accept'], 24), 'reject' => self::text('reject', $d['reject'], 24), 'customise' => self::text('customise', $d['customise'], 24),
            'layout' => array_key_exists($layout, self::LAYOUTS) ? $layout : $d['layout'],
            'position' => self::get('position', $d['position']) === 'left' ? 'left' : 'right',
            'theme' => array_key_exists($theme, self::THEMES) ? $theme : $d['theme'],
            'custom' => [
                'bg' => self::hex($custom['bg'] ?? null, $d['custom']['bg']), 'text' => self::hex($custom['text'] ?? null, $d['custom']['text']),
                'button' => self::hex($custom['button'] ?? null, $d['custom']['button']), 'button_text' => self::hex($custom['button_text'] ?? null, $d['custom']['button_text']),
            ],
            'renew_days' => max(30, min(395, (int) self::get('renew_days', $d['renew_days']))),
            'respect_gpc' => (bool) self::get('respect_gpc', $d['respect_gpc']),
            'show_reopen' => (bool) self::get('show_reopen', $d['show_reopen']),
        ];
    }

    public static function retentionMonths(): int
    {
        return max(6, min(60, (int) self::get('retention_months', config('cookie_consent.defaults.retention_months'))));
    }

    private static function text(string $key, string $default, int $max): string
    {
        $v = trim(strip_tags((string) self::get($key, $default)));

        return $v === '' ? $default : mb_substr($v, 0, $max);
    }

    private static function hex(mixed $v, string $default): string
    {
        return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) === 1 ? strtoupper($v) : $default;
    }

    /** The inline CSS variables for a fixed colour theme, or '' when the banner follows the site skin. Colours are validated hex only. */
    public static function cssVars(array $design): string
    {
        $c = match ($design['theme']) {
            'navy' => ['bg' => '#0D1B2A', 'text' => '#F1F5F9', 'button' => '#84CC16', 'button_text' => '#0D1B2A'],
            'teal' => ['bg' => '#A21CAF', 'text' => '#FFFFFF', 'button' => '#FFFFFF', 'button_text' => '#A21CAF'],
            'light' => ['bg' => '#FFFFFF', 'text' => '#0F172A', 'button' => '#A21CAF', 'button_text' => '#FFFFFF'],
            'custom' => $design['custom'],
            default => null,
        };

        return $c === null ? '' : sprintf('--ck-bg:%s;--ck-text:%s;--ck-btn:%s;--ck-btn-text:%s;', $c['bg'], $c['text'], $c['button'], $c['button_text']);
    }

    /**
     * The services this install can actually load right now, each with its category. A third party is only listed (and only ever loads) while its own setting is on.
     *
     * @return array<string, array{key:string,category:string,name:string,provider:string,purpose:string,cookies:list<array{0:string,1:string}>,privacy:?string}>
     */
    public static function services(): array
    {
        $out = [];
        foreach ((array) config('cookie_consent.services') as $key => [$cat, $name, $provider, $purpose, $cookies, $privacy]) {
            $live = match ($key) {
                'google_analytics' => Tracking::gaId() !== null,
                'meta_pixel' => Tracking::pixelId() !== null,
                'trustpilot' => class_exists(\App\Support\Trustpilot\TrustpilotSettings::class) && rescue(fn () => \App\Support\Trustpilot\TrustpilotSettings::enabled(), false, report: false),
                default => true,
            };
            if ($live) {
                $out[$key] = ['key' => $key, 'category' => $cat, 'name' => $name, 'provider' => $provider, 'purpose' => $purpose, 'cookies' => $cookies, 'privacy' => $privacy];
            }
        }

        return $out;
    }

    /** Categories that have at least one live service (a category nobody uses is not shown to visitors). @return list<string> */
    public static function categoriesInUse(): array
    {
        $used = array_unique(array_column(self::services(), 'category'));

        return array_values(array_filter(array_keys((array) config('cookie_consent.categories')), fn ($c) => $c === 'necessary' || in_array($c, $used, true)));
    }

    /** Does this install have anything optional to ask about? When not, the banner would only be noise. */
    public static function hasOptional(): bool
    {
        return array_diff(self::categoriesInUse(), ['necessary']) !== [];
    }

    public static function hashIp(?string $ip): ?string
    {
        return $ip === null || $ip === '' ? null : substr(hash_hmac('sha256', $ip, (string) config('app.key')), 0, 32);
    }

    /** @param array{functional?:bool,analytics?:bool,marketing?:bool} $choices */
    public static function record(string $consentId, string $action, array $choices, ?int $userId, ?string $ip, ?string $ua, ?string $path): CookieConsentLog
    {
        return CookieConsentLog::query()->create([
            'consent_id' => $consentId, 'user_id' => $userId, 'policy_version' => self::version(), 'action' => $action,
            'functional' => (bool) ($choices['functional'] ?? false), 'analytics' => (bool) ($choices['analytics'] ?? false), 'marketing' => (bool) ($choices['marketing'] ?? false),
            'ip_hash' => self::hashIp($ip), 'user_agent' => $ua !== null ? mb_substr($ua, 0, 200) : null, 'path' => $path !== null ? mb_substr($path, 0, 160) : null, 'created_at' => now(),
        ]);
    }

    /** Delete log rows older than the retention period. Returns how many went. */
    public static function prune(): int
    {
        return CookieConsentLog::query()->where('created_at', '<', now()->subMonths(self::retentionMonths()))->delete();
    }

    /** What the admin sees: choices in the last N days by action, and the share that allow each optional category. @return array<string, mixed> */
    public static function stats(int $days = 30): array
    {
        $rows = CookieConsentLog::query()->where('created_at', '>=', now()->subDays($days));
        $by = (clone $rows)->selectRaw('action, count(*) c')->groupBy('action')->pluck('c', 'action')->all();
        $total = array_sum($by);
        $share = fn (string $col) => $total === 0 ? 0 : (int) round((clone $rows)->whereNot('action', 'withdraw')->where($col, true)->count() / max(1, $total - (int) ($by['withdraw'] ?? 0)) * 100);

        return ['total' => $total, 'by' => $by, 'functional' => $share('functional'), 'analytics' => $share('analytics'), 'marketing' => $share('marketing')];
    }
}
