# Tenant Isolation — mandatory per-install separation

> From `NAARASIM-URGENT-TENANT-ISOLATION-AND-PROVIDER-VERIFICATION-BLUEPRINT.md`.
> This is the **repo-side** hardening + the operator runbook. The live-server
> audit and remediation (comparing real `.env` files, provisioning a fresh
> database) can only be done by an operator with access to the servers — this
> codebase cannot see another install. **Nothing in this repo touches a live
> server; that part is yours to run, using the steps below.**

---

## Why this exists

Running master on a primary domain and a White Label on a subdomain at the same
time, an operator saw a user account appear on both installs (with role
confusion) and a theme change on master appear on the White Label too. With no
real-time broadcasting anywhere in this codebase, the overwhelmingly likely
cause is that **the two installs share one physical database (and/or `APP_KEY`,
and/or Redis)** — almost always a copy-paste mistake during a quick test deploy.
Two apps reading/writing the same `users` table and the same
`platform.active_theme` setting row produce exactly those symptoms.

This is a **data-isolation** failure, not a feature bug. Before any White Label
instance holds real merchant data, each install MUST be fully isolated.

---

## The one rule (non-negotiable)

**Every install of NaaraSim — master and every white-label — gets its OWN
dedicated database, its OWN unique `APP_KEY`, and session/cache scoping that
cannot overlap another install. Never share or copy any of these between two
installs.**

---

## Operator runbook (live servers — only you can do this)

### 1. Compare the two live `.env` files (do NOT use `.env.example`)
On the real master server and the real White Label subdomain server, compare, in
this order (report match/mismatch only — never paste the actual secret values):

| Setting | What a shared value means |
|---|---|
| `DB_DATABASE`, `DB_HOST`, `DB_USERNAME` | **If these match across both installs, the databases are shared — this is the bug.** Stop and remediate (step 2). |
| `APP_KEY` | If this matches too, session cookies are mutually decryptable between the apps — fix regardless of the DB finding. |
| `SESSION_DOMAIN` | Must be empty or the install's **exact host** — never a leading-dot parent (`.example.com`) that both a subdomain and the root match. |
| `CACHE_*` / `REDIS_*` (host + `REDIS_DB` + `CACHE_PREFIX`) | If both installs share one Redis instance **and** the same DB index **and** no distinct cache prefix, cached settings leak between them. |

If the database, cache and session are all already distinct, **do not assume
the bug is solved** — log in as a distinct admin on each install in two fully
separate (incognito) browser sessions and confirm neither install's user list
or active theme reflects the other's. If it doesn't, the original report was a
browser-session artifact, not contaminated data.

### 2. Remediate (only the White Label subdomain — never touch master's data)
1. Provision a **completely fresh, dedicated database** (new DB, new DB user,
   new credentials) for the White Label install. Run a clean
   `php artisan migrate --seed` against it. Do **not** try to "split" the
   contaminated shared DB — start the White Label from a known-clean state.
2. Generate a **fresh, unique `APP_KEY`** for the White Label (`php artisan
   key:generate`) if it currently matches master's.
3. Set `SESSION_DOMAIN` to the exact host on both installs (or leave empty).
4. If Redis is shared, give the White Label a distinct `REDIS_DB` / distinct
   `CACHE_PREFIX` so cached values can never be read across installs.

### 3. Verify
- Run the self-check on each install: `php artisan tenant:verify-isolation`
  (add `--strict` to treat warnings as failures in a deploy gate).
- Reproduce the original test in two separate browser sessions: change the theme
  on master, reload the White Label, confirm it is unaffected; confirm neither
  install's user list shows the other's accounts.

---

## Repo-side hardening shipped here

- **`php artisan tenant:verify-isolation`** — a self-check every deployed
  install can run against itself. It hard-fails on a missing/placeholder
  `APP_KEY`; warns on a leading-dot `SESSION_DOMAIN` and on a Redis cache with no
  `CACHE_PREFIX`; and prints the DB connection + database **name** (never
  credentials) so an operator can eyeball that two installs point at genuinely
  different databases. Wire it into your deploy checklist for every new install.

### Added since (Tier 4 #10 Phase A2)
Production logs on both `rehav.online` (master) and `farm.rehav.online`
(White Label) showed recurring "The MAC is invalid" errors, specifically on
`white-label-registry.blade.php` — very likely tied to this exact issue.
Two things came out of re-checking it:

- **The live-server `APP_KEY`/DB/Redis comparison above is still an operator
  task** — this repo cannot see another install's `.env`, so run the runbook
  steps above on the real servers to confirm they're distinct; that part
  hasn't changed.
- **A genuine code gap was found and fixed regardless of root cause**: a
  session row encrypted under a stale/rotated `APP_KEY` (or a corrupted
  payload) throws `DecryptException` from deep inside the encrypted session
  store's read, and that specific failure was NOT caught anywhere — it
  surfaced as a hard "MAC is invalid" error page. (Laravel's own
  `DecryptCookies` middleware already tolerates a bad session-ID *cookie* by
  treating it as "no cookie"; it does not protect the session *payload*
  stored server-side.) `bootstrap/app.php`'s exception handler now catches
  `\Illuminate\Contracts\Encryption\DecryptException` platform-wide, drops
  the poisoned session cookie, and sends the visitor to a fresh session
  instead of a broken page — on both master and every White Label install,
  with zero extra configuration. This is resilience, not a substitute for
  fixing an actual shared-`APP_KEY` misconfiguration if the operator check
  above finds one.

### Still to wire (flagged, not yet built)
The definitive **cross-instance** collision check — master recording each
issued instance's intended database name / `APP_KEY` hash at license-issuance
time, and the instance verifying itself against that centrally — is a networked
step that belongs with the license-consumer layer (the white-label
`WhiteLabelUpdateClient` / activation path). It is intentionally **not** built
in this pass to avoid guessing at that contract; it is the natural next
increment once the license boundary work settles. Until then, the local
self-check above plus the operator runbook are the guardrails.
