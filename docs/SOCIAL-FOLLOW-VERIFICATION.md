# Social-Follow-to-Earn — Per-Platform Verification Decision

> BUILD-6 §C.2. This is a **money-adjacent** feature: a confirmed follow grants
> NaaraCredits (which reduce what a user pays). So we do NOT ship a blind
> "trust-the-click" version. This file records the deliberate per-platform
> decision made with the owner (Frank), so the choice is explicit, not guessed.

## Owner decision: **Hybrid + per-platform admin toggle**

- Where a platform exposes a real "does user X follow account Y" check, use it
  (**API-verified**).
- Where it does not, use an explicit **self-confirmed** tap — clearly tagged in
  the UI as self-confirmed (distinct from API-verified), still one-time and
  enforced server-side.
- Every platform has a per-handle **admin on/off** and an admin-set
  `credit_reward`, so the owner can disable any platform (or lower its reward)
  at any time without a deploy.

The verification mode is stored per handle as `verification` (`self` | `api`).
Handles ship as `self` today; flipping a handle to `api` is a data change once
the OAuth path for that platform is wired (see "Deferred" below).

## Per-platform findings (checked mid-2026)

| Platform  | Real follow-check API?                                             | Mode shipped |
|-----------|-------------------------------------------------------------------|--------------|
| X (Twitter) | Yes — OAuth 2.0 `users.read` + `follows.read`; user connects once. | self (api-ready) |
| YouTube   | Yes — Google OAuth + Data API `subscriptions.list`.               | self (api-ready) |
| Instagram | No public "does user follow account" endpoint for third parties.  | self |
| TikTok    | No public follow-check for non-business use.                      | self |
| Facebook  | No third-party follow-check.                                      | self |
| LinkedIn  | No third-party follow-check.                                      | self |
| Threads   | No public API for this.                                           | self |
| Snapchat  | No third-party follow-check.                                      | self |

## What "self-confirmed" means here (anti-abuse, even without an API)

- The handle opens in a new tab/app; on return the user taps **"I followed —
  confirm"**. The credit grant happens **server-side only**, triggered by that
  confirm action, never by merely opening the link.
- The claim is **one-time and unrepeatable**: a unique `(user, handle)` row plus
  an idempotent `CreditService::earn()` reference (`social_follow:{user}:{handle}`),
  both written in one DB transaction. A retried/double-tapped request can never
  double-grant, and a claimed button is re-checked server-side on every render
  (a client can't re-enable it).
- The reward amount is a **surprise** — never shown before the follow; revealed
  only in the success message after the claim lands.

## Deferred (API-verified path)

X and YouTube can be upgraded to `api` verification later by adding their OAuth
connect flow and calling `follows.read` / `subscriptions.list` in the confirm
step, gated behind the same one-time claim. The data model already carries the
`verification` field so no schema change is needed to turn this on per handle.
Until then, those handles run as `self` like the rest.
