# The skin contract: how every page, now and later, obeys the member's skin

Owner rule (2026-10-03): the whole signed-in product is skin-aware, and anything we add later must inherit the selected skin on demand.

## What is enforced mechanically
1. **Registration** (`tests/Feature/Appearance/SkinContractGuardTest`): any Livewire page that renders in `components.layouts.customer` must be listed in `config/appearance.php` -> `coverage.batches`. A new page that is not listed fails the suite.
2. **Token-only** (`php artisan nx:coverage`, gated per batch in `NxCoverageTest`): a converted view contains no hex colours, no Tailwind palette colours (`bg-slate-*`, `text-emerald-*`...), no brand classes and no per-element `dark:` variants. Everything reads `--nx-*` tokens, so a skin restyles it with no page code.
3. **Contrast** (`tests/visual/skins/contrast-pages.cjs`, `more-contrast.cjs`, `icons.cjs`): every skin x mode x accent on the real pages, text 4.5:1 (3:1 large), icons 3:1. Run after converting a page.

## How to build a new page (the recipe)
- Wrap in `<x-nx.page>` (or `bare` when embedded). Use `x-nx.*` components (`card`, `row`, `list`, `note`, `pill`, `cta`, `field`, `banner`, `feature-link`, `balance-hero`, `showcase`...) and `ns-*` hooks; never invent a colour.
- Colours come from tokens only: `--nx-text / -2 / -3`, `--nx-surface / -2 / -3`, `--nx-line / -strong`, `--nx-teal / -ink`, `--nx-cta-a / -b`, `--nx-ok / warn / bad`. Small coloured text uses `.ns-linkink` (teal ink mixed toward the skin's text ink), never raw `--nx-teal-ink`.
- Text 3 is for chrome (chevrons, hints); sentences use text 2.
- Anything that sits on a photo or a saturated card gets its own scrim and explicit label colour.
- Register the view in `coverage.batches`, run `php artisan nx:coverage`, then the contrast harness.
- Third-party widgets (composer, chat bubbles, carousels, sheets) expose CSS variables or `ns-*` hooks and are skinned in `resources/css/nx-layout.css` or `composer.css` under `html[data-nx-skin]`.

## Where a skin applies
Member dashboard + admin's own panel (`customer`/`admin` layouts), and the Help Center / Nia support section for signed-in members (`help-center` layout, `skin` prop). **Nia keeps its own page background** (owner exception); everything on it (header, transcript, bubbles, composer, nav) takes the skin. Marketing, login/auth, emails and PDFs never carry a skin.

## Bottom nav
Keeps its structure and behaviour; takes skin surface, hairline, elevation, radius and tab states (`.nx-bottomnav`). Glass keeps its frost there; the More sheet is solid in Glass.
