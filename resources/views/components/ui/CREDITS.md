# UI element credits

The branded elements in `resources/css/ui-elements.css` + `components/ui/` were
adapted from the operator's hand-picked, MIT-licensed Uiverse.io components,
vendored at https://github.com/SupremeIdeas/Uicomponents (no third-party runtime
is loaded — everything is re-tokenized to the NaaraSim brand and compiled into
our own bundle).

Adapted source families (see the repo for the raw originals + full author list):

| Element | Adapted from |
| --- | --- |
| `x-ui.btn` (nx-btn) | `buttons/` picks — shine-sweep / glow family |
| `x-ui.switch` (nx-switch) | `switch/` + `uiverse-raw-components-batch1/Toggle-switches/` |
| `x-ui.checkbox` (nx-check) | `checkboxes/` + `batch1/Checkboxes/` |
| `x-ui.toast-stack` (nx-toast) | `toast/` (andrew-demchenk0) |
| `x-ui.loader` / `x-ui.skeleton` | `preloader/` (marcelodolza) + `batch1/loaders/` |
| `x-ui.alert` (nx-alert) | `admin-alert-cards/` |
| `x-ui.tag` (nx-tag) | `tags/` |
| `x-ui.upload` (nx-upload) | `upload-utility-elements/` |
| Wallet "My Spending" card (nx-aurora) | `premium-custom-cards/` — Gidarx aurora balance card |
| Admin revenue hero (nx-anim-card) | `premium-custom-cards/` — anand_4957 animated-gradient income card |
| Admin revenue-split donut (nx-donut) | `premium-custom-cards/` — code-town3 donut stat card |
| Dashboard product cards (nx-card3d) | `pricing-top-perks/` — om_5409 + chase2k25 3D glass cards |
| "More" menu promo card (nx-float-card) | `premium-custom-cards/` — ayman-ashine floating-light card |
| Account day/night scene (nx-theme-scene) | `switch/` — witer33 phone sun/moon toggle |
| Wallet collapsible top-up (nx-topup) | `premium-custom-cards/` — Na3ar-17 collapsible payment card |
| `nx-btn--glow` | `buttons/` — mrhyddenn border-glow |
| `nx-btn--get-started` | `buttons/` — catraco tilted get-started |
| `nx-btn--pill-reveal` | `buttons/` — alexmaracinaru label-reveal pill |
| `nx-btn--premium` | `buttons/` — rainbow-gradient pick, re-tokenised to an on-brand teal↔gold shimmer |
| `nx-btn--edit-reveal` / `x-ui.icon-button` | `buttons/` — aaronross1 expanding edit button |
| `x-share-button` (nx-share) | data-driven from admin `SocialLinks` (no single source) |
| `x-reactions` (nx-react) | polymorphic reactions surface (own design, neutral tokens) |

Still reserved: AnthonyPreite + Cobp pricing cards → eSIM plan pricing once the
live plan APIs are active (Module 29).

Remaining categories (premium cards, pricing tables, login forms, nav elements,
date/weather, dropdown, cookies banner, product features) are adapted as their
target surfaces are built (marketing pages Module 27, pricing Module 29,
banners Module 31).
