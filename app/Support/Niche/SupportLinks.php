<?php

namespace App\Support\Niche;

/**
 * Support / live-help links (blueprint Section 32). Builds the WhatsApp deep
 * link and exposes the support email. WhatsApp is hidden when unconfigured.
 */
class SupportLinks
{
    public static function whatsappNumber(): string
    {
        return preg_replace('/\D+/', '', (string) config('naara.support.whatsapp', ''));
    }

    public static function hasWhatsapp(): bool
    {
        return self::whatsappNumber() !== '';
    }

    public static function whatsappUrl(?string $context = null): ?string
    {
        if (! self::hasWhatsapp()) {
            return null;
        }

        $text = trim(config('naara.support.whatsapp_message', 'Hi NaaraSim').' '.($context ?? ''));

        return 'https://wa.me/'.self::whatsappNumber().'?text='.rawurlencode($text);
    }

    /** The support email: what the admin typed on the Contact page (Site Editor), else the SUPPORT_EMAIL setting. Never hard-coded in a page. */
    public static function email(): string
    {
        try {
            $typed = trim((string) (\App\Support\SiteContent::page('contact', false, false)['channels']['email_address'] ?? ''));
        } catch (\Throwable) {
            $typed = '';
        }

        if (filter_var($typed, FILTER_VALIDATE_EMAIL)) {
            return $typed;
        }
        $owner = \App\Support\BrandSettings::profile('support_email');
        if (filter_var($owner, FILTER_VALIDATE_EMAIL)) {
            return $owner;
        }
        $env = (string) config('naara.support.email', '');
        // Never the original platform's address: until the owner sets one, support@ their own domain.
        if (filter_var($env, FILTER_VALIDATE_EMAIL) && ! str_ends_with(strtolower($env), '@naarasim.com')) {
            return $env;
        }
        $host = preg_replace('/^www\./', '', (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com'));

        return 'support@'.$host;
    }
}
