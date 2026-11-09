# Naara Top-Up (airtime and data) — master build notes

Built from `naara-airtime-data-integration.md`. **Master only for now**: it is tested here first, then ported to both forks **off by default and locked to the Extended licence** (`FeatureLocks::F_TOPUP`, locked at basic + standard, open at full).

## What a customer sees
`/top-up` (More menu, "Top-up"): country, phone number (E.164 via libphonenumber, region-checked), confirm the network (supplier lookup when it has one, otherwise the customer picks; never guessed from the prefix), airtime or data bundles, a price locked for 5 minutes, pay from the wallet. `/top-up/orders` and `/top-up/orders/{uuid}` are the history and a live receipt (polls while sending). Provider name, reference and cost are never rendered.

## Real-time offers
`TopupCatalogue::refresh($country)` asks the routed supplier(s) for the live list whenever a country is opened (cached `offer_cache_seconds`, default 120), upserts the normalised snapshot, retires what the supplier stopped listing (an empty answer retires nothing) and falls back to the snapshot if the supplier is down. A nightly `topup:run sync` refreshes every routed country and alerts if a country loses more than 30% of its products.

## Money path (blueprint rules 5-8)
`TopupOrderService::place`: order row + supplier reference first → atomic idempotent wallet debit (`topup:{uuid}`) → `SubmitTopupJob` (queued, unique). One door (`apply`) for submit, requery and webhooks. Delivered → done. Definitive failure → next supplier in the route chain with a **new** reference, else one keyed refund. Timeout/5xx/unknown → `pending`, requeried (1 min ×10, 5 min ×10, then 15 min), escalated after 30 min, **never auto-refunded or re-sent elsewhere**. Reversal → refund. Webhooks only name the order; the supplier is re-asked before money moves.

## Suppliers
| Supplier | Status |
|---|---|
| **Zendit** | Verified against the live API (offers by country, 121 countries, fixed + range, cost in USD, balance). Purchase/status/webhook shapes read from the docs; **no purchase has been placed** (balance is $0). RANGE `value` semantics (`type: PRICE`) unverified. |
| **VTpass** | Built to the spec (sandbox URL, auth headers, Lagos-time request_id, /pay, /service-variations, /requery, 040 reversal). **Unverified until a sandbox key is saved**: live base URL, data serviceIDs, status strings, webhook auth. |
| **Reloadly** | Built to the spec (OAuth2 with cached/locked token, /operators/countries, /topups). **Unverified**: Accept header value, auto-detect + status paths, bundle representation, duplicate `customIdentifier`, webhook. |
| **Ding** | Built to the spec (GetProducts, SendTransfer, ListTransferRecords, GetAccountLookup). **Unverified**: auth header name, ProcessingState/ResultCode enums. No webhook: polled. |

Webhook URL for each: `https://SITE/webhooks/topup/{zendit|vtpass|reloadly}?secret=<webhook secret>` (secrets saved under Admin > API keys > Airtime and data top-ups).

## Admin: Admin > Top-ups
Overview (KPIs, margin by supplier, reconciliation) · Suppliers (enable, float, minimum float, test connection) · Routes (country + type → ordered suppliers; no route = not sellable) · Pricing (markup, min profit, FX buffer, quote lock, offer cache, per-country/per-network markup overrides, failover switch) · Limits (per-order, daily/weekly, new-account, numbers/day, orders/hour, escalation, log retention) · Catalogue (live fetch, sync, per-product switch, private cost) · Orders (timeline, ask the supplier, refund with reason) · Supplier logs (redacted).
The master switch is the "Switched on" pill (feature `naara_topup`, default **off**). Customers see the friendly "coming soon" sheet until it is on **and** at least one supplier is enabled with keys.

## Operations
`php artisan topup:run seed|sync|float|reconcile|prune|margin`. Scheduled: requery every minute, float every 5 min, sync 03:40, reconcile 04:10, prune 04:30.

## Logos
`public/images/topup/logos/*.webp` + `database/seeders/topup-logos.json` (committed, no hotlinks). Names normalise through `TopupLogos::key` (aliases: etisalat/9mobile → ninemobile). An operator without a logo shows an honest initials tile. Source notes: Zendit has no brand art for top-up operators, so icons were collected from public brand icons and Wikimedia Commons and reviewed by eye; auto-generated letter placeholders were rejected.

## Before real money
1. Save sandbox keys (VTpass, Reloadly, Ding) and run the spec's sandbox matrix (success / pending / no-response numbers, duplicate reference, kill-the-worker).
2. Fund Zendit, buy one small NG and one small GH top-up end to end.
3. Pilot 100+ purchases per network per supplier; promote a supplier to primary only at ≥98% success.
4. Legal/KYB items in spec §15 (provider onboarding, Nigerian counsel on reselling top-ups, NCC/CBN refund framework).

## Fork port (after master is accepted)
Port as ordinary shared code (no master-only tokens), `naara_topup` default off, `F_TOPUP` already locked below Extended, plus enrolment in the licence/entitlement screens the way gift cards are. Not done yet.


## Round 2 (2026-11-03): number detection, people, auto top-up, hero, seeded offers and logos

**Number resolution** (`NumberResolver`, `/top-up`): the customer can type `0803…`, `+234 803…` or `2348031…`; the country is read from the number itself when it starts with `+`. The network is found in layers, strongest first: a live supplier lookup (Ding / Reloadly, when keys exist) → a past DELIVERED top-up to that exact number (`topup_number_operators`, so ported numbers are learnt) → the network saved on a beneficiary → the carrier allocation table (`database/seeders/topup-prefixes.json`, Google libphonenumber data for 99 calling codes, 21,718 prefixes, longest prefix wins). The screen says which layer answered and, when it was only the prefix, asks "is that right?" with the other networks one tap away. Once the network is known it shows both services for that line (Airtime and N data bundles). **Honest limit:** no offline table can be 100% right for a ported number; only a live HLR/lookup can. The learn-on-delivery layer is what makes a number right from its second top-up on, and the customer always sees and can change the network before paying.

**Beneficiaries** (`/top-up/people`, chips on `/top-up`): save a number with a name and its network, rename, remove, one-tap "Top up".

**Auto top-up** (`TopupAutoReloadService`, scheduled every 5 minutes): daily / weekly / monthly, at a chosen hour, airtime or a data bundle (matched again by name at run time because offers move), set when paying or on My people. Each run is an ordinary order (fraud limits, atomic debit, refund rules) with an idempotency key per scheduled slot, the next slot is claimed BEFORE money moves, a short wallet skips the run and tells the customer (a day-before low-balance heads-up too), 3 misses in a row pause the plan. Admin: on/off and the per-customer limit under Limits.

**Hero and banners:** an animated SVG (phone + airtime + data + auto-reload + refund shield) beside the headline, then a slidable, auto-playing carousel. Admin > Top-ups > Hero & banners: headline, line, upload many banners, caption/link per banner, order, autoplay and interval. Seeded with the four Numbers-hero banners (`public/img/topup/banner-*.webp`) until you upload yours.

**Offers seeded:** `database/seeders/topup-offers.json.gz` holds every Zendit offer (10,836 offers, 121 countries, all operators) captured live; `topup:run seed` and the admin panel load it into an empty catalogue. Live fetch replaces it per country as customers open them; `topup:run snapshot` re-exports.

**Logos:** 174 files seeded (170 of 202 operators). MTN, Glo, Airtel, 9mobile (and Ntel, Smile, Spectranet) come from the open-source PaystackHQ/nigerialogos set; Safaricom, Vodacom, Zain, Tigo, Moov and ~100 others from public brand icons and Wikimedia. In Nigeria "Etisalat" is shown as 9mobile. About 30 operators still show an initials tile: Admin > Top-ups > Logos lists every operator with a file input so you can upload any of them (an upload wins over the shipped file).

## Dashboard cards (round 3)
Home now shows Naara Line (permanent number) beside Verify/Rent/Gift/Top-up, each with skin-aware SVG art in all six art sets. Verify, Rent and Line deep-link to `numbers?modal=verify|rent|line`; Top-up goes to `/top-up`. Top-up and Line use custom duotone tile icons (`menu.app.more.topup`, `menu.app.more.product-line`).

## Landing page (`/naara-topup`, master only)
Premium sales page: live hero preview (example numbers resolved to real networks and bundles), live counts, rates and bundles explorer by country and usage style (chat and social, streaming, work and hotspot, always on), a "how far does your data go" slider, network logo wall, why-Naara cards, socials (only platforms linked in footer settings), FAQ and CTAs. Data is `TopupLanding` (cached 1 h, retail prices via QuoteService/PricingEngine, cost never read); wording is Site Editor page `naara-topup` (`TopupContent`); admin can switch the page off (PageSwitch `naara-topup`), which also removes the mega menu entry, the footer links and the in-app "Rates and bundles" link. Wired into the marketing mega menu (Products), mega footer (Products + Naara family) and the top-up screen. **Do NOT port to forks**: `TopupLanding`, `TopupContent`, `topup.blade.php`, the route, nav and footer entries are master marketing; only the top-up product itself goes to forks.

## Naara Credit discount on data (round 4)
Off by default (Admin > Top-ups > Pricing). Data bundles only, never airtime; auto top-ups never use it. The discount is computed at quote time from the LIVE cost and LIVE retail and is the smallest of: our profit share cap (default 20% of `retail - cost`), the profit above `max(min profit, credit min profit)`, a per-order USD cap (default $0.50), the Credits screen max-redeem %, and the customer's credit balance; it always rounds down. `price_usd` on quote and order is what the customer pays from the wallet, so debit, refund, caps and the profit report stay correct; `list_price_usd`, `credit_usd`, `credits` record the discount. Credits and wallet move in one transaction; a failed delivery returns both (credits keyed on the order, once). `place()` re-checks the minimum profit at payment time and refuses a quote that would sell below it.

## Paga payment gateway (wallet funding, Nigeria)
`PagaGateway` (Checkout link): callback hash `SHA512(amount + timeStamp + paymentReference + hashKey)` checked in constant time, the reference must be one we created, the paid amount must cover the charge, and Paga is asked directly (`POST /checkout/transaction/verify`) before any credit. Paga unreachable means pending, never a guess; `paga:reconcile` (every 5 min) credits paid intents exactly once. Wallet is credited the USD value locked at initiation. Admin > Payment gateways: sandbox/live toggle (beta-checkout vs checkout), the fee % we pay and an optional "add the fee to the payer's charge" switch (`GatewayFees`); System Verify warns when a product margin is thinner than a gateway fee. Keys: Admin > Provider Keys (public, secret, hash key).

## Paga as a supplier (Nigeria airtime and data)
`PagaTopupProvider` uses the Paga Business API with the SAME keys as the Paga gateway (Admin > Provider Keys: public key, secret key, hash key). Requests carry `principal` (public key), `credentials` (secret key) and `hash` (SHA-512 of the documented fields + hash key). Host follows the Paga gateway mode (`www.mypaga.com` live, `beta.mypaga.com` sandbox; override `PAGA_BUSINESS_URL`). Needs the server's outbound IP whitelisted by Paga (https://developer-docs.paga.com/docs/ip-whitelisting) and a funded Paga wallet. Set `PAGA_AIRTIME_DISCOUNT_PCT` to the commission in your Paga agreement (default 0: no assumed discount, margin-safe). Reference numbers are idempotent on Paga's side; an unclear answer stays pending and is resolved by `getOperationStatus`, never resent. Route priority for NG: VTpass, Zendit, Reloadly, Ding, Bitrefill, Paga (change in Admin > Top-ups). Probe 2026-11-04: the supplied keys are live-mode (sandbox host reports principal not found) and the live host rejects this container's IP as not whitelisted, as expected.
