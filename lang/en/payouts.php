<?php

return [
    // Payout status shown to users (Addendum C §5-D). Never a rule name or score.
    'generic_reason' => 'the payout could not be completed',
    'status' => [
        'being_checked' => 'Being checked — usually a few minutes',
        'security_hold' => 'Security hold until :time — nothing needed from you',
        'queued' => 'Queued — we\'re preparing funds, no action needed',
        'extra_check' => 'Extra check in progress — we\'ll notify you',
        'sent' => 'Sent to :provider — can take up to :days days',
        'paid' => 'Delivered to your :provider account',
        'returned' => 'Returned to your balance — :reason',
        'bounced' => 'Bounced back by the bank after sending — the money is back in your balance. Please check your payout details',
    ],
    'not_me' => [
        'title' => 'Payouts paused',
        'body' => 'We\'ve paused payouts on your account and let our team know. Nothing further will be sent until you hear from us.',
        'cancelled' => '{1} 1 pending withdrawal was cancelled and the money is back in your balance.|[2,*] :count pending withdrawals were cancelled and the money is back in your balance.',
        'reset' => 'Reset my password',
    ],
    'cancel' => [
        'button' => 'Cancel',
        'confirm' => 'Cancel this withdrawal? The money goes straight back to your balance.',
        'done' => 'Withdrawal cancelled — the money is back in your balance.',
        'too_late' => 'It has already been sent, so it can\'t be cancelled now.',
    ],
    'step_up' => [
        'title' => 'Verify it\'s you',
        'body' => 'For your security, confirm a code before adding or changing a payout account.',
        'send' => 'Send me a code',
        'enter' => 'Enter the 6-digit code',
        'verify' => 'Verify',
        'ok' => 'Verified — you can continue for the next :minutes minutes.',
        'bad' => 'That code didn\'t work. Try again.',
        'sent' => 'Code sent to your email.',
        'authenticator' => 'Use the code from your authenticator app.',
    ],
];
