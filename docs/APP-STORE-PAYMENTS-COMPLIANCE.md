# App Store / Play Store payment-policy compliance (BUILD-5 §6)

> **Status: research + recommendation. Re-confirm the quoted wording live from
> Apple's own page immediately before the FIRST iOS submission** — guideline
> text and enforcement shift. Last confirmed against
> <https://developer.apple.com/app-store/review/guidelines/> on **2026-08-04**.

NaaraSim sells **wallet top-ups** (via the payment gateways) that are then spent
on eSIMs, virtual numbers, merchant upgrades, and per-use Wizard billing. The
question this doc answers: **can those payments stay on our own gateways inside
the iOS app, or must some go through Apple In-App Purchase (IAP)?**

---

## What Apple's current guidelines actually say

- **3.1.1 (In-App Purchase):** digital goods/services consumed *inside* the app
  must use IAP. Notably: *"Digital gift cards, certificates, vouchers, and
  coupons which can be redeemed for digital goods or services can only be sold in
  your app using in-app purchase."*
- **3.1.3(e) (Goods and Services Outside of the App):** *"If your app enables
  people to purchase physical goods or services that will be consumed **outside
  of the app**, you must use purchase methods other than in-app purchase to
  collect those payments, such as Apple Pay or traditional credit card entry."*

So the line is **consumed-outside-the-app real-world goods/services** (our
gateways are *required*, IAP is *not* allowed) **vs. digital credit/goods
consumed in-app** (IAP required).

---

## Product-line assessment

| NaaraSim item | Nature | Verdict |
|---------------|--------|---------|
| **eSIM data plans** | Real-world telecom service used on the device's modem, outside the app | **Safe** — real-world service → our gateways (IAP not allowed). 3.1.3(e). |
| **Virtual / permanent numbers** | Real-world telecom service (calls/SMS on a real number) | **Safe** — same basis. |
| **Uncommitted wallet top-up** ("add $20") | Generic stored credit not tied to a specific real-world purchase at the moment of payment | **Risk** — looks like generic digital credit; this is the pattern Apple has rejected. |
| **Wizard per-use fee** ($0.45 after free sessions) | A fee for an in-app assistant action | **Risk** — reads as a digital in-app service → IAP territory. |
| **Merchant upgrade fee** | Unlocks in-app reseller features | **Risk / likely IAP** — an in-app digital entitlement. |
| **Naara Gift (gift cards)** | Third-party gift cards delivered by email/WhatsApp | **Grey** — physical-ish delivery, but "digital gift cards redeemed for digital goods" leans IAP. Assess per-brand before enabling on iOS. |

---

## Recommendation (structural, iOS build only)

The safest, review-friendly structure — **applied to the iOS build specifically**,
not web/Android where our gateways are unrestricted:

1. **Tie top-ups to a real-world purchase at the point of sale.** On iOS, don't
   present a free-floating "Add funds to wallet" screen. Instead, take payment
   **at the moment of buying an eSIM or a number** ("Pay $9 for this eSIM") via
   our gateway/Apple Pay — a real-world telecom service, squarely 3.1.3(e). The
   wallet can still hold change/refunds, but the *entry point* is a service
   purchase, not abstract credit.
2. **Route genuinely-digital in-app fees through IAP on iOS.** The Wizard
   per-use fee and merchant upgrade fee are in-app digital services — on iOS,
   either (a) put them behind StoreKit IAP, or (b) remove/hide them from the iOS
   build and keep them web/Android-only. Do **not** collect them via our gateway
   inside the iOS app.
3. **Gate Naara Gift on iOS** until a per-brand determination is made; default it
   off in the iOS build.
4. Keep web + Android on the current unrestricted gateway flow — this is an
   iOS-only structural carve-out.

Implementation hook: the app build already distinguishes platforms via the App
Export pipeline; add an `is_ios_build` capability flag the relevant Livewire
screens read to switch entry points, rather than branching on user agent.

---

## Google Play (Payments policy)

Play is generally more permissive here, and its "real-world / physical goods and
services" carve-out is broader, so eSIM/number sales via our gateway are fine.
**Confirm current Play Payments policy** before the Android release (it also
evolves), but no structural change is anticipated for the telecom product lines.
Purely-digital in-app fees (Wizard, merchant upgrade) should be reviewed against
Play's digital-goods rule the same way, though Play enforcement is looser.

---

## Before iOS submission — checklist
- [ ] Re-read 3.1.1 / 3.1.3(e) live from Apple; update the quotes above if changed.
- [ ] iOS build: wallet entry point is a service purchase, not "add funds".
- [ ] iOS build: Wizard fee + merchant upgrade fee via IAP or hidden.
- [ ] iOS build: Naara Gift gated off pending per-brand review.
- [ ] Confirm Google Play Payments policy for the Android release.
