# WhatsApp Autopilot (BUILD-4 §7)

Automated, **opt-in**, template-based WhatsApp notifications for lifecycle events
(eSIM delivered, number delivered, renewal reminder, low balance, order failed),
sent over the **Meta WhatsApp Cloud API**.

Like every other provider on NaaraSim, it is **gated by real config**: with no
keys it is "Coming Soon" and every call is a safe no-op. The moment the keys are
saved it flips to Active and starts sending — no code change, no redeploy.

---

## How it behaves (the gates)

A message goes out **only** when *all* of these hold — so we never spam and never
breach Meta's opt-in policy:

1. **Autopilot is on** — `naara.whatsapp_autopilot.enabled` (env
   `WHATSAPP_AUTOPILOT_ENABLED`, default `true`) **and** the Cloud API keys are
   present (`ProviderStatus::isActive('whatsapp')`).
2. **The event is mapped** to a non-blank, Meta-approved template name.
3. **The user opted in** — `users.whatsapp_opt_in` (set on the Profile page).
   Defaults to **false**; a user can also opt out any time by replying **STOP**
   on WhatsApp (handled by the webhook).
4. **We have a number** — `users.whatsapp_number`, falling back to `users.phone`.

Every send is a **queued job** (`SendWhatsAppTemplateJob`) — external calls are
never synchronous (money rule 8) — and the client **fails safe** (returns null,
logs, never throws), so a WhatsApp hiccup can never break a money path.

---

## Going live (Meta setup)

1. Create a Meta App → add the **WhatsApp** product → get a **Phone number ID**
   and a **WABA ID**.
2. Generate a **permanent access token** (System User token) with
   `whatsapp_business_messaging` + `whatsapp_business_management`.
3. Create + submit your **message templates** for approval (see below). Use the
   approved template *names* in the env vars.
4. Set the **webhook**: callback URL `https://<your-domain>/webhooks/whatsapp`,
   verify token = `WHATSAPP_VERIFY_TOKEN`; subscribe to the `messages` field.
   Meta calls it with a GET handshake first (we echo `hub.challenge` when the
   token matches), then POSTs events signed with `X-Hub-Signature-256` (verified
   against `WHATSAPP_APP_SECRET` before we read the body — money rule 9).

### Environment

```dotenv
WHATSAPP_PHONE_NUMBER_ID=      # required
WHATSAPP_ACCESS_TOKEN=         # required (permanent System User token)
WHATSAPP_WABA_ID=
WHATSAPP_APP_SECRET=           # verifies inbound webhooks
WHATSAPP_VERIFY_TOKEN=         # your chosen webhook handshake secret
WHATSAPP_AUTOPILOT_ENABLED=true
# Optional — override the default template names / language:
# WHATSAPP_TPL_ESIM_DELIVERED=esim_delivered
# WHATSAPP_TPL_NUMBER_DELIVERED=number_delivered
# WHATSAPP_TPL_RENEWAL_REMINDER=renewal_reminder
# WHATSAPP_TPL_LOW_BALANCE=low_balance
# WHATSAPP_TPL_ORDER_FAILED=order_failed
# WHATSAPP_GRAPH_VERSION=v21.0
# WHATSAPP_DEFAULT_LANG=en
```

Rotate the access token + app secret on the platform's 90-day key cadence.

---

## Templates

Autopilot sends **template** messages (the only kind allowed outside the 24-hour
customer-service window). Each event uses a body with positional parameters
`{{1}}, {{2}}, …` in the order Autopilot passes them:

| Event              | Fired from                    | Body params                    |
|--------------------|-------------------------------|--------------------------------|
| `esim_delivered`   | eSIM checkout (`Checkout`)     | `{{1}}` name · `{{2}}` plan    |
| `number_delivered` | Number purchase (`GetNumber`)  | `{{1}}` name · `{{2}}` service |
| `renewal_reminder` | *(available — not yet wired)*  | operator-defined               |
| `low_balance`      | *(available — not yet wired)*  | operator-defined               |
| `order_failed`     | *(available — not yet wired)*  | operator-defined               |

Example approved template body for `esim_delivered`:

> Hi {{1}} 👋 your NaaraSim eSIM for *{{2}}* is ready. Open the app → eSIMs to
> install it. Reply STOP to turn off these updates.

To add a new event: approve a template in Meta, add its name under
`naara.whatsapp_autopilot.templates.*`, and call
`app(WhatsAppAutopilot::class)->notify($user, '<event>', [$param1, …])` at the
delivery point.

---

## Code map

- `config/services.php` → `whatsapp` — Cloud API keys.
- `config/naara.php` → `whatsapp_autopilot` — master switch + event→template map.
- `App\Support\ProviderStatus` — `whatsapp` Active/Coming-Soon gate.
- `App\Services\WhatsApp\WhatsAppCloudClient` — low-level Graph API send (gated).
- `App\Services\WhatsApp\WhatsAppAutopilot` — the gates + queueing entry point.
- `App\Jobs\SendWhatsAppTemplateJob` — the out-of-band delivery.
- `App\Http\Controllers\Webhooks\WhatsAppWebhookController` — handshake, signed
  events, STOP opt-out. Route: `webhooks.whatsapp` (`/webhooks/whatsapp`).
- Opt-in UI: the Profile page (shown only once WhatsApp is Active).

Tests: `tests/Feature/WhatsAppAutopilotTest.php`.
