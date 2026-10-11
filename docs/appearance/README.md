# Appearance / skin system: contract files (master only for now)

Saved 2026-10-02 from the owner's uploads. These are the permanent source of truth for the skin program.

| File | Role |
|---|---|
| `wireframes/naara-dashboard-system-v9-surface-core13.html` | Visual contract: engine CSS, components, tokens, and the **core 13 skins** (surface, calm, vivid, ledger, dotgrid, aurora, daybreak, paper, clay, glass, halo, neo, noir). Where the HTML and the blueprint disagree on a *visual* value, the HTML wins. |
| `wireframes/naara-dashboard-system-v15-new22-skins-lab.html` | Visual contract for the **22 newer skins** (adire, blueprint, clarity, neon, passport, golden, vault, boarding, chipset, wavelength, airmail, handset, prism, receipt, nebula, harmattan, ankara, danfo, asooke, tide, canopy, pop). Same engine and pages. |
| `PROMPT-20-SURFACE-SYSTEM-BLUEPRINT-rev11.md` | Engine, tokens, components, appearance preferences, whole-dashboard rollout, contrast contract, codebase reconciliation. Where it disagrees with the HTML on *behaviour or data*, the blueprint wins. |
| `PROMPT-21-SKIN-ACCESS-JOURNEYS-UNLOCK-BLUEPRINT.md` | Access layer: free vs Pro skins, trials, plan tiers, goal/journey unlocks, mood presets, lapse handling. Builds on Prompt 20. |

Naming map for the port (wireframe -> production): `data-skin/data-accent/data-theme` -> `data-nx-skin/data-nx-accent` + `.dark`; short vars (`--text`, `--teal`) -> `--nx-*`; wireframe classes (`.card .op .mr .tile .pill .cta`) -> `ns-*` hooks / `x-nx.*` components.

Business Suite (Prompts 14-19) stays pending on its own branch; do not mix.
