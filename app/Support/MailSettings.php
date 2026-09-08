<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-managed outgoing-mail configuration (blueprint Section 22 + Module 22).
 * Mirrors ProviderKeys: the operator sets the mailer + SMTP credentials + the
 * "from" identity in the admin panel — no .env editing — and applyToConfig()
 * overlays them on config('mail.*') at boot so every Mailable/Notification uses
 * them. Credentials are encrypted at rest and never echoed back.
 *
 * We deliberately support the drivers a self-hosted operator actually uses:
 *   - log     (default until configured — writes mail to the log, sends nothing)
 *   - smtp    (cPanel email, Gmail, Mailgun/Postmark/Resend/Brevo all give SMTP)
 *   - sendmail(the local MTA on a VPS)
 * SMTP covers essentially every provider, so we don't need per-API drivers here.
 */
class MailSettings
{
    public const SETTING_KEY = 'mail.settings';

    private const CACHE_KEY = 'mail.settings.resolved';

    /** Verification enforcement modes (NAARA-BUILD-20 §2). Default: soft. */
    public const MODE_OFF = 'off';

    public const MODE_SOFT = 'soft';

    public const MODE_HARD = 'hard';

    public const MODES = [self::MODE_OFF, self::MODE_SOFT, self::MODE_HARD];

    /**
     * How email verification is enforced:
     *  - off:  never required or nudged.
     *  - soft: (default) never blocks — browse/buy freely; a dismissible banner
     *          nudges the user to confirm for receipts + account security.
     *  - hard: unverified users are held at the verification notice (the prior
     *          behaviour whenever mail was configured).
     */
    public static function verificationMode(): string
    {
        try {
            $m = (string) Setting::getValue('mail.verification_mode', self::MODE_SOFT);

            return in_array($m, self::MODES, true) ? $m : self::MODE_SOFT;
        } catch (\Throwable) {
            return self::MODE_SOFT;
        }
    }

    public static function setVerificationMode(string $mode): void
    {
        Setting::setValue(
            'mail.verification_mode',
            in_array($mode, self::MODES, true) ? $mode : self::MODE_SOFT,
            'mail',
            'Email verification enforcement (off | soft | hard).',
        );
    }

    /** Should the soft-gate banner nudge this (unverified) user to confirm? */
    public static function shouldNudge(mixed $user): bool
    {
        return self::isConfigured()
            && self::verificationMode() === self::MODE_SOFT
            && $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail
            && ! $user->hasVerifiedEmail();
    }

    /** field-name => [config path it overlays, is-secret]. */
    private const MAP = [
        'mailer' => ['mail.default', false],
        'smtp_host' => ['mail.mailers.smtp.host', false],
        'smtp_port' => ['mail.mailers.smtp.port', false],
        'smtp_username' => ['mail.mailers.smtp.username', false],
        'smtp_password' => ['mail.mailers.smtp.password', true],
        'smtp_scheme' => ['mail.mailers.smtp.scheme', false],
        'from_address' => ['mail.from.address', false],
        'from_name' => ['mail.from.name', false],
    ];

    /** @return array<string, array{config: string, secret: bool}> */
    public static function fields(): array
    {
        $out = [];
        foreach (self::MAP as $name => [$config, $secret]) {
            $out[$name] = ['config' => $config, 'secret' => $secret];
        }

        return $out;
    }

    /** @return array<string, mixed> field-name => stored value */
    public static function saved(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                $stored = Setting::getValue(self::SETTING_KEY, []);

                return is_array($stored) ? $stored : [];
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Overlay saved mail config on top of config('mail.*') at boot. Blank
     * values are skipped so .env still fills anything the admin left empty.
     */
    public static function applyToConfig(): void
    {
        foreach (self::saved() as $field => $value) {
            if (! isset(self::MAP[$field])) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            config([self::MAP[$field][0] => $value]);
        }
    }

    /**
     * Persist the admin form. Blank secret (password) is a no-op so the operator
     * can update the host without re-typing the password; blank non-secret
     * fields are stored as-is (so they CAN clear e.g. a username).
     *
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values): void
    {
        $map = self::saved();

        foreach ($values as $field => $value) {
            if (! isset(self::MAP[$field])) {
                continue;
            }
            $value = is_string($value) ? trim($value) : $value;
            $isSecret = self::MAP[$field][1];

            if ($isSecret && ($value === null || $value === '')) {
                continue; // keep the stored password
            }
            $map[$field] = $value;
        }

        Setting::setValue(self::SETTING_KEY, $map, 'mail', 'Admin-managed outgoing mail config (encrypted).');
        self::flush();
        self::applyToConfig();
    }

    /** Current resolved value of a field (admin-saved overlaid on .env). */
    public static function get(string $field, mixed $default = null): mixed
    {
        $config = self::MAP[$field][0] ?? null;

        return $config ? (config($config) ?? $default) : $default;
    }

    /** Masked preview of the stored SMTP password (never the raw value). */
    public static function passwordPreview(): ?string
    {
        $value = (string) self::get('smtp_password', '');
        if ($value === '') {
            return null;
        }
        $len = strlen($value);

        return $len <= 4 ? str_repeat('•', $len) : str_repeat('•', min($len - 2, 10)).substr($value, -2);
    }

    /** True once a real, sending mailer is configured (not log/array no-ops). */
    public static function isConfigured(): bool
    {
        return ! in_array(self::get('mailer', 'log'), ['log', 'array'], true);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isMailKey(string $key): bool
    {
        return $key === self::SETTING_KEY;
    }
}
