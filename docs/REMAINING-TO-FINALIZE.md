# NaaraSim — Remaining to Finalize (Go-Live Checklist)

> Snapshot as of 2026-07-18. The **application is built and green** (full test
> suite passing, CI green). What remains is mostly **configuration, real
> credentials, content, and launch ops** — not new core code. Ordered by what
> blocks a real launch first.

---

## 1. Credentials & environment (blocks everything money/data) — DO FIRST

Nothing transacts until these are set. Use **SANDBOX keys first**, switch to live
only after the hardening pass (§6).

- [ ] **Provider API keys** (Admin → API keys), each with its webhook secret:
  - eSIM: **eSIM Go** (primary), **Airalo** (secondary), **Quibity** (tertiary)
  - Numbers: **Getatext** (US), **5sim** (global), **SMS-Activate** (backup),
    **Twilio** (voice/permanent), **Telnyx** (backup)
  - Payments: **Paystack**, **Flutterwave**, **Stripe** (+ each webhook secret/hash)
  - **Anthropic API key** → lights up *Plan Price with Claude*
  - **Cloudflare Turnstile** (bot protection), **ElevenLabs** (voice support),
    **Google OAuth** (social login), **GitHub maintenance token** (§6 loop)
- [ ] **Production `.env`**: `APP_ENV=production`, `APP_DEBUG=false`, real
  `APP_URL`, MySQL 8, Redis, **Wasabi S3** keys, `ADMIN_PATH`, SMTP mail, Sentry DSN.
- [ ] **Register webhook URLs** in each provider dashboard and confirm the HMAC
  secrets match what's saved in the panel.
- [ ] **Catalogue sync**: run `php artisan esim:sync` to replace the demo plans
  with real provider plans/prices; then let *Plan Price with Claude* set retail.

## 2. Money-path verification on sandbox (before any real charge)

- [ ] Top-up end-to-end (Paystack/Flutterwave/Stripe) → confirm the wallet is
  credited by the **webhook** (not the redirect).
- [ ] Buy eSIM, buy number, issue a refund, redeem NaaraCredits — confirm the
  wallet ledger, orphan-charge guard, and MarginGuard floor all hold.
- [ ] Confirm every external call runs on the **queue** (Horizon up) with retries.

## 3. Feature gaps still open (nice-to-have before/after launch)

- [ ] **NaaraCredit redemption at the NUMBER checkout** (eSIM checkout has it;
  the number flow refunds correctly but doesn't offer credit redemption yet).
- [ ] **Real-time top-up success toast** — today top-up success is confirmed by
  email + balance (it's async via webhook). Optional: push a hero toast from the
  webhook via a broadcast/echo channel.
- [ ] **Rewarded-ad / offerwall live provider** — the earning system is built and
  compliant; needs a network account (AdGate/AdGem/BitLabs/CPX/Pollfish/Tapjoy)
  + its postback secret in config, then flip it on.
- [ ] **Product reviews / ratings** (UI-kit star rating already exists).
- [ ] **Full i18n + multi-currency** (NGN/USD live; more languages/currencies).
- [ ] **Passkey management UI** (Fortify passkey routes exist; needs a settings screen).
- [ ] **Payment-gateway icon overrides** (admin-uploadable marks — audit §5).

## 3b. Future expansion layers (designed, not built — admin-toggleable)

Full design/research specs (each researched with real provider sources):

- [ ] **Payouts + merchants + developer API** — `docs/ROADMAP-PAYOUTS-MERCHANTS-API.md`
  (NaaraCredit→cash for first-referral credits; developer API reselling at
  wholesale + markup; KYC-gated co-branded merchant/reseller system).
- [ ] **Extra payment gateways (collection + payout)** — `docs/ROADMAP-PAYMENT-GATEWAYS.md`
  (Stripe payout, PayPal, Binance Pay, Cryptomus, CoinPayments, Payssion).
- [ ] **NaaraSim Wizard** — `docs/ROADMAP-NAARASIM-WIZARD.md` (guided chat that
  secures eSIM/number/OTP; provider "model" nicknames; logic-first, Claude-light;
  reorganised dashboard; wizard fee free-3×-then-$0.45).
- [ ] **Admin setup wizard, staff/user management, partners** —
  `docs/ROADMAP-ADMIN-TEAM-PARTNERS.md` (first-run guided setup; money-safe staff
  tools with evidence-gated pending-refund fix; partner earnings + payouts).

## 4. Content & brand finalization

- [ ] **Legal pages** — Terms, Privacy, Refund policy: CMS is built; needs final
  copy (ideally reviewed by a lawyer for NG/GDPR).
- [ ] **Marketing / blog copy** final pass; home/about/how-it-works polish.
- [ ] **Coupon codes** attached to promo banners (e.g. the Global Data Sale banner)
  if you want the tap-to-copy chip.
- [ ] **Splash screen** assets confirmed (Admin → Splash).

## 5. Launch ops (Sections 28–32)

- [ ] **Queue worker / Horizon** running under a supervisor in production.
- [ ] **Scheduler (cron)** wired: `php artisan schedule:run` every minute for
  catalogue sync, OTP polling, DB backups, credit resets.
- [ ] **DB backups to Wasabi** (spatie/laravel-backup); on shared cPanel confirm
  the **PHP-fallback dumper** works (no mysqldump).
- [ ] **HTTPS + security headers + rate limits** verified on the live host.
- [ ] **Sentry** DSN set for error monitoring.
- [ ] **Claude maintenance loop** (§29) — Anthropic + scoped GitHub token + CI so
  fixes go to a PR, never straight to prod.

## 6. Hardening pass before real keys (audit §9 / S30)

- [ ] Swap sandbox → **live provider/payment keys** (only after §2 passes).
- [ ] **Nonce-based CSP** — remove `unsafe-inline`/`unsafe-eval`.
- [ ] `/api/user` → return **401 JSON** for unauthenticated API (audit F2).
- [ ] Rotate provider/wallet keys; confirm 90-day rotation reminder.
- [ ] Final OWASP matrix review (S30); 2FA required for admins in prod.

## 7. Release packaging (CodeCanyon-style, optional)

- [ ] Test the **install wizard** on a fresh cPanel **and** a fresh VPS.
- [ ] Buyer documentation + demo data toggle.
- [ ] Load/scale sanity check toward the 1M-user target (indexes, Redis, queues).

---

### Already done (for reference)
Foundation → PricingEngine + MarginGuard → WalletService → eSIM & number
providers + routers → checkout (eSIM + number) → wallet/top-up → coupons +
banners → NaaraCredits loyalty + rewards → transactional emails → admin panel,
staff/roles, backups, account lifecycle (GDPR) → brand system (logos, colours,
preloader) → transaction hero toaster → **AI Pricing Architect (Plan Price with
Claude)** → brand promo banners (image + **video**) → **collapsible sidebar**.
Full test suite green; CI green.
