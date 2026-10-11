# Funding Radar Runbook (MASTER ONLY)

Admin -> **Global payout rail**. Exact numbers are labelled **Exact**; forecasts **Estimate**. The page reads a cached copy refreshed every 5 minutes (and within seconds of a payout being requested, settled or reversed).

## Reading the cards
| Card | Meaning | What to do |
|---|---|---|
| Enrolled users | Users who CHOSE a global rail (selected / onboarding / active). Users who merely live in an unserved country are NOT here. | — |
| Max exposure | Everything enrolled users could withdraw right now. The worst case. | Never fund to this unless everyone would really withdraw today. |
| Committed unsent | Requests already made but not sent (pending / approved / awaiting funds). | Must be covered by float. |
| In flight | Sent, waiting for the provider. | Counted in the top-up only on rails that debit float later (`config/payouts.php`). |
| Due at next sweep | What the next automatic run WILL pay (same eligibility rules as the run itself). 0 in manual mode. | Must be covered by float. |
| Forecast 7d p50 / p90 | Estimate of manual withdrawals over the next week from your own history (EWMA of weekly withdraw ratio). Until 4 weeks of data exist it uses a default (25%) and says **low confidence**. | Treat p50 as "likely", p90 as "bad week". |
| Float available | Your tracked balance at the provider, in USD. **Unknown** = not tracked yet. | Track the rail under Payouts -> Float or record a top-up in the planner. |
| Recommended top-up | `(committed + due + forecast) x (1 + FX buffer on non-USD share) - float` | Fund p50 normally; p90 before a promotion or payday. |

## Red banner: "Payouts will stall"
Float is below committed + due-at-sweep. Payouts for this rail wait in "awaiting funds" (user money stays held). **Top up**, record it in the planner (note = bank reference): waiting payouts resume oldest first.

## Alerts (each fires at most once per hour per rail)
`rail_float_short` (above) · `rail_float_low_p90` (float below the p90 need) · `rail_concentration` (one user holds > 20% of exposure — check them) · `rail_velocity_spike` (withdrawals in the last hour far above the trailing average — check for abuse) · `rail_stale_snapshot` (the radar has not run for 15 min — check the scheduler / queue) · `rail_failure_rate` (> 10% of the last 20 payouts failed — the rail is automatically taken out of routing for an hour; investigate with the provider).

## Daily routine
1. Open Overview; if the banner is red, top up first.
2. Planner: fund the p50 (or p90 before a busy period), record it with the bank reference.
3. Users tab: sort by total balance; look at anyone flagged KYC / cooling-off; export (audited) if finance needs it.
4. Reconciliation page -> "Payout rails": any flagged rail needs an explanation.

## Unserved demand
Countries where users hold withdrawable money but there is no enabled corridor, with how many asked to be notified. Open the corridor with the biggest number next.

## Guarantees
The radar only READS money tables. It writes only its own snapshot/stats tables and cache. Snapshots are append-only. Money is carried as exact integer units, never floats.
