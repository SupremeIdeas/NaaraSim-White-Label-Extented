# Theme placeholder assets — swap these later

Per-theme home hero art seeded for the Theme System (Batch 2/§3). Each theme
carries its own hero image (`theme_presets.hero_assets.dashboard`), shown on the
dashboard home when the theme is applied. Files live under
`public/img/themes/` and were supplied by the owner (real Naara hero art).

**2026-09-04 update:** the 14 original persona palettes were recoloured (owner
request — too many read as teal/gold variations of Naara Official) and 5 brand-
new personas were added, so the platform now ships 20 themes total. Names
changed for 3 slugs (`aurora-shift` → Indigo Current, `coral-current` →
Crimson Current, `slate-signal` → Velvet Reserve); the rest kept their display
name with a recoloured palette. Slugs, hero assignments, radius/typography and
layout-variant wiring are unchanged for all 14 — see
`database/seeders/ThemePresetSeeder.php` and the
`2026_09_04_150000_refresh_theme_preset_palettes` migration for the actual
before/after values.

**Owner note:** these are treated as placeholders — swap for final per-theme art
whenever ready. There are still only 8 unique images now covering 19 personas
(up from 14), so more themes SHARE an image (marked ⟳). Supply more unique
heroes to make every theme 1:1.

| Theme | Hero image (`/img/themes/…`) | Unique? |
|---|---|---|
| Indigo Current (aurora-shift) | islands-female.webp | ⟳ (shares with Verdant Pulse) |
| Boarding Pass (sunset-transit) | balloons.webp | ✓ |
| Midnight Signal | portal-gateway.webp | ⟳ (shares with Cobalt Frost) |
| Paperwhite | app-ui-phone.webp | ✓ |
| Ledger (fintra-clean) | before-after.webp | ✓ |
| Origin Bold | branded.webp | ⟳ (shares with Rosewood Luxe) |
| Capable | app-ui-phone.webp | ⟳ (shares with Paperwhite) |
| Horizon (waitlisty-soft) | balloons.webp | ⟳ (shares with Boarding Pass) |
| Grid Nine (genius-grid) | before-after.webp | ⟳ (shares with Ledger) |
| Skyline (lander-hero) | worldwide.webp | ⟳ (shares with Arctic Teal) |
| Aries | portal-gateway.webp | ⟳ (shares with Midnight Signal) |
| Emerald Route | islands-male.webp | ⟳ (shares with Mango Burst) |
| Crimson Current (coral-current) | islands-female.webp | ⟳ (shares with Indigo Current) |
| Velvet Reserve (slate-signal) | worldwide.webp | ⟳ (shares with Skyline) |
| Verdant Pulse *(new)* | islands-female.webp | ⟳ (shares with Indigo Current) |
| Cobalt Frost *(new)* | portal-gateway.webp | ⟳ (shares with Midnight Signal) |
| Mango Burst *(new)* | islands-male.webp | ⟳ (shares with Emerald Route) |
| Arctic Teal *(new)* | worldwide.webp | ⟳ (shares with Skyline) |
| Rosewood Luxe *(new)* | branded.webp | ⟳ (shares with Origin Bold) |
| Naara Official | — (no theme hero; keeps admin HeroBackground behaviour) | n/a |

**Precedence on the dashboard home:** an admin-uploaded `HeroBackground` still
wins (an explicit choice); otherwise the active theme's hero shows; otherwise
the title + tiles render with no image (unchanged degrade).

**Reference-only images NOT shipped** (from `naarathemeseedimages.zip`, per the
blueprint §7 — they are screenshots of a different product's UI, supplied to
convey layout energy, never product imagery): `hero-inspo-traveler-map-unbranded`,
and the `dashboard-*` / `esim-marketplace-*` mockups.

**Storage note:** seeded to `public/img/themes/` (committed static assets) so
they work on both VPS and shared cPanel without Wasabi keys. When live Wasabi
keys exist, per-theme hero uploads can flow through `MediaStorage` like other
admin imagery; these committed defaults remain the fallback.
