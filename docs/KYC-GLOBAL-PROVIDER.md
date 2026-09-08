# Global KYC/KYB provider — research & recommendation (BUILD-4 §2.2)

**Status: research complete, integration pending Frank's vendor choice + keys.**

## Why this exists
The merchant programme is now global (§2.1 — a worldwide country + business
registration catalogue). But the two identity providers already wired into
`app/Services/Kyc/` — **Dojah** and **Smile ID** — are African-market vendors
(BVN / NIN / pan-African liveness). They do **not** cover a global country list,
so a merchant in the US, UK, EU, etc. cannot currently complete business (KYB)
verification. §2.2 adds a global-coverage provider alongside them, **routed by
country** — the same pattern `PayoutAccountService` uses to route bank resolvers.

## Coverage confirmed from current (2026) sources
> Confirmed against each vendor's current material, per §2.2's instruction not to
> assume from general familiarity. Verify once more at integration time — vendor
> coverage changes.

| Vendor | KYB (business) coverage | KYC (individual) | Notes |
|---|---|---|---|
| **Sumsub** | **Registry checks in 220+ countries/territories**; positioned as an all-in-one KYB (UBO, registry, AML) | 220+ countries, 14,000+ document types | Single vendor for KYC **and** KYB; broadest business-registry breadth. |
| **Persona** | **150+ business registries** for entity/UBO checks | Broad document coverage, highly configurable | Most flexible/composable workflows, excellent DX. Strong alternative. |
| **Onfido (now Entrust IDV)** | Narrower KYB registry breadth | Enterprise document + biometric, 195+ countries | Best for document/biometric KYC; weaker as a global **KYB** registry source. |

## Recommendation
- **Primary global provider: Sumsub.** It has the widest business-registry (KYB)
  coverage (220+ countries) and covers KYC too, so one integration serves both
  the payout-time individual check and the merchant business check worldwide.
- **Strong alternative: Persona** — pick it instead if you want maximum workflow
  customisation and a composable API; 150+ registries still covers Naara's target
  markets.
- **Keep Dojah / Smile ID for African markets** (NG/GH/KE/ZA/…): they're cheaper
  and stronger on local IDs (BVN/NIN). Route those countries to them; route the
  rest of the world to the global provider.

## Integration design (ready to build once a vendor + keys are chosen)
The seam already exists and needs **no redesign** — mirror the payout pattern:

1. **New provider class** `App\Services\Kyc\SumsubKycProvider` (or `PersonaKycProvider`)
   implementing the existing `KycProviderInterface`:
   `available()` (key-gated), `submit()` (hosted flow / API), `verifyWebhook()`
   (HMAC, constant-time — Sumsub signs with `X-Payload-Digest`), `parseWebhook()`.
   Register it in the container as `kyc.sumsub` alongside `kyc.smileid` / `kyc.dojah`.
2. **Country routing in `KycService`** — add `providerForCountry(?string $iso)`
   next to the current `activeProvider()`: African ISO codes → the admin-chosen
   African provider; everything else → the global provider (falling back to manual
   review when unconfigured). This is the *only* new selection logic; the submit /
   webhook / level-tracking flow is unchanged.
3. **Admin keys** — add the vendor's App Token + Secret Key (+ webhook secret) to
   Admin → API keys via `KycSettings`, never hardcoded (money-safety rule 10).
4. **No behaviour change until keys are present** — `available()` stays false
   without keys, so the router keeps falling back to manual review exactly as today.

## What's needed from Frank to finish §2.2
1. **Pick the vendor** (recommended: Sumsub; alternative: Persona).
2. **Create the account** and provide App Token / Secret / webhook secret.
Then the provider class + country routing is a small, well-scoped change against
the existing interface.

## Sources
- [Sumsub — KYB guide 2026](https://sumsub.com/blog/guides-reports/kyb-guide_guide_2026/)
- [Sumsub — Business Verification (six-in-one KYB)](https://www.prnewswire.com/news-releases/sumsub-revamps-business-verification-making-it-the-only-six-in-one-kyb-solution-on-the-market-302168846.html)
- [Persona vs Onfido/Entrust IDV — 2026 comparison](https://www.deepidv.com/media/articles/persona-vs-entrust-idv-onfido-comparison-2026)
- [Top identity-verification platforms 2026](https://www.deepidv.com/media/articles/top-10-identity-verification-platforms-2026-comparison)
