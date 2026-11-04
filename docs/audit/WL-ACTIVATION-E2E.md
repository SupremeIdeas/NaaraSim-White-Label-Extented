# White-label activation: end-to-end test, security findings, and how the licences work

Run: 2026-11-03. Three real apps on one machine, each with its own database, cache, cookie and instance id: the **master** (licence authority), a **Normal** white label (`WL`, port 8101, domain `wl.localhost`), an **Extended** white label (`EXT`, port 8102, `ext.localhost`), plus a **pirate** copy of WL (port 8103) pointed at a fake platform. Screenshots: `docs/audit/e2e/` (30 captured screens, numbered in the order they happened) and the attack matrix.

## 1. How it works (verified in the run)

1. **Issue.** On the master (Admin > White-label oversight) an admin issues a licence at a tier (`normal` or `extended`). The key is shown once. Stored encrypted; a keyed hash is used to look it up.
2. **Activate.** On the install, Admin > Licence activation: paste the key and the platform address. The install sends brand, contact, version, **domain** and its own **instance id**. The platform returns an API token and a **signed entitlement** (level, locked features, skin allowance, expiry, licence reference).
3. **Unlock.** The install verifies the signature with the platform's public key and enforces exactly the signed list. Locked features answer a plain 404 (page and menu absent).
4. **Heartbeat.** Every 6 hours (and on "Check in now") the install reports in; the master records domain, version, last check-in. Each answer is re-signed with a fresh expiry (30 days).
5. **Oversight.** The master registry shows each install's domain, version, last check-in, and flags a different domain ("Domain mismatch") or a second install using the same licence ("Second install seen").

## 2. Basic (Normal) versus Extended, and paying the balance

| | Normal (`tier=normal`) | Extended (`tier=extended`) |
|---|---|---|
| Starting level | `basic` | `full` |
| Locked until paid | Gift cards, full voice eSIM (Connect), preloader customisation, Brand Hunt (admin can change these lists per level) | nothing |
| What the install enforces | the signed lock list | nothing is ever per-feature locked |
| Upgrade | master admin sets `standard` (gift cards stay locked), or the buyer pays the balance | n/a |

**Paying the full balance** (`WhiteLabelLicenseService::payBalanceAndUpgrade`, or `completeBalanceExternally` for a bank/stablecoin transfer): the wallet is charged atomically, the tier becomes `extended`, level `full`, the token and key are untouched (the install does not need to re-activate). The next check-in (up to 6 hours, or the owner presses "Check in now") delivers a new signed answer with no locks, and the features open. Proven in the run: screens 11 to 15 (master shows Extended/Full; the install still shows old locks until it checks in; after check-in "Every feature is switched on"; preloader and Brand Hunt change from 404 to 200).

Not tested in the run: the Extended-only update packages that become downloadable after an upgrade (the code gates them by tier, but no package was built in the lab); and the self-service funnel (merchant wallet, bank proof, proforma) which has its own earlier tests.

## 3. Findings (all verified by a test or a live request)

| ID | Sev | Finding | Result |
|---|---|---|---|
| F1 | P0 | An install that never activated had **every feature unlocked** (fail-open) | **Fixed for Normal**: with the platform key installed, no verified answer means the entry-level locks apply. Screens 4-5 (404 before activation). Extended is deliberately never locked: see decision D1 |
| F2 | P0 | A copy could point at its own fake "platform" that answers "nothing is locked" | **Fixed**: answers are signed (Ed25519). The pirate run (screens 19-20) shows "Answer could not be verified", nothing unlocked |
| F3 | P0 | Editing the install's database (clearing the stored lock list) unlocked everything | **Fixed**: the stored signed record is re-verified on every read; test `test_editing_the_database_cannot_unlock_anything` |
| F4 | P1 | A real signed answer for another licence could be replayed, and an old answer never expired | **Fixed**: answers carry the licence reference and a 30-day expiry; wrong reference, expired, or bad signature are refused (tests) |
| F5 | P1 | Licence keys stored in plain text | **Fixed**: encrypted at rest + keyed hash lookup; migration encrypts existing keys in place |
| F6 | P1 | Key guessing only limited to the general API limit | **Fixed**: 6 tries/min and 40/hour per address; unknown, revoked and suspended keys return the identical answer (A3-A6) |
| F7 | P1 | Same key used on a second install was invisible | **Fixed**: instance id recorded; a different id raises "Second install seen" and an admin alert; re-activating issues a new token and **kills the first install's token**, so one working install per key (A11, screens 16-18, 21-22) |
| F8 | P2 | After a revoke/suspend the install kept its unlocks until the signed answer expired | **Fixed**: a 401/403 from the platform drops the stored answer at once |
| F9 | P2 | Fork's shipped name ("Orbit") overwrote the customer's real brand on the master | **Fixed** (found in screen 11) |
| F10 | P2 | Success banner showed even when the proof failed | **Fixed** |
| F11 | P2 | An instance (token) calling the audit logger crashed it | **Fixed** (only real users are logged as actors) |
| F12 | P2 | `CACHE_STORE=file` ignores `CACHE_PREFIX`: two installs in one code folder share cache. (Separate folders and the `database` store are safe.) | Documented; lab uses the database store. This is the same class as the rehav.online incident: never run two installs from one folder |

Attack matrix (live master; image `attack-matrix.png`): no token 401; invented token 401; unknown key 403; SQL text as key 403 with the same message; 200-character key 422; ten guesses throttled after the second; a token from another domain/instance gets through but is flagged; a token cannot fetch packages (404); a copy re-activating kills the original token.

## 4. What this cannot do (honest limits)

* Anyone who holds the PHP source and edits the code on their own server can remove the check. No licence system for shipped source can stop that. What these defences do: stop a fake platform, stop database/config tampering, stop replay, make sharing visible (domain mismatch, second install, token replacement), keep updates and themes tied to a live signed licence, and make the contract evidence clear.
* Domain mismatch and second install are **flagged, not blocked** (a legitimate server move looks identical). Staged enforcement (warn, restrict updates, suspend) is not built.
* If an install has no platform public key (`NAARA_LICENCE_PUBLIC_KEY`), it cannot tell a real answer from a forged one: it falls back to the old behaviour and the activation screen says so in amber.

## 5. Going live (required once)

1. On the master: `php artisan licence:keygen`; put `NAARA_LICENCE_PRIVATE_KEY` in the master `.env` only; `php artisan migrate`.
2. Put the printed `NAARA_LICENCE_PUBLIC_KEY` into every white-label build's `.env` (or ship it as the config default in the next build). Without it the install is not protected.
3. Existing installs: activate once from Admin > Licence activation (or keep the old `.env` token; then the signed answer binds on its first check-in).


## 6. Purchase flow, one licence per site, key reveal (added 2026-11-03)

* **Buying.** The buyer pays (wallet, card or approved transfer) and lands on "See your licence". The page shows **Activation credentials**: status, platform address, the key masked (`NAARA-••••-••••-XXXX`), the site it is activated on and last check-in. **Reveal key** needs the buyer's account password (5 tries per 10 minutes), lasts 2 minutes, is audited, and the key is not in the page until revealed.
* **Admin.** Master > White-label oversight shows the same key masked in each row with an **eye**: click to show (audited, super admin only), click again to hide; it hides itself after 2 minutes. A newly issued key is still shown once in the banner.
* **Domain proof.** Activation sends the install's domain and id; the oversight row shows the domain (searchable by domain), version and last check-in the moment it activates.
* **One licence, one site.** After the first activation the licence is bound to that domain and install. Any other domain, another install, a disguised domain, or an activation that leaves them out answers **409 "already activated on another site"**, is recorded and alerted. A token copied to another server is refused on check-in (403). The same site can activate again. Moving servers is an admin action: **Release site** (super admin) clears the binding and kills the token; the next activation binds the new site and the old site is then refused. Proven in the run: second site refused, original unaffected, release, new site active, old site "Platform no longer accepts this install".
* Activation now requires `domain` and `instance_id` (setting `white_label.require_domain_on_activation`, on by default).

## 5b. Decisions for the owner (recommended default first)

* D1: an **unactivated Extended** install currently runs fully with no gate at all. Recommended: 14 days grace, then a persistent admin banner and updates/themes withheld (never a hard lock).
* D2: staged enforcement for mismatch/clone (warn, then restrict, then suspend) with admin-set timings. Recommended: warn immediately, restrict updates after 7 days, never auto-revoke.
* D3: licence key delivery to buyers (secure link vs email the key), and re-auth to reveal the key on the merchant screen (not built).
