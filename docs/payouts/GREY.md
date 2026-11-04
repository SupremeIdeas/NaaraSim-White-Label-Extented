# Grey payout rail (master and both white-label forks, on by default)

Global bank and mobile-money payouts through the Grey Business API. Delivered as a payout-rail extension
(`app/PayoutRails/grey/`), so removing the folder leaves every core rail untouched.

Source of truth: `grey-business-api.md` and `grey_openapi.json` supplied by the owner.

## What is built

| Piece | Where |
|---|---|
| Gateway (send, status lookup, balances, webhook) | `app/PayoutRails/grey/GreyPayoutGateway.php` |
| HTTP client (sandbox/production hosts, payout-methods cache) | `GreyClient.php` |
| Account -> Grey `beneficiary` mapping | `GreyBeneficiaryMapper.php` |
| Member "International bank" form (fields from Grey's own list) | `GreyAccountService.php`, Withdraw page |
| Corridor creation from Grey's list (disabled) | `php artisan payouts:sync-corridors grey` |
| Test connection (read-only balances) | Admin, Provider Keys |
| Tests | `GreyPayoutRailTest`, `PayoutRailsOnboardingTest` |

## Money rules honoured

- `X-Idempotency-Key` header and body `reference` are both our `wireReference()`. Grey replays the original answer for 24h, so a retry can never send twice.
- A clear rejection (HTTP 4xx except 409/429) returns `failed` and the engine refunds. A 409, 429, 5xx or timeout throws, the payout is recorded as UNKNOWN and the reconciler resolves it with `GET /api/v1/transactions?client_reference=`. We never guess.
- Webhooks: `X-Webhook-Signature: sha256=<hmac>` over the RAW body with the webhook secret, constant-time compared. Empty secret or empty signature is a refusal. Duplicates are dropped by the payout-webhook dedupe table.
- Same-currency wallet model: `source_currency == destination_currency == request currency`, `source_amount == request amount`. This matches how FloatService tracks float per currency.
- Grey keys carry no test/live marker, so `available()` also requires the base URL and `PAYOUT_ENV` to agree (sandbox host in sandbox mode, production host in live mode).
- Every Grey payout is held for a human by the Guardian (`enrollment_unverifiable`) until the owner decides otherwise. Corridors ship disabled.

## Set-up (sandbox first)

1. Admin, Provider Keys: Grey base URL = `https://businessapi-sandbox.grey.co`, secret key `gbsk_...`.
2. In the Grey dashboard create a webhook to `https://<site>/webhooks/payouts/grey` and paste its secret into "Grey - Webhook Secret". (Webhook creation needs the dashboard login; there is no API for it.)
3. Press Test connection. It reads `/v1/balances`.
4. `php artisan payouts:sync-corridors grey` creates DISABLED corridors from Grey's live `/v1/payout-methods` (each country with its own currency only).
5. Fund the sandbox wallet (`POST /v1/sandbox/topup`, sandbox only) in the currency you will pay out.
6. Enable ONE corridor, add an account as a test member, request a payout, approve it in the review queue, watch it settle.
7. Only then: production URL, live key, `PAYOUT_ENV=live`.

## Verified live (read-only, 2026-10-11, owner's key, no payout created)

- The supplied key is a PRODUCTION key (the sandbox host answers 401). Platform mode and base URL must therefore both be live to use it; the gateway refuses a mismatch (proved against the real host).
- Wallets provisioned: EUR, GBP, USD only, all at 0. No NGN/GHS/KES/XOF wallet exists, so those payouts are paid FROM THE USD WALLET through Grey's conversion. The rail sizes the USD source from a fresh `/v1/currency/rate` quote (`transaction_type=withdraw`; `destination_amount` is net of Grey's swap + withdrawal fees) so the member receives the locked local amount, capped at the reserved net USD plus `GREY_MAX_SLIPPAGE_PCT` (default 3). Past the cap, or with an under-funded wallet, nothing is sent (clear failure + admin alert `grey_wallet_short`). Real quote: 100 USD -> 132,987.61 NGN.
- `/v1/payout-methods` returns six rails: bank_account, mobile_money, interac, pix, upi, wallet_address. `required_fields` is a UNION across countries (document_number, recipient_type, phone_number, wallet_id ...), not per-country, so the member form requires only the account number (plus network for mobile money) and offers the rest as optional. interac/pix/upi/wallet_address publish no field list and are not offered.
- What Grey actually pays today (bank_account): BD CA CI CL CM CN EG ET ID IN KE MA MY PH RW SA TH TR TZ US UY ZA. Mobile money: BD CI CM EG ET GH ID MY PH SN TH. **Nigeria has no bank or mobile-money rail on Grey** (only `wallet_address` USDC/USDT, whose quote answered "USDT payout temporarily unavailable"), and Kenya is bank only, Germany/Eurozone absent.
- Mobile money has no "Other" payment purpose; set `GREY_PAYMENT_PURPOSE` to an exact listed value (e.g. `Salary`) before enabling a mobile-money corridor.
- Funding: balances are zero. Nothing can be paid until the USD wallet is topped up.

## UNVERIFIED until a payout proves it

- The docs only show the statuses `processing` and `completed`. We map `failed/rejected/cancelled/reversed` to failed and anything unknown to processing (safe default).
- The payout response/transactions schemas are `{}` in the OpenAPI file; the shapes used come from the markdown examples.
- Whether the webhook `amount` is the source or destination amount (we never read it).
- Pagination on `/api/v1/transactions` (we look up by `client_reference`, so one page).
- Per-country beneficiary requirements (Grey's list is a union). Each corridor must be proven with one small real payout before it is enabled; a missing field comes back as a clear 400 and the payout fails safely.
- Grey has no account-name resolve call: the account holder name is the member's own verified name. This is why payouts stay on manual review.

## Go-live package

`php artisan update:package --from=<tag> --key=<private key> --changelog="Grey payout rail 1.0.0" --master-only`
