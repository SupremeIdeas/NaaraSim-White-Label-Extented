# NaaraSim — Merchant White Label Purchase & Onboarding Guide

This is the handoff document for a **Merchant V2** account buying a
white-label license: what you're buying, what the purchase form asks for,
what you're responsible for while we build your platform, and exactly what
to check the moment you get your own live login. It's written to be handed
to a merchant directly — where a step is genuinely manual on our side rather
than instant/automatic, this guide says so, because "your platform is
almost ready" should never mean "please keep guessing."

Two logins matter here and they are **not the same thing**:

1. **Your NaaraSim merchant dashboard** — `/merchant/white-label` inside your
   normal NaaraSim account. This is where you request a license, pay for it,
   and submit your brand/hosting details. It never goes away, even after
   your platform is live — it's also where you'd pay to upgrade tiers later.
2. **Your own white-label platform's admin panel** — a completely separate,
   independently running copy of NaaraSim, on its own domain, with its own
   database and its own admin login. This is the thing we actually build for
   you. You only get access to it once deployment is finished.

Everything up to "Step 4" below happens in login #1. Step 4 onward happens
in login #2.

---

## What you're buying

A white-label license is sold against one of four seeded plans. **Tier**
(which product line you get) and **entitlement level** (which features are
unlocked) are two different things — buying Extended automatically gives
you full entitlement, but the two are tracked separately under the hood so
support tiers can differ without changing what's unlocked.

| Plan | Price | Tier | Support | What tier means |
|---|---|---|---|---|
| Basic | $1,500 | Normal | Standard | The `NaaraSim-WhiteLabel` product line |
| Medium | $2,500 | Normal | Standard | The `NaaraSim-WhiteLabel` product line |
| Extended | $5,000 | Extended | Standard | The `NaaraSim-White-Label-Extented` product line, all features unlocked |
| Extended V2 | $7,500 | Extended | **Priority** | Same product line and same unlocked features as Extended — the $2,500 gap buys priority support from Supreme Ideas Agency, nothing more |

Prices and plan descriptions are admin-tunable and may differ slightly from
the table above by the time you buy — always check the live plan carousel
in your dashboard, not this document, for the current number.

**Optional add-on:** a custom theme request (None / Basic $1,200 / Elegant
$2,900 / Premium $4,000), billed together with your license in the same
charge. Picking a paid theme tier does **not** count toward upgrading from
Normal to Extended later — theme spend and license spend are tracked on
separate ledgers on purpose, so a merchant can't buy their way to Extended
early by over-spending on a theme.

---

## Step 1 — Choose a plan and request your license

In your merchant dashboard's **White Label** tab:

1. Pick a plan from the carousel.
2. Choose your **hosting preference** — Naara's own server, or your own
   VPS/shared hosting. This is a soft preference at this stage; you'll
   confirm it for real in the project intake form in Step 3.
3. Acknowledge the hosting disclaimer checkbox (required to proceed).
4. Optionally pick a custom theme add-on tier.
5. Click **Request**.

This does **not** charge you anything yet. It creates a pending request and
you'll see: *"Request submitted. An admin will confirm pricing shortly."*
That review is a real human step on our side — normally quick, but it is
not instant, so don't expect a price to appear the second you submit.

---

## Step 2 — Pricing confirmation & payment

Once we've reviewed your request, your dashboard updates to show a
confirmed price and a **Pay Now** button. The total charged is your license
price plus your theme add-on (if any), in one combined charge against your
NaaraSim wallet — **make sure your wallet is funded before you try to pay**,
since a failed charge just leaves your request sitting at "ready to pay."

The instant payment succeeds:

- Your license activates immediately (no further review needed).
- Your **entitlement level** is set automatically from your plan's tier —
  Basic/Medium start at `basic` (see the feature table below); Extended and
  Extended V2 start at `full` (nothing locked) — with zero extra steps on
  either side.
- A durable **license key** is generated behind the scenes. You will not see
  it on this screen — see "Credentials" below for why, and who does.

**Upgrading later (Basic/Medium → Extended):** once your license is active,
your dashboard shows the exact remaining balance to reach Extended's current
price (never a re-typed guess) and a **Pay balance to unlock Extended**
button. This upgrade is deliberately non-destructive — it changes your tier
and entitlement level only, and never touches your existing license key or
your platform's live connection to us. Nothing on your already-deployed
platform needs to be reconfigured because of it.

---

## Step 3 — Submit your Project Intake Form

This form only unlocks once your license is active — it's the commencement
brief our team builds your platform from. Fields:

| Field | Required? | Notes |
|---|---|---|
| Desired brand name | Yes | What your platform will be called |
| WhatsApp number | Yes | We use this for deployment updates — see below |
| Brand primary color | Yes | Hex code, e.g. `#0A6E6E` |
| Brand accent color | Yes | Hex code, e.g. `#D4A017` |
| Logo | One of these two | Upload a file, **or** describe/link a design reference for our design team to work from |
| Logo design reference | | |
| Banner reference upload / description | No | Optional, same either-or pattern |
| Hosting choice | Yes | Naara's server / your own VPS / your own shared hosting — this is the **binding** choice, replacing your Step 1 preference |
| Hosting disclaimer acknowledgement | Yes, if self-hosting | |
| Hosting host / username / password / notes | Yes, if self-hosting | See security note below |
| Additional notes | No | Anything else we should know |

**Security note on hosting credentials:** if you choose to self-host, the
login details you give us are encrypted at rest in our database and are
only ever decrypted for our deployment staff's internal review screen and
your intake PDF — never displayed anywhere else, never sent to any
third party.

**One important behavior to know:** re-submitting this form (to fix a typo,
change your hosting choice, etc.) resets its review status back to
"pending" — even if our team had already started reviewing it, or started
your deployment countdown. If you need a small correction after we've
already started building, **message us on WhatsApp instead of re-submitting
the form**, so you don't accidentally reset a countdown that's already
running.

---

## Choosing your hosting: what it means for you

**Naara-hosted (recommended for most merchants):** nothing technical
required from you. We provision, secure, and maintain the server. You just
need to get us your branding and be reachable for questions.

**Your own VPS or shared hosting:** you are responsible for:

- An active hosting account meeting the platform's requirements — PHP
  8.2+, MySQL 8, Redis, enough disk/RAM for your expected traffic.
- Giving us **real, working login credentials** in the intake form —
  ideally a dedicated deployment account rather than your own personal
  login, if your host supports creating one.
- **Not changing that password or account state while deployment is in
  progress.** If it changes mid-deployment and we lose access, your
  timeline resets from whenever you get us working credentials again —
  not from when you originally submitted.
- Having your domain purchased and its DNS ready to point at the server
  once we tell you it's time.
- Keeping the hosting account paid up and active — a suspended host account
  is a deployment blocker we can't work around.

---

## What actually happens while you wait (read this before asking "is it done yet?")

Once our team has reviewed your intake form, we set a deployment timeline —
an estimated number of days. Your dashboard then shows a **"Day X of Y"**
progress indicator.

**Be clear-eyed about what that progress bar is:** it is a countdown based
on the day-count our team typed in, not a live feed of actual deployment
work. Standing up your platform — installing NaaraSim, configuring your
server or our own, wiring in your branding — is real, manual work our team
does by hand, using the brief and (if applicable) credentials you gave us
in Step 3. The countdown finishing and you getting a "your platform is
ready" email are two separate things by design: the email fires the moment
the estimated timeline elapses. If real-world deployment work is genuinely
running long, our team will reach out on WhatsApp rather than let a stale
countdown speak for us — but the honest way to think about this indicator
is "our current estimate," not "a live tracker."

You'll get:

- An email the moment your timeline completes, with a link back to your
  merchant dashboard.
- A WhatsApp message from our team with next steps and your actual live
  platform's login details.

---

## Your responsibilities while you wait — checklist

- [ ] Keep the WhatsApp number you gave us reachable — it's our primary
      channel for deployment updates and for handing over your final login.
- [ ] If self-hosting: keep your hosting credentials valid and unchanged
      until we confirm deployment is complete.
- [ ] If self-hosting: have your domain and DNS ready to point at the
      server the moment we ask.
- [ ] If you gave us a design reference instead of a logo/banner file,
      respond promptly if our design team follows up with drafts for
      approval.
- [ ] Don't re-submit the intake form for minor tweaks once deployment has
      started — WhatsApp us instead (see the note in Step 3).
- [ ] If you're planning to upgrade from Basic/Medium to Extended, make
      sure your NaaraSim wallet can cover the balance when you're ready —
      the exact amount is always shown live on your dashboard.

---

## Step 4 — Logging into YOUR live platform for the first time

This is the separate login mentioned at the top — your own platform's own
admin panel, on your own domain, completely independent of your NaaraSim
merchant account.

Our team hands you three things once your platform is live: your **admin
panel path** (e.g. `/adminmaster`), an **email**, and a **password**. These
come from the installer's own "Done" screen, generated fresh for your
specific installation — not a fixed value published anywhere, and not
something you'll find by guessing a common path.

### First-login validation checklist

Work through this in order — steps 1–3 are money-safety critical and
should happen before you ever announce your platform to your own customers:

1. **Change the default admin password immediately**, and turn on
   two-factor authentication (Admin → Security). Bookmark your admin path
   first — if you lose track of it, only another super-admin on your
   platform (or our team) can look it back up.
2. **Configure your own admin panel path** if you'd like something other
   than the default (same Security screen) — optional, but recommended if
   you plan to hand admin access to staff of your own later.
3. **Configure your Provider API keys** (Admin → Provider Keys). This is
   not optional — until you paste in your own eSIM, number/SMS, payment
   gateway, and storage credentials, your platform genuinely cannot sell
   anything, because every price and every delivery is fetched live from
   whichever providers you've connected. A freshly deployed platform ships
   with none configured.
4. **Confirm your branding landed correctly** — logo, brand colors, splash
   screen, and app name should all match what you submitted in your intake
   form. If anything looks off, tell us — don't try to fix branding assets
   yourself unless you're comfortable in the admin theme tools.
5. **Confirm your feature set matches what you paid for** — see the table
   below. A Basic/Medium platform should show certain features locked; an
   Extended platform should show none of them locked.
6. **Confirm the platform's scheduled task is actually running.** The
   installer's Done screen calls out one cron entry that has to be active
   on your server for queued jobs, provider syncs, and background checks to
   run at all — if you're self-hosted, verify with your hosting provider
   that it's really been added, not just shown to you.
7. **Run one real (or sandbox) purchase end-to-end** before telling your own
   customers you're open — buying a small data plan or number yourself is
   the fastest way to catch a misconfigured provider key before a real
   customer does.

---

## Unlocking features — Basic vs. Extended

Your **entitlement level** — not your tier directly — decides which
features are locked. It's set automatically the moment your license
activates or upgrades:

| Entitlement level | Set automatically when... | Locked features |
|---|---|---|
| `basic` | You buy Basic or Medium | Gift cards, full voice eSIM (calls + SMS, not just data), preloader customization, Brand Hunt / Brand Directory |
| `standard` | **Never automatically** — this is a manual arrangement our team sets by hand for specific accounts | Gift cards only |
| `full` | You buy Extended or Extended V2, or pay the balance to upgrade from Basic/Medium | Nothing — everything is unlocked |

If you're on Basic or Medium and see a feature locked that you believe you
should have (for example, from a custom arrangement), that's not something
you can self-adjust from either dashboard — message our team.

If you've paid the balance to upgrade to Extended and a feature still shows
as locked after your platform's next check-in with us, give it a few
minutes for your platform to refresh its entitlement cache before assuming
something's wrong.

---

## Credentials — what's yours, what's ours

- **License key:** a durable identifier for your specific instance. You
  will not see this in your merchant dashboard — it's issued to our
  deployment team and handed to whoever configures your platform, as part
  of the same handover as your admin login. Treat it like a password: if
  your platform is ever reinstalled or moved to new hosting, this key is
  what reconnects it to your license.
- **API token:** a separate, rotatable technical credential your live
  platform uses automatically to check in with us for updates. You'll
  generally never touch this directly — it's minted and rotated by our
  tooling behind the scenes.
- **If you ever need your platform reinstalled, moved to different hosting,
  or your license key regenerated** (for example, if you suspect it's been
  exposed), contact us rather than attempting it yourself — regenerating a
  license key immediately invalidates the platform's existing connection to
  us, on purpose, and needs to be done together with reconnecting it.

---

## FAQ

**Why can't I just see my license key in the dashboard?**
By design — it's a sensitive credential handed over once, directly, as part
of your deployment, rather than sitting readable in a web UI indefinitely.

**I upgraded to Extended by paying the balance — do I need to do anything on
my platform's end?**
No. The upgrade only updates your tier and entitlement level in our
records; your platform picks this up automatically the next time it checks
in with us. Nothing needs reinstalling or reconfiguring.

**Does my theme add-on payment count toward upgrading to Extended?**
No — license payments and theme add-on payments are tracked on separate
ledgers on purpose, specifically so a merchant can't reach Extended early
just by choosing an expensive theme.

**Can I switch from Naara-hosted to my own server later, or vice versa?**
This isn't a self-service toggle today — message our team and we'll walk
you through what a hosting migration for your platform would involve.

**My deployment countdown finished but I haven't heard anything — what's
going on?**
The completion email fires purely because the estimated timeline elapsed,
independent of whether our team's actual manual work is fully wrapped up.
If a day or so passes with no WhatsApp follow-up after that email, reach
out to us directly rather than assuming something's wrong on your end.

---

## Appendix — for Naara staff

Quick reference for the admin actions this guide assumes happen on our
side, all from **Admin → White Label** (`Admin\WhiteLabelRegistry`):

| Merchant-facing moment | Staff action |
|---|---|
| "An admin will confirm pricing shortly" | **Price this request** — sets `price_usd`, does not touch status or issue anything |
| Merchant pays and their license activates | *(automatic — no staff action; this is `payAndActivate()` firing off the merchant's own click)* |
| Merchant's intake form has just come in | **Mark seen** on the intake — required before you can set a timeline |
| Setting merchant expectations on delivery | **Set deploy timeline (days)** — starts the countdown the merchant's dashboard shows |
| Handing over the live platform | **Reveal credential** on the now-`active` instance to get the one-time license key / token display, alongside whatever admin login the install run produced |
| A leaked or lost license key | **Regenerate key** — this is destructive (kills the existing API token) by design |
| A custom mid-tier arrangement | **Set level → standard** — the only way `standard` entitlement is ever reached; it is never automatic |

Full technical detail on every field, status, and service method behind
this flow lives in code (`app/Models/WhiteLabelInstance.php`,
`app/Services/Updater/WhiteLabelLicenseService.php`,
`app/Services/Updater/WhiteLabelProjectIntakeService.php`) and in
`docs/build-specs/PROMPT21-EXT-merchant-v2-license-tiers.md` /
`PROMPT21-EXT2-project-intake-form.md` — this guide is the merchant-facing
distillation of that, not a replacement for it.
