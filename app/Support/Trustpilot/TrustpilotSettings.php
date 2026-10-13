<?php

namespace App\Support\Trustpilot;

use App\Models\Setting;

/**
 * Trustpilot display layer settings (Trustpilot blueprint Phases 1, 5, 6). Everything is admin-set and OFF by default: with the master switch off nothing loads, no script,
 * no CSP change, no page. Rewards, invitations with reference ids and review sync are NOT part of this layer: they depend on the Trustpilot plan and on a policy sign-off
 * that has not happened, so the code for them does not exist yet. What ships is honest display: official TrustBox widgets only, the real score and the full unfiltered list.
 */
final class TrustpilotSettings
{
    /** Widget kinds the admin can paste a template id for (the id comes from Trustpilot's own TrustBox builder; we never ship a guess). */
    public const WIDGET_TYPES = [
        'carousel' => 'Carousel',
        'mini' => 'Mini',
        'micro_combo' => 'Micro combo',
        'micro_star' => 'Micro star',
        'list' => 'Review list',
    ];

    /** Where a widget may appear. Only surfaces that are really wired in the views are listed. */
    public const PLACEMENTS = [
        'home.carousel' => ['label' => 'Homepage (reviews section)', 'type' => 'carousel', 'height' => '140px'],
        'rewards.card' => ['label' => 'Rewards page', 'type' => 'mini', 'height' => '150px'],
        'auth.badge' => ['label' => 'Sign in and register pages', 'type' => 'micro_combo', 'height' => '24px'],
        'blog.end' => ['label' => 'End of every blog article', 'type' => 'mini', 'height' => '150px'],
        'page.list' => ['label' => 'Reviews page', 'type' => 'list', 'height' => '500px'],
    ];

    public const DEFAULT_CSP = [
        'script-src' => ['https://widget.trustpilot.com'],
        'style-src' => ['https://widget.trustpilot.com'],
        'frame-src' => ['https://widget.trustpilot.com', 'https://www.trustpilot.com'],
        'connect-src' => ['https://*.trustpilot.com'],
    ];

    private static function get(string $k, mixed $d = null): mixed
    {
        try {
            return Setting::getValue('trustpilot.'.$k, $d);
        } catch (\Throwable) {
            return $d;
        }
    }

    public static function put(string $k, mixed $v): void
    {
        Setting::setValue('trustpilot.'.$k, $v, 'trustpilot');
    }

    public static function enabled(): bool
    {
        return (bool) self::get('enabled', false);
    }

    public static function businessUnitId(): string
    {
        return trim((string) self::get('business_unit_id', ''));
    }

    /** The public Trustpilot profile address, e.g. https://www.trustpilot.com/review/example.com. Only an https trustpilot.com address is ever used. */
    public static function profileUrl(): string
    {
        $u = trim((string) self::get('profile_url', ''));
        if ($u === '' && class_exists(\App\Support\MasterMarketing\NaaraTrustpilotProfile::class)) {
            $u = \App\Support\MasterMarketing\NaaraTrustpilotProfile::URL;   // master only: a white label has no such class and starts empty
        }

        return preg_match('#^https://([a-z0-9-]+\.)?trustpilot\.com/[\w./%-]+$#i', $u) === 1 ? $u : '';
    }

    /** Trustpilot's own "write a review" form for the saved profile (the profile's /review/ path becomes /evaluate/). Null when no Trustpilot profile address is set. */
    public static function reviewFormUrl(): ?string
    {
        $u = self::profileUrl();
        if ($u === '') {
            return null;
        }
        $e = preg_replace('#/review/#', '/evaluate/', $u, 1);

        return is_string($e) && $e !== '' ? $e : $u;
    }

    public static function locale(): string
    {
        $l = (string) self::get('locale', 'en-US');

        return preg_match('/^[a-z]{2}-[A-Z]{2}$/', $l) === 1 ? $l : 'en-US';
    }

    public static function templateId(string $type): string
    {
        $t = trim((string) (((array) self::get('templates', []))[$type] ?? ''));

        return preg_match('/^[0-9a-f]{20,40}$/i', $t) === 1 ? $t : '';
    }

    public static function placementOn(string $key): bool
    {
        return isset(self::PLACEMENTS[$key]) && (bool) (((array) self::get('placements', []))[$key] ?? false);
    }

    /** Independent of Trustpilot: the platform's own testimonials stay on until an admin switches them off. */
    public static function defaultTestimonialsOn(): bool
    {
        return (bool) self::get('default_testimonials_on', true);
    }

    public static function dedicatedPageOn(): bool
    {
        return self::enabled() && (bool) self::get('page_enabled', false);
    }

    public static function pageTitle(): string
    {
        return trim((string) self::get('page_title', '')) ?: 'Customer reviews';
    }

    public static function pageIntro(): string
    {
        return trim((string) self::get('page_intro', '')) ?: 'What our customers say about us on Trustpilot. These are all reviews, good and bad, exactly as people wrote them.';
    }

    /** Load Trustpilot only after the visitor presses a button (the platform has no cookie-consent banner; this is the consent step for regions that need one). */
    public static function clickToLoad(): bool
    {
        return (bool) self::get('click_to_load', false);
    }

    /** @return array<string, list<string>> directive => extra origins, from the admin's list (defaults are a starting point to confirm in the browser console) */
    public static function cspHosts(): array
    {
        $saved = (array) self::get('csp_hosts', []);
        $out = [];
        foreach (self::DEFAULT_CSP as $dir => $defaults) {
            $list = isset($saved[$dir]) && is_array($saved[$dir]) ? $saved[$dir] : $defaults;
            $out[$dir] = array_values(array_filter(array_map('trim', $list), fn ($o) => preg_match('#^https://(\*\.)?[a-z0-9.-]+$#i', $o) === 1));
        }

        if (self::inviteActive() && ! in_array('https://invitejs.trustpilot.com', $out['script-src'], true)) {
            $out['script-src'][] = 'https://invitejs.trustpilot.com';
        }

        return $out;
    }

    /** Trustpilot's review-invitation script key (public in page source, like any client-side id). Admin-saved wins; master falls back to its own preset, a white label starts empty. */
    public static function integrationKey(): string
    {
        $k = trim((string) self::get('integration_key', ''));
        if ($k === '' && class_exists(\App\Support\MasterMarketing\NaaraTrustpilotProfile::class)) {
            $k = \App\Support\MasterMarketing\NaaraTrustpilotProfile::INTEGRATION_KEY;
        }

        return preg_match('/^[A-Za-z0-9]{8,40}$/', $k) === 1 ? $k : '';
    }

    /** The invitation script loads on every page only while the layer is on, a key exists, and visitors are not asked first (it would otherwise load before consent). */
    public static function inviteActive(): bool
    {
        return self::enabled() && self::integrationKey() !== '' && ! self::clickToLoad();
    }

    public static function inviteScriptSrc(): string
    {
        return 'https://invitejs.trustpilot.com/tp.min.js';
    }

    /** Is the layer actually producing anything that needs the script? */
    public static function active(): bool
    {
        return self::enabled() && self::businessUnitId() !== '';
    }

    public static function cspActive(): bool
    {
        return self::active() || self::inviteActive();
    }

    public static function scriptSrc(): string
    {
        return 'https://widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js';
    }
}
