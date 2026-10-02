# NAARA-PAYOUT-GLOBAL — START HERE (for Claude Code)
Put all files of this pack in `docs/payouts/` of NaaraSim master. Read CLAUDE.md and PROGRESS.md first, then this file.

## The pack
| File | Role |
|---|---|
| NaaraSim-Global-Payout-Layer-Blueprint.md | MAIN: corridors, Payoneer, Grey, Stripe, float, UX, hardening |
| NaaraSim-Global-Rail-Exposure-and-Funding-Radar-Addendum.md | A: admin tracking of global-rail users, exposure, live analytics, funding forecast |
| NaaraSim-Payout-Rail-Guide-Addendum.md | B: user-facing guide, country registry, rail recommendation |
| NaaraSim-Payout-Guardian-Auto-Approval-Addendum.md | C: automated approval engine + engine fixes (double-pay, threshold, autopilot) |
| NaaraSim-Payout-Gap-Closure-Addendum-D.md | D: conflict resolutions, 30 gap fixes, tests, go-live gates |

## Precedence if documents disagree
D wins, then C, then B, then A, then MAIN. Part 1 of D lists the known conflicts and their resolutions.

## Build order (one step at a time; each step ends with tests green, PROGRESS.md updated, then STOP and report)
| Step | Build | Why this order |
|---|---|---|
| 0 | Create `docs/payouts/OWNER-CHECKLIST.md` and `PROVIDER-PLAN-B.md` (docs only) | Owner actions gate everything live |
| 1 | MAIN Phase 1 (corridors, FX generalisation, Stripe country fix, encryption) + D-3.13 money precision/FX freshness, D-3.14 reference registry, D-3.15 corridor economics | Foundation every other piece reads |
| 2 | ENGINE SAFETY: C-A1, C-A2, C-A3 and D-H1 (job hardening, destination snapshot, webhook event table, invariants checker, restore protocol) | Fix double-pay and stuck-job risks BEFORE any new provider moves money |
| 3 | MAIN Phase 4 (float/treasury, `awaiting_funds`) + D-H3 accounting entries | Guardian gate G9 and the Radar need float |
| 4 | MAIN Phase 2 (Payoneer, sandbox) + Radar R1 (enrollment) + Guide G1–G2 (registry, advisor) + the reduced MAIN Phase 5 (deny list, sanctions stub) | First real rail + tracking + routing |
| 5 | C-A4, C-A5 in SHADOW mode + D-H2 (returns, erasure guard, failover, manual_external gateway, PII casts) | Guardian learns before it acts |
| 6 | Radar R2–R5 + Guide G3–G5 | Visibility and user flow |
| 7 | MAIN Phase 3 (Grey, only when docs/sandbox exist) and 3b (Stripe corridors, only after owner confirmation) | Extra rails |
| 8 | C-A6 admin review UI + MAIN Phase 6 UX + D-H4 full test matrix + go-live gates (D Part 5) | Finish and prove |

## Hard rules (from CLAUDE.md and this program)
Money as decimal/bcmath, never float · external provider calls only in queued jobs, never inside a DB transaction · hold funds before creating a request · `paid` only from verified webhook/lookup (Stripe Transfer is the synchronous exception) · never blind-retry a money POST · additive migrations only · feature flags default OFF · sandbox keys only until go-live · real keys last · no changes to the white-label children · push with `[skip ci]`, do not touch CI triggers · do not guess provider APIs: see D Part 7 and ask the owner.

## Stop and ask the owner if
A provider's real docs differ from the blueprint · a ledger cannot support a required entry type · `payment_charges` cannot be linked to users · queue/cron setup differs from DEPLOYMENT.md · any step would change existing payout behaviour beyond what is written.

## Kickoff prompt (paste to Claude Code, change N)
"Read CLAUDE.md, PROGRESS.md and docs/payouts/00-START-HERE.md, then the five blueprint files. Build ONLY Step N of the build order. Follow every hard rule. Write tests as specified in the Acceptance lines. Run the full local test suite. Update PROGRESS.md with what changed, what was verified, and any deviations. Do not start Step N+1. If anything in Part 7 of Addendum D is needed, stop and ask me."
