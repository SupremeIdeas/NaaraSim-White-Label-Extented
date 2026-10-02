# Member-to-member earnings transfer (for countries with no payout rail)

**Why it exists.** Someone in a country none of our rails reach can still own earnings (referral/merchant). Instead of a manual payout by you, they send those earnings to a trusted member who **can** be paid out; that member accepts and withdraws through the normal automatic payout path.

**Where.** `/account/send-earnings`. The Rail Guide links to it when a country has no rail. Admin knobs: Admin → All payout settings → "Send earnings to a member".

**How the money moves (escrow).**
1. Sender creates a transfer. Their earnings leave their ledger immediately (`transfer_out`) and sit "in escrow" (no one's balance).
2. Receiver gets an email and an in-app notice. They accept (`transfer_in` credits their referral earnings) or decline.
3. Decline, sender cancel, or no answer in 72 h (setting): earnings are returned to the sender (`release`) exactly once.
Every state change is a compare-and-set on the transfer's status inside one DB transaction with its ledger write, and each ledger row is idempotent on a reference, so retries, double clicks and races cannot move money twice. Transfers are **not** accruals, so platform margin and admin-withdrawable maths are unaffected.

**Safety rules (all adjustable except the structure).**
- Only members we cannot pay out may send (setting; on by default). Someone with a working rail cashes out themselves.
- Receiver must be active, not frozen, identity-verified (KYC level 2 by default) and hold a verified payout account on a rail that is switched on and healthy today.
- Limits: $5 minimum, $200 per transfer, $500 sent / $1000 received per 30 days, 3 open transfers, sender account at least 7 days old. Max 10 send attempts per hour per sender.
- No chaining: a member who received in the last 30 days cannot send; one who sent cannot receive.
- Privacy: lookup is by exact email and every refusal reads the same, so it cannot reveal who has an account, who is verified, or a receiver's totals. The other side is shown as "Ada O.".
- Review: when more than half of a withdrawal is received money, the Guardian adds a signal (`S_peer_funds`, +35) that sends it for a person's look (setting "Review payouts of received money", on by default). This is the main money-laundering route, which is why it is the one place a human still looks.

**Not included on purpose.** No fee, no free text beyond a 200-character note, no staff/partner earnings (employment/contract money), no wallet-balance transfers (wallet money is deposited cash and has no cash-out path).

**Tests.** `tests/Feature/EarningsTransferTest.php` (escrow, accept-once, decline/cancel/expiry, races, ownership, limits, uniform refusals, no chaining, review signal, UI).
