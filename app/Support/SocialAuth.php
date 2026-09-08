<?php

namespace App\Support;

/**
 * Social sign-in providers (owner request). Each provider lights up only once
 * both its credentials are saved (mirrors ProviderStatus). Ships a short,
 * baked-in setup guide per provider — where to create the app and the exact
 * callback URL to paste — so the operator can configure it without guesswork.
 */
class SocialAuth
{
    /**
     * provider => [label, icon slug, socialite driver, [required config keys],
     * console URL, brief steps]. Driver differs from the key where Socialite
     * names it differently (X uses the OAuth-2 driver).
     *
     * @var array<string, array<string, mixed>>
     */
    public const PROVIDERS = [
        'google' => [
            'label' => 'Google', 'icon' => 'google', 'driver' => 'google',
            'keys' => ['services.google.client_id', 'services.google.client_secret'],
            'console' => 'https://console.cloud.google.com/apis/credentials',
            'steps' => 'Create an OAuth 2.0 Client ID (type: Web application), add the callback URL below as an Authorized redirect URI, then paste the Client ID and Client secret.',
        ],
        'facebook' => [
            'label' => 'Facebook', 'icon' => 'facebook', 'driver' => 'facebook',
            'keys' => ['services.facebook.client_id', 'services.facebook.client_secret'],
            'console' => 'https://developers.facebook.com/apps',
            'steps' => 'Create an app, add the "Facebook Login" product, put the callback URL below under Valid OAuth Redirect URIs, then copy the App ID and App secret.',
        ],
        'twitter' => [
            'label' => 'X (Twitter)', 'icon' => 'x', 'driver' => 'twitter-oauth-2',
            'keys' => ['services.twitter.client_id', 'services.twitter.client_secret'],
            'console' => 'https://developer.x.com/en/portal/dashboard',
            'steps' => 'In an OAuth 2.0 app, enable "User authentication settings", set the callback URL below, request the users.read + tweet.read scopes, then copy the Client ID and Client secret.',
        ],
        'apple' => [
            'label' => 'Apple', 'icon' => 'apple', 'driver' => 'apple',
            'keys' => ['services.apple.client_id', 'services.apple.client_secret'],
            'console' => 'https://developer.apple.com/account/resources/identifiers/list/serviceId',
            'steps' => 'Register a Services ID (this is the Client ID), enable "Sign in with Apple", add the callback URL below as a Return URL, and generate a client-secret JWT from your key to paste as the secret.',
        ],
        'microsoft' => [
            'label' => 'Microsoft', 'icon' => 'microsoft', 'driver' => 'microsoft',
            'keys' => ['services.microsoft.client_id', 'services.microsoft.client_secret'],
            'console' => 'https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade',
            'steps' => 'Register an app in Microsoft Entra ID, add a Web platform redirect matching the callback URL below, create a client secret, then paste the Application (client) ID and the secret value.',
        ],
        'discord' => [
            'label' => 'Discord', 'icon' => 'discord', 'driver' => 'discord',
            'keys' => ['services.discord.client_id', 'services.discord.client_secret'],
            'console' => 'https://discord.com/developers/applications',
            'steps' => 'Create an application, add the callback URL below under OAuth2 → Redirects, select the identify + email scopes, then copy the Client ID and Client secret.',
        ],
    ];

    public static function isKnown(string $provider): bool
    {
        return isset(self::PROVIDERS[$provider]);
    }

    /** A provider is enabled once all its credentials are configured. */
    public static function enabled(string $provider): bool
    {
        foreach (self::PROVIDERS[$provider]['keys'] ?? [] as $key) {
            if (empty(config($key))) {
                return false;
            }
        }

        return self::isKnown($provider);
    }

    /** The Socialite driver name for a provider (X differs from its key). */
    public static function driver(string $provider): string
    {
        return (string) (self::PROVIDERS[$provider]['driver'] ?? $provider);
    }

    /** The absolute callback URL for a provider (anchored to this site). */
    public static function redirect(string $provider): string
    {
        $configured = (string) config("services.$provider.redirect");
        if ($configured !== '' && str_starts_with($configured, 'http')) {
            return $configured;
        }

        return url($configured !== '' ? $configured : "/auth/$provider/callback");
    }

    /** @return array<string,array<string,mixed>> only the enabled providers */
    public static function enabledProviders(): array
    {
        return array_filter(
            self::PROVIDERS,
            fn ($p) => self::enabled($p),
            ARRAY_FILTER_USE_KEY,
        );
    }

    public static function anyEnabled(): bool
    {
        return self::enabledProviders() !== [];
    }
}
