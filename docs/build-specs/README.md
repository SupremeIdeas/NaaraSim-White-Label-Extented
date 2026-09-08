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

Progress is tracked in `../../PROGRESS.md`; platform state in `../PLATFORM-STATE.md`.
