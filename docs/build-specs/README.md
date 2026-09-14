# Build specs (permanent context)

The numbered NaaraSim build prompts ("NAARA-BUILD-N"), committed here so they
survive session compaction and are always available to Claude Code and the team.

| File | Scope | Runs after |
|------|-------|-----------|
| `NB5-risk-reconciliation-compliance-sweep.md` | Provider-health alerting, financial reconciliation, scaling triggers, App Store payment compliance, final regression sweep | Builds 1–4 |
| `NB6-wizard-pagebuilder-social-follow-lottie.md` | Wizard `naara_connect` fix, page-builder public render, social-follow-to-earn + Brand Partner Hunt, Lottie wiring | Builds 1–5 |
| `NB9-brand-directory-subscriptions.md` | Self-service **paid** brand-directory listings — extends BUILD-6's brand tables (they go hand in hand) | Build 6 |
| `NB12-homepage-audience-tabs.md` | Homepage "Who Naara Is For" audience-tabs section (admin-orderable) | Build 6 (uses homepage section system) |
| `NB-elevenlabs-widget-and-password-toggle.md` | Admin ElevenLabs Convai widget (+ backup provider for downtime) & show/hide password toggle | Standalone |
| `PROMPT21-EXT-merchant-v2-license-tiers.md` | Merchant V2 self-service license: seeded 4-tier plan catalog, balance-to-Extended upgrade, platform earnings wallet, plan carousel, resell-status threshold gating. Owner's own extension of the uploaded `NAARA-PROMPT-21-MERCHANT-V2-SELF-SERVICE-LICENSE.md` — supersedes only its §4.1 | Standalone (master-repo money path) |
| `PROMPT21-EXT2-project-intake-form.md` | Post-purchase project intake form (desired brand name, WhatsApp, 3-way hosting decision + encrypted credentials), admin review (Mark Seen), admin-set autopilot deploy-progress timeline, PDF handoff export, deploy-ready email | PROMPT21-EXT (needs an active license to exist) |

Progress is tracked in `../../PROGRESS.md`; platform state in `../PLATFORM-STATE.md`.
