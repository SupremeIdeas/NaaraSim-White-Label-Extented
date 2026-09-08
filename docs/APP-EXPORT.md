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
  webhook back.
- **Public `/download` page** — live APK button + QR the moment an APK build is
  marked ready; App Store / Play badges appear only when you flip that listing
  live.

### Required GitHub Actions secrets (Android)
| Secret | What it is |
|---|---|
| `ANDROID_KEYSTORE_BASE64` | base64 of your release keystore |
| `ANDROID_STORE_PASSWORD` | keystore password |
| `ANDROID_KEY_ALIAS` | signing key alias |
| `ANDROID_KEY_PASSWORD` | signing key password |
| `APPEXPORT_CI_SECRET` | shared HMAC secret; also set `APPEXPORT_CI_SECRET` in the app `.env` |

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
- **Cloud macOS build service** (for the iOS IPA) — Codemagic, Capawesome Cloud,
  Capgo, or a GitHub Actions macOS runner. Separate cost from Apple's $99. Once
  chosen, put its build-trigger webhook in App Builder → *CI webhook URL*; it
  calls the same `/webhooks/appbuild/ci` status endpoint the Android CI uses, so
  swapping providers later is config, not code.
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
4. Configure the CI secrets; run an Android APK build; verify `/download`.
5. Stand up the macOS build service; set the CI webhook; run an iOS IPA build.
6. Prepare store listings; submit; run Google's closed test.
7. Only after a listing is genuinely live, flip its store-live toggle so the
   badge appears.
