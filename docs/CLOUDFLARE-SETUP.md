# If this domain sits behind Cloudflare

NaaraSim runs correctly behind Cloudflare (or any reverse proxy) — `TrustProxies`
is configured (`bootstrap/app.php`) and the URL scheme is forced to HTTPS in
production (`AppServiceProvider`), so signed URLs (email verification, password
reset) and payment redirects resolve as `https://`.

But Cloudflare's **security features can silently block or challenge inbound
payment webhooks**, producing the worst-possible symptom: *the payment succeeds
on the gateway's side and is never credited in Naara.* This platform has already
seen a webhook-not-crediting incident once (for an unrelated reason), so treat
the steps below as required deploy hardening, not optional polish.

> Do all of this in the Cloudflare dashboard for the zone, then **verify with a
> real test webhook** from each gateway's own dashboard — never assume it worked
> from the settings screen alone.

---

## 1. Allow each payment gateway's webhook source IPs

Payment gateways deliver webhooks from a **published, gateway-specific set of IP
ranges**. Add each configured gateway's *current* ranges as Cloudflare
**IP Access Rules → Allow** (Security → WAF → Tools, or Security → WAF → Custom
rules on the new dashboard).

**Pull the ranges live from each gateway's own docs at deploy time — do NOT
hardcode a list here.** IP ranges change, and a stale allowlist is worse than
none (it blocks the very webhooks it was meant to permit). For the gateways this
build supports, the current ranges are documented at:

| Gateway | Where its current webhook/IP ranges are published |
| --- | --- |
| Paystack | Paystack docs → Payments → Verify webhook / "Webhook IPs" |
| Flutterwave | Flutterwave docs → Webhooks → "Whitelisting our IPs" |
| Stripe | `https://stripe.com/files/ips/ips_webhooks.txt` (Stripe's own published list) |
| PayPal | PayPal Developer docs → Webhooks (resolve IPs via PayPal's published hostnames) |
| Binance Pay | Binance Merchant/Pay docs → Webhook / IP configuration |
| NOWPayments | NOWPayments docs → IPN / webhook settings |
| Cryptomus | Cryptomus docs → Webhook / callback IPs |
| CoinPayments | CoinPayments docs → IPN settings |
| Payssion | Payssion docs → Notification / callback IPs |

Only add rows for the gateways you have actually configured keys for. Every
inbound webhook route lives under `/webhooks/*` (see `routes/web.php`), and each
handler independently verifies the provider's HMAC signature — the Cloudflare
allowlist is defence-in-depth in front of that, not a replacement for it.

## 2. Exclude `/webhooks/*` from Bot Fight Mode

Under **Security → Bots**, make sure Bot Fight Mode (or Super Bot Fight Mode)
does **not** challenge `/webhooks/*`. A webhook has no browser to solve a
JS/managed challenge, so a challenged webhook is a *silently dropped* webhook —
exactly the "succeeded on the gateway, never credited here" failure. Add a
WAF skip/exception for the `/webhooks/*` path if Bot Fight Mode is on.

## 3. Cache-bypass rules for dynamic paths

Cloudflare must **never cache** these paths — a cached webhook response, admin
action, or API reply is a materially worse bug than a cached marketing page.
Under **Caching → Cache Rules** (or a Page Rule per path), set *Bypass cache* for:

- `/webhooks/*` — a cached `200` on a webhook can make a real delivery look
  already-processed.
- `/admin/*` — cached admin action pages leak stale state / CSRF tokens.
- `/api/*` — the developer API + internal Livewire/AJAX calls must always be live.
- any dashboard/authenticated path already in use (`/dashboard`, `/account`, …).

Static assets (`/build/*`, images) and the marketing pages can and should stay
cached as normal — this is about the dynamic surface only.

## 4. Verify for real

After the changes, trigger a **real test webhook from each configured gateway's
dashboard** and confirm:

1. It reaches Naara (check the **Recent webhook deliveries** panel in the admin
   System Health page — the inbound webhook delivery log records every
   `/webhooks/*` hit and whether it was accepted or rejected).
2. The corresponding order/wallet action completed.

If a delivery never appears in that log, Cloudflare is still blocking it — revisit
steps 1–2 before going live.
