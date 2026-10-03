# Provider plan B (Addendum D-3.24)

If a provider is declined, delayed, or goes down, work down this list. Each step is a setting or an admin action — no deploy.

1. **Payoneer not approved / delayed** -> Grey, if API access is granted. (Not built until real docs exist.)
2. **Grey unavailable** -> PayPal where the payee has PayPal (already built, `payout.paypal`).
3. **PayPal unsuitable** -> Cryptomus (already built; admin-only option).
4. **Nothing else available** -> **Manual rail** (`manual_external`): switch on in Admin -> All payout settings -> Manual rail; enable a corridor for the country with provider `manual_external`. Requests wait in Admin -> Payout health -> "Pay by hand"; you pay from your own bank/app and record a proof reference and a note. The payout then becomes delivered, posts to the accounting ledger, and is counted by the Funding Radar. No provider is ever called.

During an outage on a rail: requests are **queued, not redirected** (the destination is bound to the rail). Users see "queued — no action needed". You get an alert after `Provider outage alert after` minutes (default 60).
