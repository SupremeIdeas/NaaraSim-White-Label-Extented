# Skins on NaaraSim-White-Label-Extented: Batch 0 audit and path decision (Prompt 22)

**Path decision: A (port the master engine into the fork, then gate it).** The fork had no skin engine at all at its committed HEAD
(`git show HEAD:config/appearance.php` absent; no `appearance_presets` / `user_appearance` tables, no `x-nx.*` components, no `--nx-*` tokens).
So the work is a port plus a licence gate, not a reconcile.

## What was ported
- Engine: `config/appearance.php` (35 skins, pruned of master-only access/Pro fields), `AppearanceResolver`, `UpdateUserAppearance`, `AccentDeriver`, tables migration, `enable_free_launch_skins` migration, `resources/css/nx-*.css`, `x-nx.*` components, layouts (app, customer, admin, app-shell), `app.js`, icon sprite additions.
- Member pages converted to tokens: wallet, account (+ privacy), numbers landing/modals, my-lines, eSIM catalogue/detail, messages, brand pages, widgets.
- Tests: appearance suite (fixtures now stand in for a fully licensed install), `SkinContractGuardTest` (skips the Nia check where the fork has no Help Center layout).

## What was deliberately NOT ported (master-only, per CLAUDE.md)
- N2N (permanent), Naara Pro badge/grants (`hasNaaraPro`, `x-naara-pro-badge`, `pro_*` lang keys), notification-tone/sounds library, More-apps carousel, Prompt 21 access layer (D5), license issuer/oversight, `SkinAllowance`, `EntitlementPayload`, `config/white_label_skins.php`.

## Fork-only code (the consumer)
`LicensedSkins` (stores `skins.allowance`, selection, versioned cache key, preset sync), `AppearanceResolver::platform()` intersection with `available()`, `WhiteLabelUpdateClient::refreshEntitlement()` storing `skins.allowance`, `Admin\SkinSelection` + route `admin.skins`, read-only skin list on the platform Appearance page, allowance row on the Updater panel, `skins:verify`.

## Evidence
Screenshots under `docs/audits/skins-wl/` (member dashboard, numbers, wallet, appearance; admin Your skins; Updater panel; 390 and 1280, light and dark) with allowance 2 and selection Surface + Neo: the member Appearance page reads "Skin (2 available)", the picker reports "2 of 2" and 33 hidden. `php artisan skins:verify` passes (35/35 selectors in the built CSS, one default skin, one default accent).
