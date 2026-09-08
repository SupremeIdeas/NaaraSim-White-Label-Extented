<?php

namespace App\Support;

use App\Models\User;

/**
 * NaaraFacts (owner request) — the greeting + "did you know" engine for the
 * customer dashboard. It welcomes the user by name with a time-of-day greeting
 * and surfaces ONE fact a day about what their NaaraSim numbers / eSIMs can
 * actually do globally, so an idle account learns the worth of what it holds.
 *
 * The fact is chosen deterministically from the date + user, so it's stable for
 * the whole day but rotates every login-day. When the Claude API is live
 * (Setting 'dashboard.ai_facts' + a configured key), tailoredFor() is the hook
 * to swap in a fact personalised to the user's activity; until then the curated
 * library below is the source of truth.
 */
class NaaraFacts
{
    /**
     * Curated facts — plain, true, benefit-led. Grouped so a user sees variety
     * across days. Keep the voice warm and useful (brand: "No Borders").
     *
     * @var array<int, string>
     */
    public const FACTS = [
        // eSIM data
        'One NaaraSim eSIM can keep you online across 190+ countries — no SIM swap when you land, no roaming shock on your home bill.',
        'Travelling through several countries? A single regional eSIM plan follows you across borders, so you stay connected the moment the plane doors open.',
        'Your eSIM installs in under a minute with a QR code — your physical SIM stays in place for calls back home while data runs on NaaraSim.',
        'Data running low mid-trip? You can top up your eSIM from the app in seconds — no queue at a foreign kiosk, no language barrier.',
        'An eSIM means your phone number back home keeps working while you browse, map, and message on local data abroad.',

        // Verification numbers (OTP)
        'A NaaraSim verification number lets you sign up for apps that aren’t available in your country — verify once, get your code, done.',
        'Protect your real number: use a NaaraSim verification number for sign-ups and marketplaces so your personal line never leaks to strangers.',
        'Need to confirm a WhatsApp, Google, or Telegram account in another country? A NaaraSim number receives the code for you in seconds.',
        'Selling online? A verification number keeps buyers off your personal phone while you still get every code and reply you need.',

        // Rentals
        'Rent a number for a full period and receive unlimited codes from one service — perfect for managing a business account long-term.',
        'A rented NaaraSim number can act as a dedicated line for a single platform, so all of that app’s messages land in one predictable place.',
        'With an “any service” rental you can receive codes from every service on one number — a single inbox for all your verifications.',

        // Permanent / voice lines (Naara Line)
        'A permanent NaaraSim line gives you a second number for life — hand it out internationally and answer calls on the phone already in your pocket.',
        'Forward your NaaraSim line to any phone in the world, so one number reaches you whether you’re in Lagos today or London next week.',
        'Call any international number straight from your browser with the in-app dialer — no app install, no second handset, billed by the minute.',
        'Keep work and personal life separate with a permanent second line — give clients the NaaraSim number, keep your private one private.',

        // General / trust
        'NaaraSim sells both data AND numbers in one app — most competitors do data only, so you’d normally juggle two services. Here it’s one.',
        'Every price you see already includes the network cost — what you see is what you pay, in your own currency, with no hidden extras.',
        'Your wallet auto-refunds if a code never arrives, so a number that doesn’t deliver never costs you a thing.',
        'Earn NaaraCredits as you go and put them toward your next eSIM or number — loyalty that actually lowers your bill.',
        'Refer a friend and you both benefit — your referral rewards can even be withdrawn to cash once you’re verified.',
        'Stay Connected. No Borders. No Swaps — one NaaraSim account is your data plan, your second line, and your verification desk worldwide.',
    ];

    /** Time-of-day greeting for a user, e.g. "Good morning, Ada". */
    public static function greeting(User $user): string
    {
        $name = trim((string) $user->name);
        $first = $name !== '' ? explode(' ', $name)[0] : 'there';
        $hour = (int) now()->format('G');

        $part = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            $hour < 21 => 'Good evening',
            default => 'Good evening',
        };

        return "{$part}, {$first}";
    }

    /** A warm one-liner asking about their day (rotates with the fact seed). */
    public static function askOfTheDay(User $user): string
    {
        $lines = [
            'Hope your day’s going well.',
            'How’s your day treating you?',
            'Great to see you back.',
            'Wishing you a smooth one today.',
            'Hope today’s been kind to you.',
        ];

        return $lines[self::seed($user) % count($lines)];
    }

    /** The fact of the day for this user (deterministic per date + user). */
    public static function dailyFor(User $user): string
    {
        // Hook for the AI layer: when the Claude API is live and enabled, a
        // tailored fact can replace the curated one. Falls back safely.
        if (($tailored = self::tailoredFor($user)) !== null) {
            return $tailored;
        }

        return self::FACTS[self::seed($user) % count(self::FACTS)];
    }

    /**
     * AI-tailored fact hook (Claude API — future). Returns null until the AI
     * facts setting is on AND a generator is wired, so the dashboard always has
     * a fact regardless. Kept here so the call site never changes.
     */
    public static function tailoredFor(User $user): ?string
    {
        if (! (bool) \App\Models\Setting::getValue('dashboard.ai_facts', false)) {
            return null;
        }

        // Placeholder for the live Claude generator (guarded + cached per day).
        return null;
    }

    /** Stable daily seed: changes each calendar day, unique per user. */
    private static function seed(User $user): int
    {
        return (int) sprintf('%u', crc32(now()->toDateString().':'.$user->id));
    }
}
