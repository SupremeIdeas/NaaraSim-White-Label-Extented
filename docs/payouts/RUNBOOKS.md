# Payout runbooks

All screens: Admin -> Payouts (queue, float, guardian), **Payout health & operations**, **All payout settings**.

## Restore after a database restore / rollback
1. Put the app in maintenance mode.
2. `php artisan payouts:post-restore-check --since="YYYY-MM-DD HH:MM"` (the backup time). It switches **payouts and auto-approval OFF**, asks every provider about each open request and each one finalised since the backup, and lists mismatches. Nothing moves.
3. Read the list. `--apply` feeds the provider's answer through the normal confirm/fail path (never a re-send). Anything listed as "verify by hand": check the provider dashboard.
4. `php artisan payouts:invariants-check` must pass.
5. Re-enable payouts (and later auto-approval) in All payout settings. Stripe idempotency keys expire after ~24h, so Stripe checks rely on the stored reference, not keys.
Do a drill quarterly.

## Top up float
Admin -> Payouts -> Float -> record the top-up with the bank reference as the note. Waiting payouts resume oldest-first.

## A payout is stuck in "processing"
The stuck-payout watchdog alerts. If the rail has a lookup (Paystack) the reconciler resolves it. Otherwise open Payout health -> "Needs your decision": check the provider dashboard and mark paid / not sent with evidence.

## "Unknown outcome"
Never refund blindly. Provider dashboard first; then Payout health -> "It was paid" / "It did not go out" with the evidence in the note.

## Conflict alerts (`payout_confirm_conflict`, `payout_fail_conflict`)
The provider said the opposite of what we recorded. Nothing was changed automatically. Check the provider, then use Payout health (mark returned, or resolve) with evidence.

## A delivered payout came back
Payout health -> "A delivered payout came back": payout number + evidence. Money returns to the user once; the account is flagged (locked on a second return).

## Destination changed after the request
Payout health shows it with a red note. Contact the user. "It's genuine" re-freezes the new destination and returns it to the queue; otherwise decline and the funds return.

## "This wasn't me"
The user's payouts are frozen and cancellable requests cancelled. Make sure they reset their password. A super admin clears the freeze in Payout health with a note.

## Provider outage
Requests queue. Nothing is redirected. Alert after the configured minutes. Consider notifying affected users. Plan B: `PROVIDER-PLAN-B.md`.

## Rotate webhook secrets
Add the new secret in Admin -> Provider keys, update the provider's dashboard, then remove the old one. (A dual-secret window is not built; do it at a quiet hour.)

## Rotate the destination fingerprint key
Set `PAYOUT_FP_KEY`, then `php artisan payouts:reindex-fingerprints`. Safe to re-run.

## Change tier limits / turn auto-approval on or off
All payout settings -> Auto-approval. Keep shadow mode ON for 14 days / 100 payouts and read the decision log first. Pause quickly: Payouts -> "Pause auto-approvals".

## Investigate a user's payout trail
Payouts -> Decision log (search the user). Each decision lists the rules that fired. Payout health shows freezes and held requests.

## Month-end close
Payout health -> "Month-end accounting export" (CSV). Reconcile each provider statement in the same screen. Resolve every flagged item with a note.
