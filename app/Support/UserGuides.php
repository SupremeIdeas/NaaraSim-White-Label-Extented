<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserGuide;

/**
 * Per-audience user guides + agreement. Serves the guide for a given audience,
 * seeding sensible on-brand defaults on first read, and picks the right audience
 * for a signed-in user. Admin edits everything (intro, sections + images, and the
 * agreement) from the admin area.
 */
class UserGuides
{
    public const AUDIENCES = ['user', 'merchant', 'merchant_v2', 'developer'];

    public static function label(string $audience): string
    {
        return match ($audience) {
            'merchant' => 'Merchant guide',
            'merchant_v2' => 'Merchant V2 guide',
            'developer' => 'Developer guide',
            default => 'User guide',
        };
    }

    /** The best-fit guide audience for a signed-in user. */
    public static function audienceFor(?User $user): string
    {
        if (! $user) {
            return 'user';
        }
        $merchant = $user->merchantAccount;
        if ($merchant && $merchant->isActive()) {
            return $merchant->isV2() ? 'merchant_v2' : 'merchant';
        }

        return 'user';
    }

    public static function for(string $audience): UserGuide
    {
        $audience = in_array($audience, self::AUDIENCES, true) ? $audience : 'user';

        return UserGuide::firstOrCreate(['audience' => $audience], self::defaults($audience));
    }

    /** The shared one-account + pricing agreement, tuned per audience. */
    private static function agreement(string $extra = ''): string
    {
        $brand = 'NaaraSim';

        return '<h3>Our promise on pricing</h3>'
            .'<p>You always pay a clear <strong>retail price</strong> — provider cost plus a modest, consistent margin — across every '.$brand.' product: eSIM data, Full eSIMs, virtual numbers, verification, and calls. Prices are <strong>flat and reasonable</strong>, the same rules for everyone, with no hidden fees. What you are charged is what you see before you confirm.</p>'
            .'<h3>What you enjoy</h3>'
            .'<ul><li>One wallet for data <em>and</em> numbers in 190+ countries.</li>'
            .'<li>Instant activation and honest, up-front pricing.</li>'
            .'<li>Money-safety guarantees: you are never charged without delivery, and failed orders are auto-refunded.</li></ul>'
            .$extra
            .'<h3>Rules &amp; regulations</h3>'
            .'<ul><li>Use '.$brand.' lawfully; do not resell verification numbers for fraud or abuse.</li>'
            .'<li>Chargebacks or fraudulent payments lead to suspension.</li>'
            .'<li>Respect the fair-use and refund policy shown at checkout.</li></ul>'
            .'<h3>One account, one verification</h3>'
            .'<p><strong>Please keep a single account.</strong> One verified account unlocks the full platform — withdrawals, higher limits, merchant and developer features. Creating <strong>multiple accounts</strong> to farm bonuses or spam the platform will trigger <strong>automatic restrictions</strong> on the accounts involved. Verify once, and enjoy everything '.$brand.' offers.</p>';
    }

    public static function defaults(string $audience): array
    {
        return match ($audience) {
            'merchant' => [
                'audience' => 'merchant', 'title' => 'Merchant guide',
                'intro' => 'Resell NaaraSim eSIMs and numbers under your own brand, earning on every sale.',
                'sections' => [
                    ['heading' => 'Getting started', 'body' => '<p>Apply from <strong>Become a Merchant</strong>, complete business verification, and set up your storefront branding and invite link.</p>', 'image' => ''],
                    ['heading' => 'How you earn', 'body' => '<p>Your customers pay your reseller price; NaaraSim settles the standard retail from your wallet and the difference is your earnings, tracked in your ledger and withdrawable.</p>', 'image' => ''],
                    ['heading' => 'Keeping a funded wallet', 'body' => '<p>Always keep enough balance to fulfil your customers\' orders — every sale debits your merchant wallet at your reseller price.</p>', 'image' => ''],
                ],
                'agreement' => self::agreement('<h3>Merchant terms</h3><p>The contract between NaaraSim and you is settled from your wallet at the standard price. Whatever you negotiate with your own customers is between you and them.</p>'),
            ],
            'merchant_v2' => [
                'audience' => 'merchant_v2', 'title' => 'Merchant V2 guide',
                'intro' => 'Manage eSIMs for clients who never log in — register devices, subscribe plans, and control renewals.',
                'sections' => [
                    ['heading' => 'Registering a client', 'body' => '<p>Add a client with their device, WhatsApp and email. NaaraSim checks eSIM compatibility before you assign — you can override for a device you have confirmed.</p>', 'image' => ''],
                    ['heading' => 'Assigning an eSIM', 'body' => '<p>Choose Naara Data or Naara Connect, pick a plan, and subscribe it from your wallet at your reseller price. Each client shows a live validity countdown.</p>', 'image' => ''],
                    ['heading' => 'Auto-billing &amp; locked funds', 'body' => '<p>Mark a client for auto-renewal to <strong>reserve</strong> the next renewal amount from your wallet. Reserved funds are locked and cannot be reused — this cannot be undone unless a future renewal fails to provision, in which case the funds are returned automatically.</p>', 'image' => ''],
                    ['heading' => 'Non-payment &amp; invoices', 'body' => '<p>Disable a client\'s eSIM if they don\'t pay you, re-provision when they do, send WhatsApp reminders, and invoice them with your own price and brand. What you charge your clients is your business.</p>', 'image' => ''],
                ],
                'agreement' => self::agreement('<h3>Merchant V2 terms</h3><p>NaaraSim only settles the standard price from your wallet when an eSIM is registered for a client. Always keep sufficient balance to facilitate your clients\' subscription periods.</p>'),
            ],
            'developer' => [
                'audience' => 'developer', 'title' => 'Developer guide',
                'intro' => 'Build on NaaraSim: catalogue, quote, and order eSIMs and numbers over a simple REST API.',
                'sections' => [
                    ['heading' => 'Keys &amp; scopes', 'body' => '<p>Create an API key in the Developer portal and choose scopes (catalogue, quote, order, status). Your key is shown once — store it safely.</p>', 'image' => ''],
                    ['heading' => 'Prepaid billing', 'body' => '<p>Top up your API wallet from your main wallet. Each order is billed at the developer price (wholesale + a small markup) — provider cost is never exposed.</p>', 'image' => ''],
                    ['heading' => 'Ordering', 'body' => '<p>POST an order with an idempotency reference; poll <code>GET /orders/{ref}</code> for delivery and OTP status. Failed orders are auto-refunded to your API wallet.</p>', 'image' => ''],
                ],
                'agreement' => self::agreement('<h3>Developer terms</h3><p>Developer pricing is a small markup over wholesale, floored so it is always sustainable. Respect rate limits and do not use the API to enable fraud.</p>'),
            ],
            default => [
                'audience' => 'user', 'title' => 'Welcome to NaaraSim',
                'intro' => 'Everything you need to stay connected anywhere — data and numbers in one app.',
                'sections' => [
                    ['heading' => 'eSIM data plans', 'body' => '<p>Buy a local data plan for 190+ countries and activate it in under a minute — no SIM swap. Check your device compatibility before you buy.</p>', 'image' => ''],
                    ['heading' => 'Numbers', 'body' => '<p>Get disposable numbers for OTP (Naara Verify), rent numbers (Naara Rent), or a permanent second line with voice + SMS (Naara Line).</p>', 'image' => ''],
                    ['heading' => 'Your wallet &amp; rewards', 'body' => '<p>Top up once and pay for anything. Earn NaaraCredits, and refer friends for rewards.</p>', 'image' => ''],
                ],
                'agreement' => self::agreement(),
            ],
        };
    }
}
