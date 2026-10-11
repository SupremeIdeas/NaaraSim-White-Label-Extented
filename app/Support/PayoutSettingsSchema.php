<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The ONE description of every payout setting an admin can change: label, plain-English help, type, default and
 * limits. The settings page renders itself from this, so adding a knob is one entry here (plus its accessor in
 * PayoutSettings) — nothing is hidden in code, and every value shown has a visible default and a reset button.
 *
 * Types: bool | int | float | select | csv | json
 */
class PayoutSettingsSchema
{
    /** @return array<string, array{title: string, blurb: string, fields: list<array<string, mixed>>}> */
    public static function groups(): array
    {
        return [
            'general' => [
                'title' => 'Basics',
                'blurb' => 'The master switches. Payouts are OFF until you turn them on.',
                'fields' => [
                    self::f(PayoutSettings::FLAG, 'Payouts enabled', 'Master switch. When off, nobody can request or receive a withdrawal.', 'bool', false, danger: true),
                    self::f(PayoutSettings::MODE, 'Settlement mode', 'Auto = the Guardian approves low-risk requests by itself and sends them (recommended). Manual = a person approves every request.', 'select', 'auto', options: ['manual' => 'Manual — approve each request', 'auto' => 'Auto — Guardian may approve'], danger: true),
                    self::f(PayoutSettings::MIN, 'Minimum withdrawal', 'Smallest amount a user can withdraw.', 'float', 5.0, min: 0, max: 100000, unit: 'USD'),
                    self::f(PayoutSettings::FREE_COUNT, 'Free payouts before identity check', 'How many payouts a user can take before identity verification (KYC level 2) is required.', 'int', 5, min: 0, max: 1000),
                    self::f(PayoutSettings::MAX_OPEN, 'Open requests per user', 'How many withdrawals one user can have in progress at the same time.', 'int', 3, min: 1, max: 50),
                    self::f(PayoutSettings::QUOTE_HOURS, 'Quote lock', 'How long the exchange rate shown to the user is honoured.', 'int', 24, min: 1, max: 168, unit: 'hours'),
                    self::f(PayoutSettings::STRIPE_GLOBAL, 'Stripe Global Payouts available', 'Turn on ONLY if your Stripe account is US/UK-based with Treasury and Global Payouts access. Without it, Stripe payouts are limited to recipients in the US, UK, EEA, Canada and Switzerland (Stripe\'s own rule).', 'bool', false, danger: true),
                    self::f(PayoutSettings::MANUAL_EXTERNAL, 'Manual rail (Plan B)', 'Lets you pay people yourself (bank/app) and record proof in Payout health. No provider is called. Use until a real provider is live.', 'bool', false),
                ],
            ],
            'autoapproval' => [
                'title' => 'Auto-approval (Guardian)',
                'blurb' => 'Defaults: automatic. The Guardian approves low-risk payouts on Paystack, Flutterwave and Stripe by itself; anything unusual goes to a person. Check Payout health -> Automation status to see exactly what is on.',
                'fields' => [
                    self::f(PayoutSettings::AUTO_APPROVAL, 'Auto-approval', 'Allow the Guardian to approve payouts that pass every check, so supported rails pay out with no manual work. Risky or large requests still go to a person.', 'bool', true, danger: true),
                    self::f(PayoutSettings::AUTO_SHADOW, 'Learning (shadow) mode', 'Turn ON only if you want the Guardian to just watch and record what it would do, with a person approving everything. Off = automatic payouts.', 'bool', false, danger: true),
                    self::f(PayoutSettings::TIER_LIMIT_PREFIX.'new', 'Limit — new payees', 'Largest payout the Guardian may approve for a new payee.', 'float', 100.0, min: 0, max: 1000000, unit: 'USD'),
                    self::f(PayoutSettings::TIER_LIMIT_PREFIX.'trusted', 'Limit — trusted payees', 'Same, once a payee has a good track record.', 'float', 500.0, min: 0, max: 1000000, unit: 'USD'),
                    self::f(PayoutSettings::TIER_LIMIT_PREFIX.'vip', 'Limit — VIP payees', 'Same, for payees you have marked VIP.', 'float', 2000.0, min: 0, max: 1000000, unit: 'USD'),
                    self::f(PayoutSettings::DAILY_CAP_USD, 'Daily auto-approval cap', 'Total the Guardian may approve in 24 hours. 0 = no cap.', 'float', 5000.0, min: 0, max: 100000000, unit: 'USD'),
                    self::f(PayoutSettings::BREAKER_HOURLY_FLOOR, 'Hourly approval floor', 'Minimum number of auto-approvals per hour before the "too many at once" brake can trip.', 'int', 5, min: 1, max: 1000),
                    self::f(PayoutSettings::BAND_APPROVE, 'Risk score — approve below', 'Requests scoring under this (0–100) can be approved.', 'int', 30, min: 0, max: 100),
                    self::f(PayoutSettings::BAND_HOLD, 'Risk score — hold above', 'Requests scoring over this go to manual review.', 'int', 60, min: 0, max: 100),
                    self::f(PayoutSettings::QA_SAMPLE_PCT, 'Quality-check sample', 'Share of auto-approvals flagged for a human spot-check.', 'float', 3.0, min: 0, max: 100, unit: '%'),
                    self::f(PayoutSettings::FAIL_CLOSED_HEARTBEAT, 'Stop if the Guardian\'s own jobs stall', 'If the sweeper or metrics job stops running, send everything to manual review instead of approving.', 'bool', true),
                ],
            ],
            'peer' => [
                'title' => 'Send earnings to a member',
                'blurb' => 'For people whose country has no payout rail yet: they can send their withdrawable earnings to an identity-verified member who can be paid out, who accepts and cashes out normally. Money is held until the member accepts; if they decline or it expires it goes straight back.',
                'fields' => [
                    self::f(PayoutSettings::PEER_ENABLED, 'Member-to-member transfers', 'Turn the feature on or off. It also needs payouts switched on.', 'bool', true),
                    self::f(PayoutSettings::PEER_ONLY_UNSUPPORTED, 'Only members we cannot pay out may send', 'When on, someone who already has a working payout rail in their country must cash out themselves. This keeps the feature for the people it was built for.', 'bool', true),
                    self::f(PayoutSettings::PEER_MIN_USD, 'Minimum transfer', 'Smallest amount one member can send another.', 'float', 5.0, min: 0, max: 100000, unit: 'USD'),
                    self::f(PayoutSettings::PEER_MAX_USD, 'Largest single transfer', 'Per transfer.', 'float', 200.0, min: 1, max: 1000000, unit: 'USD'),
                    self::f(PayoutSettings::PEER_SENDER_30D, 'Sender limit (30 days)', 'Most one member can send out in any 30 days.', 'float', 500.0, min: 1, max: 1000000, unit: 'USD'),
                    self::f(PayoutSettings::PEER_RECIPIENT_30D, 'Receiver limit (30 days)', 'Most one member can receive from others in any 30 days. Stops one account collecting for many.', 'float', 1000.0, min: 1, max: 1000000, unit: 'USD'),
                    self::f(PayoutSettings::PEER_EXPIRY_HOURS, 'Accept within', 'An unanswered transfer is returned to the sender after this long.', 'int', 72, min: 1, max: 720, unit: 'hours'),
                    self::f(PayoutSettings::PEER_RECIPIENT_KYC, 'Receiver identity level', 'The receiver must hold at least this verification level (2 = ID check).', 'int', 2, min: 1, max: 3),
                    self::f(PayoutSettings::PEER_SENDER_AGE_DAYS, 'Sender account age', 'Accounts younger than this cannot send.', 'int', 7, min: 0, max: 365, unit: 'days'),
                    self::f(PayoutSettings::PEER_MAX_PENDING, 'Open transfers per sender', 'How many unanswered transfers one member can have.', 'int', 3, min: 1, max: 50),
                    self::f(PayoutSettings::PEER_REVIEW_RECEIVED, 'Review payouts of received money', 'When most of a withdrawal is money another member sent, send it for a person to check before it is paid (recommended: this is the main money-laundering route).', 'bool', true),
                ],
            ],
            'extensions' => [
                'title' => 'Rail extensions',
                'blurb' => 'Rails delivered later through the Platform Updater (Payoneer, Grey, Stripe Global). They stay "coming soon" until installed. Core payouts never depend on them.',
                'fields' => [
                    self::f(PayoutSettings::DISABLED_EXTENSIONS, 'Switched-off extensions', 'Comma-separated slugs (e.g. payoneer) of installed extension rails to switch off without deleting them. Empty = all installed rails active.', 'csv', ''),
                ],
            ],
            'protection' => [
                'title' => 'Fraud & safety rules',
                'blurb' => 'Hard checks that run before money moves.',
                'fields' => [
                    self::f(PayoutSettings::COOLING_OFF_HOURS, 'Cooling-off for new destinations', 'A newly added payout account must wait this long before its first payout.', 'int', 48, min: 0, max: 720, unit: 'hours'),
                    self::f(PayoutSettings::MATURITY_DAYS, 'Earnings maturity', 'Earnings must be at least this old before they can be withdrawn — a safety window for refunds and chargebacks (the default of 7 days was recommended for card-funded earnings).', 'int', 7, min: 0, max: 90, unit: 'days'),
                    self::f(PayoutSettings::NEW_ACCOUNT_DAYS, '"New account" period', 'Accounts younger than this are treated as higher risk.', 'int', 7, min: 0, max: 365, unit: 'days'),
                    self::f(PayoutSettings::NAME_MATCH, 'Name-match strictness', 'How closely the bank account name must match the user\'s verified name (0–1; higher = stricter).', 'float', 0.85, min: 0, max: 1),
                    self::f(PayoutSettings::LARGEST_MULT, 'Unusually large payout', 'A payout bigger than this many times the user\'s largest previous one needs identity verification.', 'float', 5.0, min: 1, max: 1000, unit: '×'),
                    self::f(PayoutSettings::DENIED_COUNTRIES, 'Blocked countries', 'Comma-separated ISO country codes (e.g. KP, IR). Payouts to or from these are never sent.', 'csv', ''),
                    self::f(PayoutSettings::USER_CAP_PREFIX.'daily', 'Per-user cap — daily', 'Most one user can withdraw per day. 0 = no cap.', 'float', 0.0, min: 0, max: 100000000, unit: 'USD'),
                    self::f(PayoutSettings::USER_CAP_PREFIX.'weekly', 'Per-user cap — weekly', '0 = no cap.', 'float', 0.0, min: 0, max: 100000000, unit: 'USD'),
                    self::f(PayoutSettings::USER_CAP_PREFIX.'monthly', 'Per-user cap — monthly', '0 = no cap.', 'float', 0.0, min: 0, max: 100000000, unit: 'USD'),
                    self::f(PayoutSettings::STEP_UP_REQUIRED, 'Ask for a security code', 'Require a quick code (authenticator app, or emailed) before adding/changing a payout account and for every global-rail withdrawal.', 'bool', false),
                    self::f(PayoutSettings::STEP_UP_MINUTES, 'Security code validity', 'How long one successful code check stays valid.', 'int', 15, min: 1, max: 120, unit: 'minutes'),
                    self::f(PayoutSettings::SEND_DELAY_GLOBAL, 'Send delay — global rail, new destination', 'Pause between approval and sending to a never-used destination: the user\'s chance to cancel. 0 = none.', 'int', 10, min: 0, max: 1440, unit: 'minutes'),
                    self::f(PayoutSettings::SEND_DELAY_LOCAL, 'Send delay — local rail, new destination', 'Same, for local rails (Paystack, Flutterwave, Stripe…).', 'int', 0, min: 0, max: 1440, unit: 'minutes'),
                    self::f(PayoutSettings::NOTIFY_ON_REQUEST, 'Email on every withdrawal request', 'Tell the user when a withdrawal is requested or a payout account changes, with a "This wasn\'t me" button.', 'bool', true),
                    self::f(PayoutSettings::DUAL_CONTROL_USD, 'Dual control above', 'Whoever recorded float funding in the last 24h cannot approve a payout at/above this. 0 = off.', 'float', 0.0, min: 0, max: 100000000, unit: 'USD'),
                ],
            ],
            'money' => [
                'title' => 'Money & exchange rates',
                'blurb' => 'How amounts are priced and rounded.',
                'fields' => [
                    self::f(PayoutSettings::FX_TOLERANCE_PCT, 'Exchange-rate drift allowed', 'If the live rate has moved more than this since the quote, the request is re-checked.', 'float', 3.0, min: 0, max: 50, unit: '%'),
                    self::f(PayoutSettings::FX_BAND, 'Rate sanity band', 'A new rate that jumps more than this from the last accepted one is refused (feed fault protection). 0 = off.', 'float', 15.0, min: 0, max: 100, unit: '%'),
                    self::f(PayoutSettings::MAX_FEE_RATIO, 'Largest fixed-fee share', 'Refuse a payout where the provider\'s fixed fee would be more than this share of it. 0 = off.', 'float', 5.0, min: 0, max: 100, unit: '%'),
                    self::f('payouts.currency_decimals', 'Currency decimals (overrides)', 'Only if a currency is rounded wrongly. JSON, e.g. {"UGX":0,"KWD":3}. Built-in defaults cover the usual currencies.', 'json', '{}'),
                    self::f(PayoutSettings::TAX_FORM_OVER_USD, 'Ask for tax details above', 'Payees paid more than this in a calendar year must have tax details on file. 0 = never. Ask your accountant before using this.', 'float', 0.0, min: 0, max: 100000000, unit: 'USD'),
                ],
            ],
            'operations' => [
                'title' => 'Operations & timing',
                'blurb' => 'Timeouts, retention and alert timing.',
                'fields' => [
                    self::f(PayoutSettings::LOOKUP_GRACE_MIN, 'Unknown-outcome grace', 'How long a "provider has no record" answer is distrusted before a transfer is treated as not sent.', 'int', 30, min: 1, max: 1440, unit: 'minutes'),
                    self::f(PayoutSettings::OUTAGE_ALERT_MINUTES, 'Provider outage alert after', 'Alert you when requests have been queued this long behind a provider problem.', 'int', 60, min: 5, max: 1440, unit: 'minutes'),
                    self::f(PayoutSettings::HTTP_TIMEOUT, 'Provider call timeout', 'Longest we wait for a provider to answer. Capped at 35 so the worker never gives up first.', 'int', 25, min: 5, max: 35, unit: 'seconds'),
                    self::f(PayoutSettings::HTTP_CONNECT_TIMEOUT, 'Provider connect timeout', 'Longest we wait to reach a provider.', 'int', 5, min: 1, max: 15, unit: 'seconds'),
                    self::f(PayoutSettings::WEBHOOK_PAYLOAD_DAYS, 'Keep raw webhook payloads', 'Raw provider notifications are kept encrypted this long, then removed.', 'int', 90, min: 1, max: 3650, unit: 'days'),
                    self::f(PayoutSettings::ADD_ACCOUNT_PER_HOUR, 'Add-account limit', 'Payout accounts one user can add per hour. 0 = no limit.', 'int', 5, min: 0, max: 1000),
                    self::f(PayoutSettings::WITHDRAW_PER_MINUTE, 'Withdraw requests per minute', '0 = no limit.', 'int', 3, min: 0, max: 1000),
                    self::f(PayoutSettings::WITHDRAW_PER_DAY, 'Withdraw requests per day', '0 = no limit.', 'int', 10, min: 0, max: 10000),
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> key => field */
    public static function flat(): array
    {
        $out = [];
        foreach (self::groups() as $g) {
            foreach ($g['fields'] as $f) {
                $out[$f['key']] = $f;
            }
        }

        return $out;
    }

    /** The value currently in effect (stored, else the default). */
    public static function current(array $field): mixed
    {
        $v = Setting::getValue($field['key'], $field['default']);

        return match ($field['type']) {
            'bool' => (bool) $v,
            'int' => (int) $v,
            'float' => (float) $v,
            default => is_array($v) ? json_encode($v) : (string) $v,
        };
    }

    /** Is a value different from the default? */
    public static function isCustom(array $field): bool
    {
        return self::current($field) !== self::cast($field, $field['default']);
    }

    public static function cast(array $field, mixed $v): mixed
    {
        return match ($field['type']) {
            'bool' => (bool) $v,
            'int' => (int) $v,
            'float' => (float) $v,
            default => (string) $v,
        };
    }

    /** Validation rules for one field (used by the page and by tests). */
    public static function rules(array $field): array
    {
        return match ($field['type']) {
            'bool' => ['boolean'],
            'int' => ['required', 'integer', 'min:'.$field['min'], 'max:'.$field['max']],
            'float' => ['required', 'numeric', 'min:'.$field['min'], 'max:'.$field['max']],
            'select' => ['required', 'in:'.implode(',', array_keys($field['options']))],
            'csv' => ['nullable', 'string', 'max:500', 'regex:/^\s*([A-Za-z]{2}\s*(,\s*[A-Za-z]{2}\s*)*)?$/'],
            'json' => ['nullable', 'json'],
            default => ['nullable', 'string', 'max:500'],
        };
    }

    private static function f(string $key, string $label, string $help, string $type, mixed $default, ?float $min = null, ?float $max = null, ?string $unit = null, array $options = [], bool $danger = false): array
    {
        return compact('key', 'label', 'help', 'type', 'default', 'min', 'max', 'unit', 'options', 'danger');
    }
}
