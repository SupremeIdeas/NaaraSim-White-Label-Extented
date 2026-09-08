# NaaraSim Wizard — Design & Research (for later build)

> **Status: DESIGN for later build. Admin-toggleable.** A guided, chat-style
> assistant that helps a user secure an **eSIM**, a **virtual number**, or an
> **OTP** end-to-end inside a floating widget, with almost no thinking required.
> **Logic-first, Claude-light** (§8). Nothing here is built yet.
>
> **This rewrite is grounded in the ACTUAL code** — the model↔provider mapping,
> availability rules, and the build order below all match what `SmsNumberRouter`,
> `ProviderRouter`, the provider services, and `ProviderKeys` really do. Where a
> capability is **not yet wired in the codebase**, it is flagged ⚠️ so the wizard
> never promises something the platform can't deliver.

---

## 0. Principles

1. **Deterministic state machine, not LLM freestyle.** Every step has fixed
   options + validation. Claude is used **only** to (a) map a free-text answer to
   a fixed option and (b) answer a short question — both cached, both optional,
   with a **button fallback** so the wizard is fully usable with Claude **off**.
2. **Never expose suppliers.** Users see **Model** names (§2), never "5sim",
   "eSIM Go", "Twilio", etc.
3. **Reuse the real engines.** Ordering → `SmsNumberRouter` / `ProviderRouter`;
   pricing → `PricingEngine` (MarginGuard floor); money → `WalletService`;
   credits → `CreditService`; device check → `DeviceCompat`; support → NaaraCare
   (`/support`). The wizard is a *guided front-end* to code we already have.
4. **A Model shows only if it can actually be fulfilled** — see the availability
   rule in §2.4.

---

## 1. What the wizard sells (matches the code exactly)

- **eSIM data plans** — fulfilled by the eSIM failover chain **eSIM Go → Airalo →
  Quibity** (`ProviderRouter`); the user picks a *plan* (already masked to
  name + price), not a provider.
- **Numbers**, by the real `NumberRequest` types + `SmsNumberRouter` lanes:
  - **OTP** (`TYPE_OTP`) — US lane `getatext → fivesim → smsactivate`; global lane
    `fivesim → smsactivate`.
  - **Rental** (`TYPE_RENTAL`) — US lane `getatext → fivesim`; global lane
    `fivesim → smsactivate`.
  - **Permanent** (`TYPE_PERMANENT`) — lane `twilio → telnyx` (voice + 2-way SMS).
    ⚠️ **Not purchasable yet** — see §12/§13.

---

## 2. The "Model" layer (accurate to the lane architecture)

The existing system already does **capability routing**: it picks the provider
that owns a *(country, type)* and fails over **within that lane**. So a **Model is
a capability line backed by a lane of real providers** — *not* one provider per
model. This masks suppliers, matches the code, and lets a Model stay available as
long as **any** provider in its lane is configured.

### 2.1 The Models (what the user picks by purpose)

| Model (public) | Capability | Backed by (internal lane — never shown) | Number-pattern match? |
| --- | --- | --- | --- |
| **Naara Data** | eSIM **data** (local / regional / global plans) | eSIM Go → Airalo → Quibity | — |
| **Naara Verify** | **OTP** / verification codes | US: Getatext → 5sim → SMS-Activate · Global: 5sim → SMS-Activate | ❌ (provider assigns the number) |
| **Naara Rent** | **Short-term rental** numbers | US: Getatext → 5sim · Global: 5sim → SMS-Activate | ❌ |
| **Naara Line** | **Permanent** number + **voice** + 2-way SMS | Twilio → Telnyx | ✅ (Twilio/Telnyx number search) |

> Four Models map **1:1 to the real product types** (eSIM + the three number
> types). Admin can rename any Model and edit its descriptor/icon. (If admin ever
> wants to expose provider choice *inside* a lane — e.g. "Naara Line · US-direct"
> — that's an optional future "edition" sub-level; the default is one Model per
> capability, which is the clean UX and the accurate mapping.)

### 2.2 Capability flags (drive the whole wizard — pure data, no guessing)

`esim_data, otp, rental, permanent, voice, two_way_sms, number_search, renewable,
data_with_number, countries[]` + live price via `PricingEngine`. The wizard shows
only the options a Model's flags allow, and recommends the Model whose flags match
the user's purpose.

### 2.3 Purpose → Model matching (the "sorry, the model that supports this is X")

The user picks a **purpose**; the wizard maps it to the Model(s) whose flags
support it. If they'd chosen a Model that doesn't support a later choice (e.g.
they picked **Naara Verify** but then ask for a permanent number), the wizard says
*"Sorry — the Model that does that is **Naara Line**"* and lists matching Models
with what each does. Pure flag lookup — deterministic.

### 2.4 Availability rule (the masking you asked for)

A Model is **shown only when it can actually be fulfilled**:

- **`available = any provider in the Model's lane has its API key configured`**
  (via `ProviderKeys::hasValue()`), **and** — for a specific request — the chosen
  **country is served by that lane**.
- If **no** backing provider is configured, the Model is **hidden entirely** (the
  user never sees it). Example: if only 5sim is configured, **Naara Verify** and
  **Naara Rent** still appear (backed by 5sim); if neither 5sim, SMS-Activate nor
  Getatext is configured, both are hidden. **Naara Line** appears only when Twilio
  or Telnyx is configured **and** permanent purchase is wired (§12).
- This is exactly "only the available API, with a Model name masking the
  supplier" — and because a lane can have several providers, one supplier being
  absent doesn't kill the Model as long as another in the lane is present.

---

## 3. The wizard workflow (state machine)

Each `→` is a state with fixed options; free-text is parsed to an option (§8).

1. **Orientation** (auto-opens on first registration). One dismissible card: what
   NaaraSim is (eSIM data + numbers, one wallet).
2. **Purpose** — options built **only from Models that are available (§2.4)**:
   *Get an OTP code · Rent a number · Get a permanent number · Get eSIM data*.
3. **Recommend the Model** for that purpose (+ why); user confirms or switches to
   another supporting Model.
4. **Country** — type it or use the in-chat country selector (searchable). Only
   countries the chosen Model's lane serves are selectable.
5. **Refine** — service (WhatsApp, Google…) for numbers; plan filter
   (local/regional/global) for eSIM. If a choice isn't supported by the Model, do
   the §2.3 redirect.
6. **Number matching** — **only for Naara Line** (`number_search = true`): "Type a
   number you'd love (or your local number); we'll find the closest." Explains the
   match may be on the **last/first 4–7 digits** (country code will differ); user
   can list 2–3 numbers to widen. No match → apologise + offer another country
   code/Model, with a caution to choose deliberately so the number still suits
   their use-case. **For Naara Verify/Rent the provider assigns the number — the
   wizard skips this step and just shows what's available.**
7. **Choose result** — show available number(s)/plan(s) with price via
   `PricingEngine` (never cost) → **Get this**.
8. **Balance check** — enough wallet balance (topped up via a gateway; NaaraCredits
   may apply within the Model's margin cap). If short: *"Not enough balance —
   minimise the wizard, top up, then tap the floating widget. Your progress is
   saved. Hurry: someone else may grab this number."* Session persists (§7); on
   return the wizard re-checks availability.
9. **Purchase** — order via the real router; item lands in the **dashboard** (§9)
   and shows **copyable** in the widget.
10. **OTP** — the code can be **pushed from the dashboard to the widget** for
    one-tap copy (same code both places); the user can paste a number back to
    request another OTP where the Model allows.

### eSIM path
Step 5 becomes a **device-compatibility check** (§4) before showing plans; on
success the eSIM (QR / LPA) appears in the dashboard + is copyable in the widget.

---

## 4. eSIM device-compatibility (reuses `DeviceCompat`)

`DeviceCompat::check()` already returns supported / not / unknown from a device
list. In the wizard: type the phone or pick from a shown list →
- **supported** → auto-confirm; **not supported** → block + explain;
- **unknown** → guide to dial **`*#06#`**: a **32-digit EID** = eSIM hardware
  present; no EID = none. Facts to bake into the copy/list: iPhone XS/XR (2018)+,
  Samsung S20+, Pixel 3+, most 2020+ Androids; **caveats**: mainland-China iPhones
  have no eSIM; carrier-locked phones only accept the locking carrier's eSIM. Keep
  the list maintained in `DeviceCompat`. **This mirrors the existing checkout gate
  — no new risk.**

---

## 5. Number matching — real capability (source of truth)

Pattern matching is a **permanent-number** capability (Naara Line), confirmed in
the code: `TwilioService::searchNumbers()` and `TelnyxService::searchNumbers()`
exist. Twilio supports match patterns (`* % + $`, `+`=starts, `$`=ends) + Near
Number; Telnyx supports starts_with / ends_with / contains (last 4). **Naara
Verify / Rent** (5sim / SMS-Activate / Getatext) **assign** a number for the
service+country — no custom pattern — so the wizard offers matching **only when
`number_search = true`**. ⚠️ Both Twilio/Telnyx `searchNumbers()` are provider
stubs to be wired at go-live (rule 1.1); see §12.

---

## 6. Wizard pricing (transparent)

Free for the first **3** completed wizard sessions; after that **$0.45** per
wizard-completed purchase, shown up front (*"a small $0.45 — not even a dollar —
supports the Wizard doing the heavy lifting; prefer to skip it? Use the dashboard
free"*). It's a line item (never hidden), charged at purchase; the dashboard path
is always free. Track `wizard_uses` per user (window/reset admin-set).

---

## 7. Session persistence

`wizard_sessions` row per user (state, answers, selected item, TTL). On
minimise/top-up/return the widget rehydrates. Number selections are **soft-held
only as long as the provider allows**; on resume the wizard re-validates
availability and, if gone, apologises + re-lists (matches the copy). Never charge
for a number that's no longer available.

---

## 8. Claude usage strategy (keep it cheap)

- **Buttons work with Claude off** — full functionality without the LLM.
- **Claude only for**: (a) free-text → fixed option, (b) short Q&A. Via the
  existing `AnthropicClient`, **cached** by normalized input, tiny `max_tokens`,
  **button fallback** on any error/no-key.
- **No Claude in the money path** — pricing/ordering/balance are pure code.

---

## 9. Dashboard reorganisation (sort by type + Model)

- **Top tabs:** **eSIMs** · **Numbers** (each item tagged `eSIM` / `Number`).
- **Numbers grouped by type → Model badge:** *Permanent* (Naara Line), *Rental*
  (Naara Rent), *OTP* (Naara Verify). No mixing across types.
- **State:** active · awaiting OTP · expiring soon · **expired → Archive**. Models
  that support **renewal** show **Renew**; permanently-expired items are labelled
  and archived (kept for history, out of the active view).
- eSIMs: active vs archived, Model badge (Naara Data), data left, QR/LPA, renewal
  where supported.
- Data model: `sms_orders`/`esim_orders` already carry provider + type; add
  `model_key` (public Model), `archived_at`, `renewable`, and surface `expires_at`
  so the UI sorts deterministically.

---

## 10. NaaraCare handoff

Anything the wizard shouldn't answer (billing dispute, refund, account issue) →
one-tap navigation to **NaaraCare** (`/support`), with context passed so the agent
starts warm.

---

## 11. Widget UX

Floating bottom-right, not blocking content; **animated glowing brand-colour
border** (lightweight moving gradient; reduced-motion → static). **Collapse** via
an X at the top; **re-open** from a labelled header icon. Loading states on every
action (money actions disable while in flight). SVG icons only, dark-mode parity.

---

## 12. ⚠️ Codebase dependencies the wizard needs (must exist first)

These are **real gaps in the current code** the wizard depends on:

1. **Permanent-number purchase is NOT wired.** `TwilioService::buyNumber()` /
   `sendSms()` throw "not wired yet", and `SmsNumberRouter::order()` throws
   "Permanent numbers are coming soon." → **Naara Line cannot transact yet.** Until
   it's wired (Twilio/Telnyx provisioning + monthly billing + `searchNumbers`
   pattern search), the wizard should present **Naara Line as "coming soon"** and
   launch with **Naara Data / Verify / Rent** only.
2. **Number-search (`searchNumbers`)** must return real results before the §6
   matching step is offered.
3. **OTP push to widget** needs the existing `PollSmsOtpJob` result surfaced to the
   wizard channel (Livewire event / poll) — small, but a dependency.
4. **eSIM QR/LPA** already flows to the dashboard; the wizard just mirrors it.

---

## 13. Build sequence — what to build BEFORE the wizard (the intentional order)

**The wizard is NOT first.** It sits on top of a small foundation that *also*
upgrades the existing app. Recommended order:

1. **Model registry + availability layer (FIRST — the keystone).** A data layer
   that defines the 4 Models, their capability flags, their backing lanes, and
   `available()` (any backing key configured + country served). **Apply it to the
   existing catalogue, numbers page, and dashboard immediately** — it delivers
   supplier-masking + organisation *without* the wizard and de-risks everything
   after. Small, safe, high value.
2. **Dashboard reorganisation** (§9) — group by type + Model, archive/renewal.
   Independent of the wizard; makes the whole app cleaner.
3. **Wire the permanent-number flow** (§12.1) — Twilio/Telnyx provisioning,
   monthly billing, `searchNumbers`. Needed before **Naara Line** is real. (If
   you'd rather ship the wizard sooner, do this *after* step 4 and keep Naara Line
   "coming soon" in the meantime.)
4. **Wizard state machine** (buttons-only, Claude off) over the Model registry +
   existing routers + wallet/credits, with save/resume and the dashboard drop.
5. **Wizard polish** — Claude NLU sprinkle (cached, fallback), number matching
   (once §3 is live), device check, the $0.45 fee, NaaraCare handoff, glow widget.

**So: Model registry → dashboard reorg → (permanent-number flow) → wizard core →
wizard polish.** This is deliberate: the Model layer and dashboard reorg pay for
themselves even before the wizard, and the wizard only turns on capabilities that
are genuinely wired.

## ⚠️ Build-safety notes (wait-for-later)

- **Don't ship Naara Line in the wizard until permanent purchase is wired** — mark
  it "coming soon" so we never take money for something we can't provision.
- **Soft-holds are best-effort** — always re-validate availability on resume.
- **Number-match syntax differs** by provider (Twilio meta-chars vs Telnyx
  literals) — abstract behind the Model; never leak provider syntax.
- **Data+number bundles** — only expose when a Model's flag proves the provider
  really bundles it (none of the current lanes do by default).
- **Keep Claude optional** — no purchase may depend on an LLM response.

## Sources

- Twilio — [AvailablePhoneNumber (match patterns, NearNumber)](https://www.twilio.com/docs/phone-numbers/api/availablephonenumberlocal-resource)
- Telnyx — [Advanced number search](https://developers.telnyx.com/docs/numbers/phone-numbers/advanced-number-search)
- eSIM device support + `*#06#`/EID — [eSIM compatible phones 2026](https://www.easysim.global/blog/esim-phones)
- 5sim — activation (OTP) + hosting (rental) API (as used by `FiveSimService`)
