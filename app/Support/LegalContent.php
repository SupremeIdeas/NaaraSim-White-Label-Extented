<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Legal document CMS (Module 30). Ships accurate, best-practice defaults for the
 * five legal documents third-party login reviews (Google, Facebook, Apple) and
 * app stores expect at a stable public URL. Admins can override the title/body
 * per document; overrides live in one Setting per doc and are merged over the
 * shipped defaults. Body is a light markup (## headings + blank-line paragraphs)
 * rendered safely (escaped) so admin edits can never inject HTML/scripts.
 *
 * These defaults are sensible starting points, NOT legal advice — the operator
 * should have them reviewed for their jurisdiction. {brand} is interpolated.
 */
class LegalContent
{
    private const CACHE = 'legal.docs.v1';

    /** slug => default [title, body]. Order = index page order. */
    public static function slugs(): array
    {
        return ['privacy', 'terms', 'refund', 'cookies', 'data-deletion'];
    }

    public static function isLegalKey(string $key): bool
    {
        return str_starts_with($key, 'legal.');
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE);
    }

    /** All docs (merged defaults + overrides) for the index. */
    public static function all(): array
    {
        return collect(self::slugs())->map(fn ($slug) => self::doc($slug))->all();
    }

    public static function exists(string $slug): bool
    {
        return in_array($slug, self::slugs(), true);
    }

    /**
     * A single doc: admin override merged over the default, brand interpolated.
     *
     * @return array{slug:string,title:string,body:string,updated:?string}
     */
    public static function doc(string $slug): array
    {
        $merged = Cache::rememberForever(self::CACHE, function () {
            $out = [];
            foreach (self::slugs() as $s) {
                $default = self::defaults()[$s];
                $override = [];
                try {
                    $override = Setting::getValue("legal.$s", []) ?: [];
                } catch (\Throwable) {
                    $override = [];
                }
                $out[$s] = [
                    'slug' => $s,
                    'title' => $override['title'] ?? $default['title'],
                    'body' => $override['body'] ?? $default['body'],
                    'updated' => $override['updated'] ?? null,
                ];
            }

            return $out;
        })[$slug] ?? ['slug' => $slug, 'title' => ucfirst($slug), 'body' => '', 'updated' => null];

        $brand = BrandSettings::name();
        $merged['title'] = str_replace('{brand}', $brand, $merged['title']);
        $merged['body'] = str_replace('{brand}', $brand, $merged['body']);

        return $merged;
    }

    /** @return array<string, array{title:string, body:string}> */
    public static function defaults(): array
    {
        return [
            'privacy' => [
                'title' => 'Privacy Policy',
                'body' => <<<'TXT'
{brand} ("we", "us") respects your privacy. This policy explains what we collect, why, and your rights.

## Who we are
{brand} is a travel-connectivity service operated by Supreme Ideas Agency, Onitsha, Nigeria. We sell eSIM data plans and virtual/verification phone numbers.

## What we collect
Account data you give us: your name, email address, and password (stored hashed). Transaction data: wallet top-ups, orders, and balances. Technical data: IP address, device and browser information, and security logs. If you sign in with Google, we receive your name, email, and profile identifier from Google — nothing more.

## Why we collect it
To create and secure your account, deliver the eSIMs and numbers you buy, process payments, prevent fraud and abuse, provide support, and meet legal obligations. We never sell your personal data.

## Sharing
We share only what is necessary with our connectivity and payment providers to fulfil your orders, and with authorities where the law requires it. Providers act under contract and may not use your data for their own purposes.

## Data retention
We keep account and transaction records for as long as your account is active and as required by law, then delete or anonymise them.

## Your rights
You can access, correct, export, or delete your personal data from your account (Account & privacy), or by contacting us. Deletion is reviewed and, once approved, reaches our backups. See our Data Deletion policy for details.

## Contact
Questions about privacy? Reach us through the in-app support channel or the contact page.
TXT,
            ],
            'terms' => [
                'title' => 'Terms of Service',
                'body' => <<<'TXT'
By creating an account or buying from {brand}, you agree to these terms.

## The service
{brand} sells eSIM data plans and virtual/verification phone numbers. Verification numbers receive SMS (and, on some networks, a voice OTP) — they are not full phone lines. Only permanent numbers make and receive calls. We describe each product as exactly what it is.

## Your account
You are responsible for keeping your login secure and for activity on your account. You must give accurate information and be old enough to form a binding contract in your country.

## Payments and wallet
You pay the retail price shown before you confirm — always. Wallet balances are store credit, spendable on eSIMs and numbers, and are not a bank deposit. Prices are shown before purchase and may change over time.

## Acceptable use
Do not use our numbers or data for fraud, spam, harassment, or anything illegal. We may suspend accounts that abuse the service or put our provider relationships at risk.

## Availability
Connectivity depends on third-party networks. We work to keep the service reliable but cannot guarantee uninterrupted coverage in every location.

## Refunds
Refunds are governed by our Refund Policy.

## Changes
We may update these terms; material changes will be notified in-app or by email. Continued use means you accept the updated terms.
TXT,
            ],
            'refund' => [
                'title' => 'Refund Policy',
                'body' => <<<'TXT'
We aim to be fair and honest about refunds.

## eSIM data plans
An eSIM bundle that has not been started (not yet activated on a device/network) is revocable and refundable to your {brand} wallet. Once a bundle has been activated and data has begun, it is consumed and generally non-refundable.

## Verification numbers
If no code arrives within the delivery window, the number auto-refunds to your wallet automatically — you are not charged for a code you never received.

## Wallet balances
Wallet balances are store credit, spendable across eSIMs and numbers. Top-ups are not withdrawable to cash unless required by law.

## How to request
Contact support through the app with your order reference. Approved refunds are returned to your {brand} wallet, usually within a short review window.
TXT,
            ],
            'cookies' => [
                'title' => 'Cookie Policy',
                'body' => <<<'TXT'
{brand} uses a small number of cookies to make the service work.

## Essential cookies
We use cookies that are strictly necessary: to keep you signed in (session), to protect forms against cross-site request forgery, and to remember your light/dark theme preference. The service cannot function without these.

## What we do not do
We do not use advertising cookies and we do not sell cookie data to third parties.

## Managing cookies
You can clear or block cookies in your browser settings, but essential cookies are required to sign in and buy. Disabling them will break parts of the service.
TXT,
            ],
            'data-deletion' => [
                'title' => 'Data Deletion',
                'body' => <<<'TXT'
You can ask us to delete your {brand} account and personal data at any time.

## From your account
Sign in, go to Account & privacy, and choose "Request account deletion". A super administrator reviews every request before anything is erased — you can cancel until it is approved.

## What deletion removes
Once approved, we permanently remove your profile, wallet records, orders, and numbers, and the erasure reaches our backups within the normal backup rotation. Some records may be retained in anonymised form where the law requires (for example, financial records).

## If you signed in with Google or Facebook
Deleting your {brand} account removes the data we hold. It does not affect your Google or Facebook account itself — manage those with the respective provider.

## Contact
If you cannot sign in, contact support with the email on your account and we will help you complete the request.
TXT,
            ],
        ];
    }
}
