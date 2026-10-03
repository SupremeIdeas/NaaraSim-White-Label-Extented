# Contrast deviations from the wireframes (and why)

Prompt 20 §14 says the canonical token values are "not yet proven" and orders: *if a pair fails, darken the token, never loosen the test*.
`tests/Feature/Appearance/SurfaceTokenContrastTest.php` asserts every text-on-surface pair and every accent's CTA label in both modes.
It found exactly two failures in the wireframe values; both are fixed with the smallest change that passes. Everything else is byte-identical to V9.

| Token | Wireframe | Production | Why |
|---|---|---|---|
| `--nx-cta-a` (dark, Naara Teal) | `16 179 170` | `15 165 157` | White CTA label (18px/700, large text, floor 3:1) on the top of the gradient measured **2.61:1**. New value measures 3.05:1. A ~9% step toward the ink colour: visually the same teal, a touch deeper at the top of the button. |
| `--nx-text-3` (light) | `88 114 127` | `87 113 126` | Tertiary text on the page canvas measured **4.49:1** (floor 4.5). New value measures 4.56:1. |
| `--nx-teal-ink` (light, Naara Teal) | `0 127 121` | `0 123 117` | Teal text/icons directly on the page canvas measured **4.30:1** (4.87 on a white card, so it only fails on the canvas). New value 4.53:1 on canvas, 5.13:1 on white. |

## Found by the per-skin scan (Skins S2)

`tests/visual/skins/contrast.js` renders the Appearance page for **every skin x accent x mode (35 x 6 x 2 = 420 states, 21,000 text nodes)**,
hides the text, samples the real pixels behind each text box, and compares them with the real computed text colour (AA: 4.5:1, or 3:1 for
large text). Failures it found in the wireframes, and the fix for each. Hand-written fixes live in `resources/css/nx-skins/_contrast-fixes.css`
(never generated, so regenerating the skins cannot lose them); the gradient change is a generator patch (`CONTRAST_PATCHES` in `scripts/nx_port.py`).

| Where | Wireframe behaviour | Measured | Fix |
|---|---|---|---|
| Primary CTA and the selected segment, **every skin** | Gradient fades `cta-a` to `cta-b` across the whole height, so the lightest pixels sit under the label | 3.6 to 4.0:1 (all 35 skins) | The fade now finishes in the top 30%: `linear-gradient(180deg, cta-a, cta-b 30%)`. The label sits entirely on `cta-b` (5.4:1 or better). The look is the same gloss on the top edge. |
| Handset, primary card | Inverts the card but reads its fill from `--nx-text` on the same element that re-points `--nx-text` at the canvas, so fill = label colour | **1.0:1** (an invisible card and label) | Fill reads a separate `--nx-hs-ink` that the inversion does not touch. |
| Boarding Pass, primary card, light mode | Dark accent gradient with the dark light-mode label colour | 1.75:1 | Gets the same on-fill token set every other filled card uses. |
| Glass, dark | Secondary text over the frosted card and colour washes | 4.13:1 | `--nx-text-2/3` lifted to `198 216 228` / `168 192 207`. |
| Glass, light | Back link over the top-left accent wash (rose, violet, ocean) | 3.87:1 | Wash alphas halved (.38/.30/.14 to .18/.16/.10). |

## Found by the page scan (Skins S3, Batch 1)

`tests/visual/skins/contrast-pages.cjs` runs the same pixel scan over the **real converted pages** (Numbers, Verify/Rent/Line sheets, eSIM
detail, Checkout), every skin x mode x accent. Disabled controls are exempt (WCAG), and behind a modal only the modal is judged.

| Where | Measured | Fix |
|---|---|---|
| `--nx-gold-ink` (light) | Premium/Featured outline pills 3.4 to 4.4:1 on tinted cards | `133 92 0` to `115 79 0` (6.0:1 and better on every card tint) |
| Neutral / "best" pill, light mode | 3.4 to 4.1:1 (teal ink on a teal tint) | text = the CTA's dark end mixed 30% toward the ink colour, tint .16 to .12 |
| Any pill on the saturated Verify card | 3.6:1 (the card colour changes with the accent) | pill gets a dark scrim and white text, like the tile on the same card |

## Found by the 10-page scan (Skins S3, 2026-10-03)

`contrast-pages.cjs` over **Home, Wallet, Numbers, eSIM catalogue, Account, Rewards, Referrals, My Journey, Receipts, Data estimator**,
every skin x light/dark x accent (14,770 text nodes per accent). 365 failures at the start of the pass, 0 at the end. Real fixes (not test changes):

| Where | Measured | Fix |
|---|---|---|
| Home hero headline (gradient words) | brand-gold end read as pale yellow on light skins; scan could not see it | Stops are now the skin's own ink tokens mixed toward its text ink (`.ns-gradtext`); the scan reads the real resolved gradient |
| Small emphasised/link text on the canvas ("See more", "Manage all", rewards intro, estimator days) | `--nx-teal-ink` is tuned for fills and display type: 2.3 to 4.4:1 at 12-15px on 20+ light skins | New `.ns-linkink`: teal ink mixed 60% toward the skin text ink |
| Table headers, muted copy, muted stat cells | text-3 on tinted surfaces: 2.6 to 3.2:1 (neo, prism, aurora, calm, dotgrid ...) | Sentences use text-2 (text-3 stays for chevrons/hints) |
| eSIM destination cards on a pale photo | white label at 2.5:1 | Scrim 0.78 to 0.90 at the base, deeper mid-stop |
| Locked My Journey cards | opacity .72 pushed small copy under 4.5:1 | opacity .86; the "Not yet" hint uses text-2 (the OK green only on completed steps) |
| Referral / payout card | hard-coded Tailwind slate/amber/emerald (fixed in both modes, ignored the skin) | Converted to skin tokens (`--nx-text*`, `--nx-ok/warn/bad`, `ns-card`) |
| Hero ghost "Get Number" (blueprint, ledger, neon, noir) | translucent skin CTA background behind a dark label: 2.6 to 4.3:1 | Solid skin surface + text ink |
| Legacy `nx-btn--ghost` inside converted pages | brand teal on skin surfaces (1.2 to 3:1 on handset/neo/pop/vivid) | Skin ink + hairline; Vivid keeps white-on-dark-chip inside its gradient cards (incl. hover) |
| Handset (light) status inks | ok/bad/warn pale on the green LCD: 3.4 to 4.0:1 | Darker status inks in that skin |
| Receipt (light) OK ink | 4.39:1 | `3 92 64` |
| Vivid stat cells | white label on the pale light-mode surface: **1.1:1** | Dark glass chip inside the saturated card |
| Glass (light and dark) balance card | accent gradient at 80-92% alpha over the frosted canvas, white copy 2.7 to 4.4:1 | Accent deepened toward navy (same recipe as Vivid), sublabel opacity 1 |

### More sheet (owner direction, 2026-10-03)

The More menu is **not** frosted in the Glass skin. The sheet is solid, the grid/list are quiet solid tiles with a hairline, no backdrop blur
anywhere inside it, and the sheet now stacks above the floating widgets (they used to sit on top of the Account tile). 2,026 text nodes scanned
across all 35 skins x light/dark x grid/list (`more-contrast.cjs`): 0 failures. A stylesheet lint test pins the Glass rule and the z-index.

## Deliberate differences from the wireframe (not contrast)

* **Previews show the skin as it will look in the member's current mode.** In the wireframe, five skins (Handset, Receipt, Harmattan, Pop, Paper)
  keep a *light* mini preview while the page is dark, because their dark palette is defined for the page only. The picker now previews the same
  dark palette the page will use. The pixel-diff harness lists these five dark minis as expected differences. Handset's primary card also differs (30-35%) on purpose: the wireframe's card is invisible (see the contrast table).
* **The Numbers bento is 3 / 3 / 6 / 3 / 3 / 6 columns** (Verify and Rent equal, as the spec's locked layout says), not the older 4 / 2.
* **Skin rules that the wireframe wrote against the page** (`[data-theme=dark][data-skin=x] ...`) are also applied to that skin's preview, so a
  preview never looks different from the real thing once picked.
