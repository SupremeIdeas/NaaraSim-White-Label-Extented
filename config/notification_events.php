<?php

/**
 * Event matrix (Engineering v3 9.2): one row per user-facing event the platform can raise through App\Support\Notifications\Notify.
 *
 *   category  a key of config/notification_catalog.php (decides the default, whether it is critical, and which preference group it sits in)
 *   channels  the channels the event may use: in_app (bell), toast (live on-screen while the person is in the app), push (web push), email
 *   dedupe    true = the caller supplies a dedupe key; the same (person, event, key) is delivered once
 *   title/body  `{name}` style placeholders filled from the caller's $vars; plain text (the email wraps it in the branded shell)
 *   route     named route for the action button, or null
 *
 * `pending` = defined and ready (copy, channels, dedupe) but no trigger exists in the product yet (rewards do not expire, there is no reward-claim rejection step, no per-line fault source): `naara:verify notifications` says so plainly instead of pretending.
 * `existing` rows document events delivered by their own dedicated notification class (so the matrix stays the one list the owner reads, and
 * `naara:verify notifications` can assert the class exists and is cataloged). SMS is deliberately not offered: no event here justifies a paid channel.
 */
return [
    'events' => [
        // Rewards
        'reward.expiring' => ['pending' => true] + ['category' => 'lifecycle', 'channels' => ['in_app', 'push', 'email'], 'dedupe' => true, 'icon' => 'gift', 'route' => 'rewards', 'title' => 'A reward is about to expire', 'body' => 'Your {what} expires on {date}. Claim it before then.'],
        'reward.referral_credited' => ['category' => 'lifecycle', 'channels' => ['in_app', 'toast', 'push'], 'dedupe' => true, 'icon' => 'gift', 'route' => 'rewards', 'title' => 'Referral reward credited', 'body' => '{amount} was added because someone you invited joined.'],
        'reward.claim_rejected' => ['pending' => true] + ['category' => 'lifecycle', 'channels' => ['in_app', 'email'], 'dedupe' => true, 'icon' => 'alert-triangle', 'route' => 'rewards', 'title' => 'Reward claim not approved', 'body' => '{reason}'],

        // Wallet and payouts (processing / paid / failed are PayoutStatusNotification; funds added is TopUpReceiptNotification)
        'payout.bank_account_removed' => ['category' => 'security', 'channels' => ['in_app', 'email'], 'dedupe' => true, 'icon' => 'shield-check', 'route' => 'withdraw', 'title' => 'A withdrawal account was removed', 'body' => '{account} was removed from your payout accounts. If this was not you, secure your account now.'],
        'payout.frozen' => ['category' => 'security', 'channels' => ['in_app', 'email'], 'dedupe' => true, 'icon' => 'shield-check', 'route' => 'withdraw', 'title' => 'Payouts paused for your protection', 'body' => 'We paused payouts on your account. Pending withdrawals were cancelled and the money stays in your wallet. Reset your password and contact support to resume.'],

        // Messages (NaaraLine)
        'message.delivery_failed' => ['pending' => true] + ['category' => 'services', 'channels' => ['in_app', 'toast'], 'dedupe' => true, 'icon' => 'alert-triangle', 'route' => 'messages', 'title' => 'A message was not delivered', 'body' => 'Your message to {to} could not be delivered. {reason}'],
        'line.issue' => ['pending' => true] + ['category' => 'services', 'channels' => ['in_app', 'push', 'email'], 'dedupe' => true, 'icon' => 'alert-triangle', 'route' => 'numbers', 'title' => 'There is a problem with your line', 'body' => '{what}'],

    ],

    /** Events delivered by their own notification class: event => [class, what it covers]. */
    'existing' => [
        'account.email_verified' => ['EmailVerifiedNotification', 'Email confirmed'],
        'account.password_changed' => ['PasswordChangedNotification', 'Password or security change'],
        'account.new_device' => ['NewDeviceLoginNotification', 'New device sign-in'],
        'reward.claimed' => ['RewardClaimedNotification', 'Reward claim success'],
        'wallet.funds_added' => ['TopUpReceiptNotification', 'Funds added'],
        'payout.requested' => ['PayoutRequestedNotice', 'Withdrawal requested and account added'],
        'payout.status' => ['PayoutStatusNotification', 'Processing, paid, failed or reversed'],
        'payout.step_up' => ['PayoutStepUpCode', 'Step-up code'],
        'message.inbound_sms' => ['InboundSmsNotification', 'Inbound SMS'],
        'admin.alert' => ['AdminAlertNotification', 'Operator alerts: AlertAdminJob pushes + mails every admin. Codes: payout_stuck (stuck payout), payout_provider_outage / guardian_breaker_tripped (gateway degraded), invariant_violation (invariant breach), settlement_mismatch'],
    ],
];
