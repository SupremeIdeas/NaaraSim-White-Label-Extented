# NAARA PROMPT 21-EXT2 — Merchant White-Label Project Intake Form, Deploy Timeline & PDF Handoff

**For: Claude Code, working against the master `NaaraSim-main` repo only.** Builds on top of
`PROMPT21-EXT-merchant-v2-license-tiers.md` (already shipped, merged to `main`). This is a
follow-up feature, not a revision of that spec.

## §0 — What this is

Once a merchant's white-label license is **active** (they've paid — Basic/Medium/Extended/
Extended V2, doesn't matter which), they now fill a **project intake form** — the real
commencement brief the ops team needs to actually stand up their deployment. This is
deliberately a SEPARATE, later step from the lightweight `hosting_preference` field already
captured at request time (Prompt 21-EXT §2.3) — that field stays as the merchant's early
"leaning," the intake form below is the authoritative, detailed brief collected once the
purchase is real.

## §1 — Hosting decision layer (extends the existing 2-option field to 3)

`WhiteLabelInstance::HOSTING_OWN_SERVER` (generic, unused beyond the request-step dropdown) is
replaced by two concrete options — nothing live depends on the old value, so this is a clean
rename, not a migration:

- `HOSTING_SUPREME_IDEAS_SERVER` (unchanged) — we host it, no credentials needed from the merchant.
- `HOSTING_OWN_VPS` — merchant hosts it themselves on a VPS. The form tells them to purchase
  **Cloudways' Laravel-optimized VPS hosting** and collects login details for it.
- `HOSTING_OWN_SHARED` — merchant hosts it themselves on shared hosting. The form recommends
  **premium shared hosting from Hostinger or Namecheap** and collects login details for it.

Both `own_*` paths show the same explicit disclaimer: *"Your login details are encrypted and
stored securely — only authorized Supreme Ideas Agency staff can access them, solely to deploy
your white-label platform."*

## §2 — `WhiteLabelProjectIntake` (new model, one per instance)

New table `white_label_project_intakes`, `belongsTo` `white_label_instances` (one row per
instance — a second submission overwrites the first, since this is "the current commencement
brief," not a versioned history):

- `desired_brand_name` — what they want their white-label actually called.
- `whatsapp_number` — for the ops team to reach them once live (manual outreach; this codebase
  has no WhatsApp Business API integration to build a bot on, so this is contact info, not an
  automated channel).
- `brand_primary_color` / `brand_accent_color` — the two-tone palette their white-label should
  use, the same pairing convention Naara's own brand uses (Deep Teal + Warm Gold) throughout
  every themed surface.
- `logo_url` (an uploaded file, via `MediaStorage::storePublic()`) **or** `logo_design_reference`
  (a link/description) — exactly one of the two is required: either they have a logo to hand
  over, or they want Naara's design team to create one from a reference.
- `banner_reference_url` (optional uploaded reference image) / `banner_design_request` (a free-
  text brief) — how they want their site-wide banners to look, the same way Naara's own banners
  match the platform's brand throughout the app.
- `hosting_choice` — one of the three §1 values, confirmed/re-picked at intake time (the
  request-time value was only ever a leaning).
- `hosting_disclaimer_acknowledged_at` — set only when `hosting_choice` isn't
  `supreme_ideas_server` (the credential-safety disclaimer above).
- `hosting_host` / `hosting_username` / `hosting_password` / `hosting_notes` — **`encrypted`
  Eloquent casts** (same discipline as `PortInRequest::account_number`/`pin`), populated only
  for the two `own_*` paths. Never logged, never included in any API response, decrypted only
  for the admin intake-detail view and the PDF export.
- `additional_notes` — free-text catch-all for anything else the ops team should know.
- `status` — `pending` (just submitted) → `seen` (admin reviewed) → `in_progress` (timeline
  set, autopilot running) → `completed` (timeline elapsed).
- `reviewed_by` / `reviewed_at` — set by `markSeen()`.
- `deploy_days` / `deploy_started_at` / `deploy_completed_at` — set by `setDeployTimeline()`;
  `deploy_started_at` is stamped the moment the admin sets the timeline, and progress from then
  on is 100% derived (no daily admin action needed — "autopilot").

## §3 — `WhiteLabelProjectIntakeService`

- `submit(WhiteLabelInstance $instance, array $data): WhiteLabelProjectIntake` — merchant-facing.
  Only reachable when the instance `hasLiveLicense()` (must be paid). Validates the
  hosting-choice-specific requirements (credentials required for `own_*`, disclaimer
  acknowledgment required for `own_*`). `updateOrCreate` on `white_label_instance_id` — a
  resubmission before admin marks it Seen simply replaces the draft.
- `markSeen(WhiteLabelProjectIntake $intake, int $reviewerId): WhiteLabelProjectIntake` — admin-facing.
- `setDeployTimeline(WhiteLabelProjectIntake $intake, int $days): WhiteLabelProjectIntake` —
  admin-facing. Requires `status === 'seen'` already (can't schedule a deploy before reviewing
  it). Stamps `deploy_started_at = now()`, `status = 'in_progress'`.
- `progressPercent(WhiteLabelProjectIntake $intake): ?int` — `null` until a timeline is set;
  otherwise `min(100, round((now - deploy_started_at) / (deploy_days * 86400) * 100))` — the
  single place both the merchant dashboard and the admin panel read this from.

## §4 — Merchant dashboard (`/merchant/white-label`)

Once the instance is `active` (already the case today), a new section:
- No intake yet → the intake form itself (desired name, WhatsApp, hosting choice + disclaimer +
  conditional credential fields, additional notes).
- Intake submitted, `status = pending` → "Submitted — awaiting review."
- `status = seen` → "Reviewed — your deployment timeline will be set shortly."
- `status = in_progress` → a progress bar (percent from §3) + "Day X of Y."
- `status = completed` → "Your white-label platform is live!" (this is also the moment the
  deploy-ready email fires, §6).

## §5 — Admin (`Admin\WhiteLabelRegistry`)

Per-instance intake detail (visible once submitted): every field including **decrypted**
credentials (admin-only, same access level as everything else on this screen), the WhatsApp
number surfaced prominently for outreach, a "Mark Seen" button, a "Set deploy timeline" days
input + button (disabled until Seen), and an "Export PDF" button.

## §6 — PDF export + deploy-ready notification

- A real controller route (Livewire can't stream file downloads) renders a Blade view through
  `barryvdh/laravel-dompdf` (newly added — no PDF library existed in this codebase) with the
  full intake brief, admin-only, for handing to whoever actually does the deployment work.
- New scheduled command `whitelabel:intake-deploy-check` (daily, same `routes/console.php`
  convention as every other scheduled job here): finds `in_progress` intakes whose timeline has
  elapsed, flips them to `completed`, and sends `WhiteLabelDeploymentReadyNotification` (plain
  `MailMessage`, mirrors `MerchantSubscriptionDueNotification`'s shape) to the instance's owner.

## Acceptance checklist

- [ ] `HOSTING_OWN_SERVER` fully replaced by `HOSTING_OWN_VPS`/`HOSTING_OWN_SHARED` everywhere
- [ ] Credentials are `encrypted` casts — never appear in plaintext in any log, response, or test assertion against raw DB columns
- [ ] Intake only submittable once the instance has a live license
- [ ] `setDeployTimeline()` refuses an intake that hasn't been marked Seen
- [ ] `progressPercent()` is the single source of truth read by both the merchant dashboard and the admin panel
- [ ] PDF export reachable only by super_admin/admin
- [ ] Deploy-ready email fires exactly once per intake (idempotent — the scheduled command only touches rows still `in_progress`)
- [ ] Full suite green; this is entirely master-side, no fork changes
