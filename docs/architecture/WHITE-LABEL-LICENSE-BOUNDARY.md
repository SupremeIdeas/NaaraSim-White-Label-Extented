# White-Label License Boundary — Target Architecture

> Phase 2 deliverable of `NAARASIM-WHITELABEL-LICENSE-SURGERY-BLUEPRINT.md`.
> This is the design of record **and** the deterministic execution manifest
> for Phase 3. Checked into master; the same boundary applies to all three
> repos. Grounded in the committed Phase 1 audit (`docs/audits/`), not guessed.

---

## 1. Capability table (every cell explicit — no blanks)

| Capability | Master (`naarasim-core`) | White Label (normal) | White Label Extended |
|---|:--:|:--:|:--:|
| Issue a license (`issueLicense`, key generation) | **Yes** | No | No |
| Upgrade / revoke / suspend / restore / reject a license | **Yes** | No | No |
| Register / activate a license **as the issuer** (inbound API) | **Yes** | No | No |
| **Consume** a license — call master's `activate`, store own entitlement | No (never consumes from itself) | **Yes** | **Yes** |
| View the oversight registry of all instances | **Yes** | No | No |
| Sell / onboard a white-label license (merchant purchase flow, intake, plan catalog, resale earnings) | **Yes** | No | No |
| **Serve** an update/theme (distributor endpoint others call) | **Yes** | No | No |
| **Pull** an update/theme (call master, apply to self) | No | **Yes** | **Yes** |
| Apply a local feature lock/unlock (`FeatureEntitlements`) | Inert (`isMaster()` → nothing locked) | **Yes — real locks from master's list** | **Yes — but list is always empty ⇒ nothing locked** |
| Resell / redistribute the underlying code as a new reseller layer | **No** | **No** | **No** |

**What a license entitles a merchant to** (spelled out per §0/Phase 2.1): the
right to *run* one white-label instance that stays validated against master
and pulls updates from master. It does **not** entitle them to redistribute
the code, issue sub-licenses, or act as an issuer/distributor to anyone else.
That is why every "resell" cell above — master included — is **No**.

**Extended vs normal:** identical code after this surgery. The *only*
difference is the entitlement lock list master returns: for Extended master
returns an empty list (nothing locked); for normal, master returns the
tier-appropriate locks. Extended has no per-feature conditionals of its own —
its only gate is "is the license valid at all."

---

## 2. Child → master API contract (the only cross-instance calls that exist)

All are **outbound from the child to master**; master is the only server.
The child authenticates with the Sanctum token master issued it, carried in
`.env` as `NAARA_UPDATE_API_TOKEN` (see §4 decision A).

- **Activate / validate** — `POST {master}/api/v1/white-label/activate`
  (master side: `WhiteLabelLicenseController@activate` → real
  `WhiteLabelLicenseService`). Child sends its license key + fingerprint;
  master responds `{ token, entitlement: { level, locks[] }, tier, status }`.
- **Entitlement re-check** — `GET {master}/api/v1/white-label/entitlement`
  (child side: `WhiteLabelUpdateClient::refreshEntitlement()`, already built).
  Response `{ level, locks[] }` is stored via `FeatureEntitlements::store()`.
- **Update / theme check + download** —
  `GET {master}/api/v1/white-label/{updates|themes}/check` and
  `/{package}/download` (child side: `WhiteLabelUpdateClient`, already built;
  applied by the untouched `UpdateApplier`/`PackageVerifier`).
- **Outcome report** — `POST {master}/api/v1/white-label/updates/report`.

The child **exposes none** of these as inbound routes. It only calls them.

---

## 3. Verbatim guard comment (copied above every issuer/oversight/distributor class in master)

```
/**
 * BOUNDARY: master-only. This class issues/oversees/distributes across
 * white-label instances. It must NEVER exist in a white-label build
 * (normal or Extended) — a child is a consumer + a leaf puller, never a
 * hub. See docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md. CI fitness
 * tests in the child repos fail the build if this reappears there.
 */
```

---

## 4. Flagged decisions & assumptions (licensing/revenue — stated, not guessed)

**A. Activation stays `.env`-token-based; the in-app screen is status-only,
not a token writer.** `config/updater.php` deliberately states the API token
lives in `.env` only, "never in the database." A merchant-facing screen that
persisted a fetched bearer token into the DB would violate that deliberate
security posture. So: the operator obtains the token from master at issuance
and sets `NAARA_UPDATE_API_TOKEN` in `.env` (unchanged from today); the child's
in-app surface is the existing **White Label Updater** admin screen, extended
with a read-only "license & entitlement status + re-check" panel. This
satisfies the consumer-visibility intent of surgery §3b without introducing a
DB-stored bearer token the codebase's own config rules reject. *If the owner
prefers a true paste-key-in-UI flow, that is a separate decision that requires
relaxing the `.env`-only rule — flagged here rather than assumed.*

**B. Master-only tables are removed from the child migration set (fresh-install
correctness); already-deployed children keep orphan tables until a confirmed
follow-up.** Per surgery §3c's live-data caution, no destructive `drop`
migration ships. Removing the create/alter migrations means a *fresh* child
install never builds `white_label_instances`, `white_label_license_plans`,
`white_label_license_payments`, `white_label_project_intakes`,
`white_label_guide_links`, `white_label_api_logs`, `distributed_packages`,
`platform_earnings` (or their alters). An already-deployed child keeps those
tables as harmless orphans once the code that reads them is gone; a follow-up
rename→drop migration is added only after the operator confirms no live rows
(a live-server check). Entitlement on the child lives in the flat
`white_label.entitlement_locks` Setting (via `FeatureEntitlements`), never in
`white_label_instances`, so nothing kept depends on the removed tables.

**C. The PlatformEarnings / resale-revenue stack is child-removed.** It only
makes sense on the issuer (master), which books revenue from selling licenses.

---

## 5. Execution manifest (Phase 3, applied identically to BOTH child repos)

### DELETE (master-only surface)
- Controllers: `Admin/WhiteLabelIntakePdfController`, `Api/V1/WhiteLabel/DistributesPackages`, `.../WhiteLabelLicenseController`, `.../WhiteLabelThemeController`, `.../WhiteLabelUpdateController`
- Middleware: `EnsureWhiteLabelApiEnabled`, `EnsureWhiteLabelInstanceUsable` (+ alias registrations)
- Livewire + blades: `Admin/WhiteLabelRegistry` (+blade), `MerchantWhiteLabel` (+blade)
- Models: `DistributedPackage`, `PlatformEarning`, `WhiteLabelApiLog`, `WhiteLabelGuideLink`, `WhiteLabelInstance`, `WhiteLabelLicensePayment`, `WhiteLabelLicensePlan`, `WhiteLabelProjectIntake`
- Services: `Platform/PlatformEarningsService`, `Platform/PlatformEarningsException`, `Platform/PlatformWithdrawalService`, `Updater/PackageDistribution`, `Updater/WhiteLabelActivityLogger`, `Updater/WhiteLabelLicenseService`, `Updater/WhiteLabelProjectIntakeService`, `Updater/WhiteLabelProjectIntakeException`
- Support: `ThemeAddonCatalog`
- Listener: `ReturnPlatformEarnings` (+ event binding)
- Notification: `WhiteLabelDeploymentReadyNotification`
- Console command: `CheckWhiteLabelDeployTimelinesCommand` (+ schedule entry)
- Seeders: `WhiteLabelLicensePlanSeeder`, `WhiteLabelGuideLinkSeeder`
- PDF view: `pdf/white-label-project-intake.blade.php`
- Migrations creating/altering the master-only tables listed in §4B
- Tests exercising any of the above (issuer/oversight/distributor/resale/intake)

### KEEP (consumer + local gate — the leaf)
- `Livewire/Admin/WhiteLabelUpdater` (+blade) — the pull screen
- `Services/Updater/WhiteLabelUpdateClient` — outbound consumer to master
- `Services/Updater/UpdateApplier`, `PackageVerifier`, `ThemeInstaller`; `Models/PlatformUpdateAttempt`; `config/updater.php`
- `Support/FeatureEntitlements` — local gate (reads the flat Setting)
- `Exceptions/LicenseActivationException` — shared generic exception

### TRIM (edit, don't delete)
- `Support/FeatureLocks` — keep the `F_*` key constants + `catalog()`; remove the master-authority level→locks map, `all()`/`saveLevel()` admin config, and the `WhiteLabelInstance::LEVELS` dependency (level decisions are master's; the child only needs the key names for its gates).
- `routes/api.php` — remove every inbound white-label serving route + its middleware group.
- `routes/web.php` — remove `/admin/white-label`, `/white-label/intake/{intake}/pdf`, `/merchant/white-label` + imports.
- `routes/console.php` — remove the deploy-timeline schedule.
- `database/seeders/DatabaseSeeder.php` — drop the two removed seeders.
- `bootstrap/app.php` — drop the removed middleware aliases + the removed listener binding (if bound there).
- Admin nav partial — remove Registry + Merchant-White-Label links; **keep** the Updater link.

### ADD (Extended only)
- Nothing structural. Extended already runs `FeatureEntitlements` and will
  simply always receive an empty lock list from master. Cross-checked against
  §1: no per-feature conditional remains that isn't driven by that list.

### Post-cut verification (before commit, per repo)
1. `composer dump-autoload` clean.
2. Grep the whole app for every deleted class name → zero remaining references.
3. Full `vendor/bin/phpunit` green.
