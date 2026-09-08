# End-to-end regression sweep log (BUILD-5 §7)

One coherent pass across every money + identity path, run in a single sitting,
so each round of changes has a real baseline instead of starting from zero trust.

Two kinds of check live here:
- **Automated** — the PHPUnit suite, runnable now, on every change.
- **Live-sandbox** — real sandbox transactions through the real providers/gateways.
  These need SANDBOX keys configured and must be executed by the operator; they
  cannot run in CI without those keys. Record each run below.

---

## Automated baseline

| Date | Result | Notes |
|------|--------|-------|
| 2026-08-04 | **1053 passed (3448 assertions)** | `php artisan test` (in-memory sqlite). Baseline after BUILD-5 §2–§4 + BUILD-4. |

Re-run `php artisan test` before every release; append a row. A drop from this
baseline is a regression to investigate before shipping.

---

## Live-sandbox sweep — operator checklist

Run all of these in ONE sitting on a sandbox-keyed environment, then log the run
in the table below. Do **not** rely on each earlier build's isolated checklist.

1. **Money in** — a real sandbox transaction through **every** payment gateway,
   confirmed via **webhook** (not just the redirect): wallet balance updates, a
   `wallet_transactions` row is written, and a simulated webhook **retry** does
   **not** double-credit (idempotency).
2. **Money out** — a payout through at least one payout-capable gateway per
   supported region: the KYC gate fires at the right point in the merchant flow,
   and the "Recommended — fast payout" badge reflects real volume.
3. **Identity** — a full individual **KYC** flow and a full business **KYB** flow
   end-to-end, each including a **deliberately-failed** verification, confirming
   the fast-fail path reaches a human review queue.
4. **Uploads** — every upload surface (app icon, KYC document, chat attachment,
   voice note, avatar) tested with **zero Wasabi credentials** configured
   (graceful degradation, no crash).
5. **Native app** — if the Android pipeline is live, a real device install from a
   freshly generated build: icon/splash/version reflect current admin settings,
   not a stale `cap sync`.
6. **Admin alerting** — deliberately trigger one low-balance provider alert and
   one chargeback (or simulated), and confirm both reach an admin **promptly**
   (web push + email — BUILD-5 §3).

### Live-sandbox run log

| Date | Operator | 1 In | 2 Out | 3 ID | 4 Uploads | 5 Native | 6 Alerts | Notes |
|------|----------|------|-------|------|-----------|----------|----------|-------|
| _pending_ | | | | | | | | First full live-sandbox sweep not yet run — execute before go-live. |

---

## Baseline installer (§8)

Once a live-sandbox sweep passes, package the finished platform as the new
white-label baseline installer ZIP using the first-party packager:

```bash
scripts/package-release.sh
```

(Integrity check → `composer install --no-dev` → `npm ci && npm run build` →
runtime dirs → ZIP. See `docs/CPANEL-INSTALL.md`.) Record the packaged version
here so future white-label installs start from a known-good baseline.

| Date | Version | Packaged by | Notes |
|------|---------|-------------|-------|
| _pending_ | | | Package after the first passing live-sandbox sweep. |
