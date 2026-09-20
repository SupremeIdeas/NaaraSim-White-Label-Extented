# White-Label License Boundary — Forensic Audit

> Phase 1 deliverable of `NAARASIM-WHITELABEL-LICENSE-SURGERY-BLUEPRINT.md`,
> executed with the deterministic classification table from
> `SONNET-GRAPHIFY-EXECUTION-ADDENDUM.md`. **Read-only audit — no code was
> changed to produce it.** This is the basis for Phase 2 (architecture) and
> Phase 3 (surgical removal); nothing may be moved before it exists.

- **Audit date:** 2026-09-20
- **Repos audited:** `NaaraSim` (master), `NaaraSim-WhiteLabel` (WL normal),
  `NaaraSim-White-Label-Extented` (WL Extended)
- **Branch (all three):** `claude/graphify-codebase-mapping-715iyi`

---

## 0. Headline finding

**Both child repos currently carry the full issuer + oversight + distributor
stack, and every piece of it is live and route-reachable — not dead code.**
Each White Label build is, as it stands, capable of: issuing/activating its
own licenses, listing and managing *other* merchants' instances, selling
white-label licenses to sub-merchants (self-service purchase flow), and
serving updates/themes to other instances as if it were the master platform.

This is a direct violation of §0 of the surgery blueprint (only master may
ever issue/upgrade/revoke/resell a license; a child is a consumer + a leaf
puller, never a hub). It is a *live* violation, which raises Phase 3's
urgency for both child repos above "clean up dead code."

**Provenance note (important, and owned squarely):** a large part of this
surface was authored and/or synced into the two child repos *during the
current engagement* — the P21-EXT merchant self-service license flow, the
project intake system, the entitlement/registry work, and most recently the
in-app Merchant White Label Guide + guide-links. The forensic classification
below is deliberately blunt about that: this audit does not grade its own
prior homework kindly. Phase 3 will remove this surface from the children.

---

## 1. Classification (Graphify addendum decision table, applied top-to-bottom)

Categories: **Issuer** (has `issueLicense`/`revokeLicense`/`upgradeTier`/
`payAndActivate`/`payBalanceAndUpgrade`/`generateUniqueKey`/`suspend`/
`restore`/`reject`) · **Oversight** (queries/lists >1 instance, or manages
other merchants' data) · **Distributor** (an endpoint another instance calls
to receive a payload) · **Consumer** (calls out to master for its own
license/updates, stores only for itself) · **Local gate** (reads its own one
row, returns per-feature true/false) · **Out of scope**.

| Artifact | Master | WL | WL-Ext | Category | Belongs in | Reachable in children? |
|---|:--:|:--:|:--:|---|---|---|
| `app/Services/Updater/WhiteLabelLicenseService.php` | ✓ | ✓ | ✓ | **Issuer** | Master only | Yes — backs the `activate`/`register` API + registry actions |
| `app/Http/Controllers/Api/V1/WhiteLabel/WhiteLabelLicenseController.php` | ✓ | ✓ | ✓ | **Issuer** (API surface) | Master only | **Yes — `POST /api/v1/white-label/register` + `activate` registered in both children's `routes/api.php`** |
| `app/Http/Controllers/Api/V1/WhiteLabel/WhiteLabelUpdateController.php` | ✓ | ✓ | ✓ | **Distributor** | Master only | **Yes — `GET updates/check`, `updates/{package}/download`, `updates/report`, `entitlement` registered in both children** |
| `app/Http/Controllers/Api/V1/WhiteLabel/WhiteLabelThemeController.php` | ✓ | ✓ | ✓ | **Distributor** | Master only | **Yes — `themes/check` + `themes/{package}/download` registered in both children** |
| `app/Livewire/Admin/WhiteLabelRegistry.php` + its blade | ✓ | ✓ | ✓ | **Oversight** | Master only | **Yes — `/admin/white-label` (name `white-label`) registered in both children** |
| `app/Http/Controllers/Admin/WhiteLabelIntakePdfController.php` | ✓ | ✓ | ✓ | **Oversight** | Master only | **Yes — `white-label.intake.pdf` route registered in both children** |
| `app/Livewire/MerchantWhiteLabel.php` + its blade | ✓ | ✓ | ✓ | **Oversight / reseller-adjacent** (creates `WhiteLabelInstance` rows + issues/pays licenses for sub-merchants) | Master only | **Yes — `/merchant/white-label` registered in both children** |
| `app/Services/Updater/WhiteLabelProjectIntakeService.php` | ✓ | ✓ | ✓ | **Oversight** (onboarding brief for licenses this instance sells) | Master only | Yes — driven by `MerchantWhiteLabel` + registry |
| `app/Models/WhiteLabelInstance.php` | ✓ | ✓ | ✓ | **Oversight** (bookkeeping of many merchants) | Master only | Yes |
| `app/Models/WhiteLabelLicensePlan.php` | ✓ | ✓ | ✓ | **Oversight** (resale plan catalog) | Master only | Yes |
| `app/Models/WhiteLabelLicensePayment.php` | ✓ | ✓ | ✓ | **Oversight** (resale payment ledger) | Master only | Yes |
| `app/Models/WhiteLabelGuideLink.php` + guide UI + `WhiteLabelGuideLinkSeeder` | ✓ | ✓ | ✓ | **Oversight / reseller-adjacent** (merchant purchase-onboarding guide) | Master only | Yes — rendered inside `MerchantWhiteLabel` |
| `app/Models/WhiteLabelApiLog.php` | ✓ | ✓ | ✓ | **Oversight** (log of calls other instances made to this one) | Master only | Yes |
| `app/Models/DistributedPackage.php` + `PackagePublisher.php` | ✓ | ✓ | ✓ | **Distributor** (catalog + publisher of packages served to others) | Master only | Yes |
| `app/Livewire/Admin/WhiteLabelUpdater.php` | ✗ | ✓ | ✓ | **Consumer** (pulls an update from master, applies to self) | Child only ✅ | Yes — correctly child-only, this is the model to keep |
| `app/Exceptions/LicenseActivationException.php` | ✓ | ✓ | ✓ | Shared generic exception (thrown by issuer on master, by consumer on activation failure) | Shared — keep | n/a |
| `white_label_*` migrations/tables (instances, plans, payments, guide_links, api_logs, distributed_packages) | ✓ | ✓ | ✓ | **Oversight/Distributor storage** (tables shaped to hold *many* merchants' records) | Master only (children need at most one local self-license row) | Present in both children |

### Correctly-scoped today (keep, do not remove)
- `WhiteLabelUpdater.php` — the child-side "pull an update from master"
  component. This is the one piece that got the direction right; Phase 3's
  new consumer stack should mirror its shape.

### To be built for children in Phase 3 (do not exist yet)
- `app/Services/Updater/WhiteLabelLicenseClient.php` — thin consumer: stores
  this instance's own key, calls master's `activate`, persists the returned
  entitlement in a **single local row**, exposes `hasFeature()`.
- A minimal local activation screen (merchant pastes the key master issued).
- A single local license-state table (key, tier, entitlement flags,
  last-checked-at) — never a multi-merchant-shaped table.

---

## 2. Reachability evidence (why this is "live," not dead code)

Confirmed by direct inspection of both child repos' route files:

**`routes/web.php` (both children):**
- `Route::get('/merchant/white-label', MerchantWhiteLabel::class)` → line ~305
- `Route::get('/white-label', WhiteLabelRegistry::class)->name('white-label')` → line ~439
- `Route::get('/white-label/intake/{intake}/pdf', WhiteLabelIntakePdfController::class)` → line ~442

**`routes/api.php` (both children):**
- `POST v1/white-label/register` + `activate` → `WhiteLabelLicenseController`
- `GET v1/white-label/updates/check|download|report|entitlement` → `WhiteLabelUpdateController`
- `GET v1/white-label/themes/check|download` → `WhiteLabelThemeController`

A child instance that ships as-is exposes the issuer API and the distributor
API to the network, and the oversight registry to its admin. That is the
capability §0 says must not exist outside master.

---

## 3. Not yet inspected line-by-line (flagged for Phase 3 execution)

- Whether any child migration must be renamed-not-dropped because it holds
  real rows on a live merchant install (surgery §3c live-data caution).
  **This cannot be determined from the repo alone — it depends on live
  database state and must be checked against the actual deployment before
  any destructive migration ships.**
- Admin nav partial entries linking the oversight screen in children (to be
  removed alongside the routes).
- `tests/` in both children that exercise issuance/oversight/serving — these
  pass today, which is additional proof the capability is live; they will be
  removed/replaced with consumer-side tests in Phase 3.

---

## 4. Consequence for sequencing

Per the 00 Master Blueprint Index, Tier 0 (tenant isolation, verified on the
live servers) must pass before Tier 1 (this surgery) ships to production.
This audit (read-only) is safe to produce now; the **destructive Phase 3
removal is gated** on (a) live tenant-isolation verification the operator
performs on the servers, and (b) explicit confirmation that removing the
merchant-sales/licensing surface from the children — including work synced
into them during this engagement — is intended.
