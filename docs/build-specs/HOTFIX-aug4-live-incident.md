# NAARA HOTFIX: AUG-4 LIVE INCIDENT — payment credit, provider health, glass UI, logo sizing, menu overflow

Live-incident hotfix. Independent of the numbered NAARA-BUILD files.

## §1 — Unifying finding (read first)
Two symptoms — Paystack wallet not crediting after a live transaction, and the
provider-health widget showing nothing in admin — both depend on the same thing:
**Laravel's scheduler (`* * * * * php artisan schedule:run`) actually running on
the live cPanel host.** Both paths are code-correct (Paystack credit proven
end-to-end; `providers:health-check` correctly registered every 15 min covering
all providers). If the cron isn't installed / silently stopped, or the queue
worker isn't draining, both symptoms appear together. §2 makes this visible.

## §2 — "Is the scheduler running" diagnostic (HIGHEST PRIORITY)
Record each scheduled task's last-successful-run timestamp; add an admin
**System Health** panel: per task name, expected frequency, last actual run,
red/green overdue flag; plus current `QUEUE_CONNECTION` (flag loudly if `sync`
in production) and live queue backlog (pending job count). First thing to check
live: whether `providers:health-check` + the queue drain show recent on-schedule
runs; if stale, fix the cPanel cron, not code.

## §3 — Paystack webhook registration (operational, not code)
Confirm the webhook URL `https://[live-domain]/webhooks/payments/paystack` is
registered on the live Paystack dashboard. Send one real test transaction and
confirm via §2's panel that the webhook job queued and drained. Don't re-touch
`PaymentWebhookController` unless the operational cause is ruled out.

## §4 — Sandbox/live key fields (real gap)
Today one key field per gateway is reused for sandbox + live; the toggle is
informational. Build two separate key sets (sandbox + live) per gateway; the
toggle selects the active set; migrate any existing key into the slot matching
`PaymentSandbox` prefix detection. Also add a `public_key` field (sandbox + live)
for Paystack, Flutterwave, Stripe (platform-wide gap) to unblock inline/embedded
checkout. Other gateways stay secret-key-only.

## §5 — Global sidebar "too transparent"
`global-sidebar.blade.php` relies on `backdrop-blur-2xl` + `bg-white/75`
(`dark:bg-[#0D1B2A]/80`); blur is unreliable on some Android. Raise baseline bg
opacity (e.g. `/92`) so it's legible with zero blur support; blur becomes a
nice-to-have on top. Grep + apply to every glass surface with the same pattern.

## §6 — More-menu visual corruption
The More-menu content panel is already opaque (fine). The bug is the backdrop
(`bg-black/40 backdrop-blur-sm`) animating `backdrop-filter: blur()` alongside a
sibling's `transform` slide — a GPU-compositing glitch on many Android builds.
Remove `backdrop-blur-sm` from that backdrop (keep `bg-black/40`); same for the
global sidebar's backdrop layer.

## §7 — Marketing mobile menu content hanging off-viewport
The mobile mega-menu in `layouts/marketing.blade.php` uses `overflow-hidden`
with no `max-h` → tall content is clipped with no scroll. Change to
`max-h-[calc(100dvh-6rem)] overflow-y-auto overscroll-contain` +
`-webkit-overflow-scrolling: touch`.

## §8 — Logo size inconsistency
Every `<x-brand-logo>` passes ad-hoc `class="h-X max-w-[Ypx]"` (7 combos). Add a
named `size` prop (sm/md/lg → fixed height+max-width) and reassign every usage to
the correct context size; keep `class` for spacing only.
