# NaaraSim — Security Hardening Matrix (Blueprint Section 30)

Every OWASP mistake class and attack vector, mapped to the control that defends
against it in this codebase. Keep this current as modules change.

| Attack class | Control | Where |
|---|---|---|
| **Injection (SQL)** | Eloquent / query builder (parameterised); no string-built SQL in app code | throughout |
| **XSS (stored/reflected)** | Blade auto-escaping; `Content-Security-Policy` with **no external script origins**; SVG uploads sanitised | `SecurityHeaders`, `config/security.php`, `MediaStorage::sanitizeSvg` |
| **Clickjacking** | `X-Frame-Options: SAMEORIGIN` + CSP `frame-ancestors 'self'` | `SecurityHeaders` |
| **MIME sniffing** | `X-Content-Type-Options: nosniff` | `SecurityHeaders` |
| **Protocol downgrade / MITM** | HSTS over HTTPS; secure + HttpOnly + SameSite session cookies; session **encrypted at rest** | `SecurityHeaders`, `config/session.php` |
| **SSRF** | `SsrfGuard` rejects non-http(s) + private/reserved/loopback/link-local (incl. cloud-metadata `169.254.x`); optional host allow-list; `PublicUrl` rule for user URLs | `Support\Security\SsrfGuard`, `Rules\PublicUrl` |
| **Mass assignment** | `$fillable` allow-lists on every model; Livewire uses typed props + `validate()`, never `request()->all()` for business logic | models, Livewire components |
| **Broken access control** | Spatie roles + `EnsureAdmin` (env path, IP allow-list, 2FA, plain 404); per-route `role:`/`permission:` gates; `super_admin`-only destructive actions | `EnsureAdmin`, `routes/web.php`, services |
| **Auth brute-force** | Rate-limited login / two-factor / passkey limiters; order + admin + api throttles | `FortifyServiceProvider`, `AppServiceProvider` |
| **CSRF** | Laravel CSRF on all web POSTs; webhooks exempt but **HMAC/shared-secret verified** first | `bootstrap/app.php`, webhook controllers |
| **Sensitive data exposure** | Cost/profit columns `$hidden`; data export filters third-party PII; secrets only in `.env` via `config()` | models, `UserDataExporter` |
| **Insecure deserialization / secret writes** | Maintenance loop `SecretGuard` blocks any diff touching `.env`/keys or writing secret-looking values | `Support\Maintenance\SecretGuard` |
| **Vulnerable dependencies** | `composer audit` gate in CI — fails on any new advisory (documented allow-list only) | `bin/security-audit.php`, `.github/workflows/tests.yml` |
| **Rate/abuse of money actions** | Order actions 10/min; money moves are idempotent + atomic | Checkout/GetNumber, `WalletService` |
| **Audit / non-repudiation** | Immutable `audit_logs` for every admin/staff/lifecycle/maintenance action | `Support\Auditor` |

## Admin-toggleable controls (no coding needed)

The two web-security headers most likely to clash with a specific self-hosted
environment are **on by default** but can be switched off from the admin panel
(**Security → Site protection**, super-admin only, applies instantly):

- **Content protection (CSP)** — turn off only if a trusted embed/widget won't load.
- **Force secure connection (HSTS)** — turn off only if the server isn't on HTTPS yet.

Everything else (SSRF guard, session encryption, rate limits, CSRF, role gates) is
always on — those have no legitimate reason to be disabled and are not exposed as
toggles, to avoid a footgun for a non-technical operator.

## Optional developer tooling (does NOT run on the live platform)

`Larastan` static analysis is available for developers as a local check
(`composer require --dev larastan/larastan && vendor/bin/phpstan analyse`, config
in `phpstan.neon`). It is a code-quality aid used while editing source — it is
**never installed on, nor run by, the live platform**, and is deliberately not a
CI gate.

## Framework version — Laravel 12 (security-supported)

The platform runs on **Laravel 12** (upgraded from Laravel 11 on 2026-07-14,
framework `v12.63`). This resolved the three `laravel/framework` advisories that
Laravel 11 carried after its 2026-03-12 security-EOL (signed-URL path confusion;
CRLF in the default email rule). `composer audit` now reports a clean tree, and
the `bin/security-audit.php` allow-list is empty — so any **new** advisory
breaks the build until it is fixed or consciously accepted.
