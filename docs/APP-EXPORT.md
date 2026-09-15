# NaaraSim — Installable App Export (Android + iOS)

This is the operator handoff for the App Export module. It lists what the
platform builds automatically, and — just as importantly — the steps only Frank
can do (paid accounts, identity verification, store review). **A compiled build
is not a published app.** The admin UI never claims otherwise, and neither
should anyone reading this.

## Approach

Capacitor in **remote-URL mode**: the native Android/iOS shell is a thin WebView
pointed at the production HTTPS domain, so the existing server-rendered Livewire
app runs inside it with no rebuild into a static bundle. Config lives in
`capacitor.config.json` — set `server.url` to the real production domain and
`appId` to the final reverse-DNS package id before the first real build.

## What the platform builds for you

- **PWA layer** — a dynamic `manifest.webmanifest` (admin-editable name / icon /
  colours) and install-shell support added to `public/sw.js` (offline fallback
  only; the existing web-push logic is untouched).
- **Admin → App Builder** (`/adminmaster/app-builder`) — app name, colours,
  version/build number, changelog, icon + splash upload, preloader; the Android
  keystore upload (encrypted at rest); store-live toggles; CI webhook; the
  "Download the app" CTA placement toggles; **Generate Build** + live build
  history with logs.
- **Android CI** — `.github/workflows/android-build.yml` builds a signed APK
  (direct download) + AAB (Play Store) on a Linux runner and calls the status
  webhook back. Triggered by App Builder's "Generate Build" via GitHub's
  `repository_dispatch` API (`TriggerAppBuildJob`) — **never Codemagic**, since
  Android needs no paid macOS runner (owner audit, 2026-09-15).
- **iOS CI** — `codemagic.yaml`'s `ios-release` workflow on Codemagic's macOS
  build service (or any other CI whose completion hook can POST the same
  signed `{build_id, status, artifact_url}` shape — App Builder's "Generic /
  custom webhook" option). Android and iOS are configured completely
  separately in App Builder; there is no single shared CI toggle.
- **Public `/download` page** — live APK button + QR the moment an APK build is
  marked ready; App Store / Play badges appear only when you flip that listing
  live.

### The Android/iOS native platform folders are NOT committed
`android/` and `ios/` don't exist in this repo — they're generated fresh on
every CI run (`npx cap add android` / `npx cap add ios`, both idempotent) from
`capacitor.config.json` + the compiled web assets in `/public`, exactly like
`/public/build` is rebuilt from source rather than hand-edited. This was a
real gap found during the 2026-09-15 audit: `@capacitor/*` wasn't even an npm
dependency and neither native platform had ever been scaffolded, so the
original `android-build.yml` would have failed on its first real run. Both
are now real npm dependencies (`package.json`) and both workflows scaffold
the platform themselves — nothing extra to do locally.

### Required GitHub Actions secrets (Android)
| Secret | What it is |
|---|---|
| `ANDROID_KEYSTORE_BASE64` | base64 of your release keystore |
| `ANDROID_STORE_PASSWORD` | keystore password |
| `ANDROID_KEY_ALIAS` | signing key alias |
| `ANDROID_KEY_PASSWORD` | signing key password |
| `APPEXPORT_CI_SECRET` | shared HMAC secret; also set `APPEXPORT_CI_SECRET` in the app `.env` |
| `WASABI_ACCESS_KEY_ID` / `WASABI_SECRET_ACCESS_KEY` / `WASABI_BUCKET` / `WASABI_ENDPOINT` | same Wasabi account this app already uses for storage — the workflow pushes the signed APK there (public-read) so `/download` has a real, permanent link. Without these the build still succeeds but is reported `failed` with no artifact, since there's nowhere public to point the download button at. |

Also set in App Builder → Store & download page → "Android CI — GitHub
Actions": the **GitHub token** (Admin → API Keys → App Export — a
fine-grained PAT scoped to this one repo, `Contents: read` + `Actions:
write`, nothing more) and the **repo** (`owner/repo`) the workflow lives in.

> ⚠ **Keystore backup is existential.** Lose the keystore and the app can never
> be updated under the same package id again — you'd have to publish a brand-new
> listing. Back it up somewhere permanent the moment it's generated. The App
> Builder shows a one-time warning until you confirm you've done this.

## What only Frank can do (accounts, fees, review — no code changes this)

- **Apple Developer Program** — $99/year, tied to your/business identity, with
  Apple's own verification. Required before any iOS build can be signed or
  submitted.
- **Google Play Console** — $25 one-time + identity verification (photo ID; a
  D-U-N-S number for an Organization account). **New accounts must run a closed
  test with a minimum of 12 real testers for ~14–20 days before the app can go
  public.** Plan the first Android release timeline around this — the pipeline
  cannot skip it.
- **Cloud macOS build service** (for the iOS IPA) — `codemagic.yaml`'s
  `ios-release` workflow is already written for Codemagic specifically (App
  Builder → CI provider → "Codemagic", plus its API token/app id/workflow id).
  Prefer a different macOS CI (Capawesome Cloud, Capgo, a GitHub Actions macOS
  runner)? Pick "Generic / custom webhook" instead and point its completion
  hook at the same `/webhooks/appbuild/ci` status endpoint Android's own CI
  uses — swapping providers is config, not code, either way. Separate cost
  from Apple's $99; under Codemagic's Team settings, add your Apple
  Developer distribution certificate + an App Store Connect API key (Apple's
  modern, non-interactive signing method).
- **Store listing content** — screenshots, descriptions, privacy-policy URL,
  content-rating questionnaire, support contact. The App Builder stores what it
  can, but you originate the content.
- **The review itself** — Apple ~24–48h once submitted; Google is bound up in
  the closed-testing window above.

## iOS reality check (don't fight it)

Outside the EU, Apple only allows installing an app via the App Store — a website
cannot trigger a live iOS install regardless of how the IPA is packaged. The
`/download` page therefore only ever shows the App Store badge for iOS (once the
listing is live), never a direct-install button. The EU DMA web-distribution
exception exists but still needs Apple's per-release notarization, carries extra
fees, and lapses for a user outside the EU for 30+ days — not worth building
around unless EU distribution becomes a specific priority.

## Publish-readiness checklist (in the admin)

App Builder shows a live **Publish readiness** panel that ticks green as each
store requirement is satisfied — app identity/icon/splash/version, **privacy
policy URL**, support contact, **account-deletion path** (both stores now
require one; we point it at `/account`), short/full descriptions, **data-safety
/ privacy declaration**, content rating, and Android signing/target-API/build.
Items marked **(you)** are the account/review steps only Frank can do (Apple
$99/yr, Google Play $25 + 12-tester closed test, cloud macOS build service) —
they never count toward the technical "ready" score, so a green technical build
is never mistaken for a live listing.

## iOS payment structure (App Store review, BUILD-5 §6)

The iOS build restructures 4 product lines per
`docs/APP-STORE-PAYMENTS-COMPLIANCE.md` — `App\Support\AppPlatform::isIosBuild()`
detects the native iOS wrapper (via the `median` user-agent token every native
build carries) and:
- **Wallet top-up** — no free-floating "add funds" entry point on iOS; pay at
  the point of buying an eSIM/number instead.
- **Wizard fee** — never charged on iOS (hidden, not routed through IAP).
- **Merchant V2 upgrade fee** — blocked on iOS with a message pointing to
  web/Android.
- **Naara Gift** — off by default on iOS (App Builder → Store listing →
  "Enable Naara Gift on iOS") until a per-brand Apple-guideline review is done.

None of this affects web or Android — the same wallet/Wizard/merchant-upgrade
flows work exactly as before there.

## First-run onboarding

Admin adds **3–4 portrait slides** (image + title + subtitle) in App Builder →
First-run onboarding. On first app open the installed app enters `/get-started`
(the PWA `start_url` flips there automatically when slides exist), the user
swipes/Next through the slides, and lands on the **login page** — the correct
gateway into the dashboard. A `localStorage` flag skips it on later opens, and
signed-in users are sent straight to the dashboard.

## First-release checklist
1. Set `capacitor.config.json` `server.url` + `appId`.
2. Enrol in Apple Developer + Google Play Console (above).
3. Generate + upload the Android keystore; **back it up**; confirm the warning.
4. Add the GitHub Actions secrets (Android table above) + the App Export
   GitHub token and `github_repo` in App Builder; run an Android APK build;
   verify `/download` shows a real, working link.
5. Pick an iOS CI provider (Codemagic by default — `codemagic.yaml` is
   already written for it) and configure it in App Builder; run an iOS IPA
   build.
6. Prepare store listings; submit; run Google's closed test.
7. Only after a listing is genuinely live, flip its store-live toggle so the
   badge appears.

## What's structurally verified vs. what needs a real account to confirm

Everything above the CI-provider line — the queued-build lifecycle, the
signed-webhook contract, the live poll + auto-download, the GitHub
`repository_dispatch` call, the Codemagic REST API call, the `is_ios_build`
payment gating — is covered by the automated test suite and was exercised
live against a booted instance of this platform. What CANNOT be verified
without real, paid credentials (no Codemagic/Apple/Google account exists in
a dev sandbox): whether GitHub Actions' `ubuntu-latest` runner actually
produces an installable signed APK end to end (the Gradle project itself was
confirmed to scaffold and its `gradlew` wrapper is real — the compile step
itself needs a real keystore to run), and whether Codemagic's iOS pipeline
produces a real signed IPA (needs a real Apple Developer account + signing
identity on Codemagic's side). Don't take either as "proven working" until a
real build has actually completed successfully once.
