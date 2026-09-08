# Installing NaaraSim on cPanel shared hosting (Namecheap, etc.)

This is the quick path for **shared hosting** (Namecheap Premium, LiteSpeed/cPanel).
It assumes you cannot run Node/npm on the server — so the compiled front-end
assets ship with the product (`public/build/`), and a root `.htaccess` lets the
app run straight from `public_html` with no document-root change.

> If you were getting a **"404 Not Found — The resource requested could not be
> found on this server!"** at `yourdomain.com/install`, that was the web server
> not finding Laravel's `public/` folder. The root `.htaccess` in this package
> fixes it — just make sure that hidden file uploaded (see step 2).

> **Behind Cloudflare?** If this domain proxies through Cloudflare, follow
> [`docs/CLOUDFLARE-SETUP.md`](CLOUDFLARE-SETUP.md) **before taking payments** —
> Cloudflare's bot protection and caching can silently drop payment webhooks
> (payment succeeds on the gateway, never credited in Naara). That doc covers
> webhook-IP allowlisting, excluding `/webhooks/*` from Bot Fight Mode, and
> cache-bypass rules for `/admin/*`, `/api/*`, and `/webhooks/*`.

---

## 1. Create the database (cPanel → MySQL® Databases)
1. Create a **database**, e.g. `cpuser_naarasim`.
2. Create a **database user** + password.
3. **Add the user to the database** with **ALL PRIVILEGES**.
4. Keep the DB name, user, and password handy for step 4. (Host is usually
   `localhost`, port `3306`.)

> **Footgun — cPanel silently truncates long MySQL usernames.** cPanel prefixes
> your username with `cpuser_` and caps the total length. The name you *typed*
> and the name cPanel actually *created* can differ. Always copy the **exact
> username cPanel shows** after you create the user (not the one you intended)
> into step 4 — a mismatch here is the most common "installer can't connect to
> the database" cause.
>
> **Footgun — MySQL DDL is not transactional.** If the installer fails partway
> through migrating, MySQL does **not** roll the half-created tables back. A
> second attempt then fails with "table already exists." Before retrying, fully
> **drop and recreate the database** (cPanel → MySQL Databases → delete, then
> create again) so migrations start from a clean slate.

## 2. Upload the files (cPanel → File Manager)
Upload the **entire project** into your domain's document root — for the primary
domain that is **`public_html`** (for an addon/subdomain, its own docroot).

- In File Manager, enable **Settings → Show Hidden Files (dotfiles)** so the
  **`.htaccess`** and **`.env.example`** files are visible and get uploaded.
- The layout inside `public_html` should look like:
  `app/  bootstrap/  config/  public/  routes/  storage/  vendor/  .htaccess  artisan …`
- **`vendor/` must be present.** If you cloned from Git (no `vendor/`), either
  upload a release ZIP that includes it, or run `composer install --no-dev` in
  cPanel Terminal.

> **More secure alternative:** upload the project ABOVE `public_html` and point
> the domain's document root at the project's `public/` folder
> (cPanel → Domains → document root → append `/public`). Then the root
> `.htaccess` is unused. Either approach works.

## 3. Set permissions (cPanel → Terminal)
```bash
cd ~/public_html        # or wherever you uploaded
bash set-permissions.sh
```
This makes `storage/` and `bootstrap/cache/` writable (775) and everything else
a safe 755/644. No `777` anywhere.

If you have no Terminal access, in File Manager set **`storage`** and
**`bootstrap/cache`** (and everything inside them) to **755**, or **775** if PHP
reports it still can't write.

## 4. Run the web installer
Visit **`https://yourdomain.com/install`**. You'll get:
1. **Welcome**
2. **Server requirements** — green check that PHP ≥ 8.2, the required
   extensions, and that `storage/` + `bootstrap/cache/` are writable.
   (If a row is red, fix it and reload — usually a permission or PHP-version
   setting in **cPanel → Select PHP Version**.)
3. **Setup** — enter:
   - **App name / URL** (URL with **no trailing slash**, e.g. `https://naarasim.com`).
   - **Hosting type: Shared** (this puts cache/session/queue in the database — no
     Redis needed on shared hosting).
   - **Database**: the name / user / password from step 1 (host `localhost`,
     port `3306`).
4. The installer writes `.env`, runs migrations, seeds a default admin, links
   storage, and caches config. The **Done** screen shows your admin login.

## 5. Add the cron job (cPanel → Cron Jobs) — required
Scheduled tasks *and* the queue (money/API jobs) run from one cron line. Add a
cron that runs **every minute**:
```
* * * * * cd ~/public_html && php artisan schedule:run >> /dev/null 2>&1
```
(Use the full path to the project if it isn't `public_html`.) The admin **Setup
Wizard** also shows this line with a copy button.

> **NCI (smart routing) on a fresh install.** The Naara Core Intelligence layer
> learns from real transactions, so on a brand-new install its tables
> (`provider_registry`, `provider_outcomes`) are empty and it simply has no
> opinion yet — routing falls back to the static provider order until real orders
> accumulate. This is expected and safe: no manual seeding is required. Once the
> cron above is running, two NCI jobs keep it healthy automatically —
> `nci:recompute` (nightly, refreshes scores) and `nci:prune-outcomes` (weekly,
> trims outcome rows past the 90-day retention window so the table never grows
> unbounded). You can watch both under **Admin → System Health → NCI learning
> health**, and turn the whole intelligence layer off (routing reverts to plain
> latency/success-rate, circuit breakers stay active) from **Admin → Operations
> (NCI) → Routing Console** if you ever need to.

## 6. Log in and finish setup
Go to **`https://yourdomain.com/adminmaster`**, log in with the credentials from
the Done screen, **change the password immediately**, then use the in-panel setup
to paste your provider/payment/eSIM/number keys. Nothing goes live until you add
its key.

---

## Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| Server **404** at `/install` | Root `.htaccess` missing, or files aren't in the docroot | Ensure hidden files uploaded (step 2); confirm `.htaccess` sits next to `artisan`. Or set docroot to `/public`. |
| **500** / "Vite manifest not found" | `public/build/` didn't upload | Re-upload the `public/build` folder — it ships in the package (don't delete it). |
| "**The stream or file storage/logs… could not be opened**" | `storage/` not writable | Re-run `bash set-permissions.sh` (or chmod `storage` + `bootstrap/cache` to 775). |
| Installer **can't write .env** | Project root not writable during install | Temporarily set the project folder to 755 (owner-write); the installer writes `.env` next to `artisan`. |
| Blank page after install | Stale cache | `php artisan optimize:clear` in Terminal, then reload. |
| **419 Page Expired** on forms | App URL / HTTPS mismatch | Make sure `APP_URL` in `.env` exactly matches the address you visit (https). |

## Re-running the installer
The installer locks itself after finishing (a `storage/installed` file). To
re-install (e.g. wrong DB), delete that file and visit `/install` again.

## After a code update
Shared hosting can't rebuild assets. If you change front-end code, run
`npm run build` locally and upload the refreshed **`public/build`** folder, then
`php artisan optimize:clear` on the server.

---

## Building the installable package from source

You never have to hand-assemble a package again. From a **clean checkout** of the
repo (run it in a throwaway clone, or `git stash` local changes first):

```bash
bash scripts/package-release.sh                 # → dist/naarasim-cpanel-<timestamp>.zip
bash scripts/package-release.sh my-package-name # optional custom name
```

The script, in order:
1. Runs `php bin/check-install-integrity.php` (fails fast if an install-critical
   file drifted out — see below).
2. `composer install --no-dev --optimize-autoloader` (ships `vendor/`).
3. `npm ci && npm run build` (ships compiled `public/build/`).
4. Recreates the writable runtime directories so the ZIP is bulletproof even if a
   later re-zip strips dotfiles.
5. Zips a clean tree (no `.git`, `node_modules`, tests, or `.env`) into `dist/`.

The resulting ZIP needs no Composer or npm on the server — upload it per the
steps above.

## Why these files must never be deleted (install-critical)

Four files silently drifted out of the repo once and broke fresh installs until a
byte-level diff against a known-working package found them. They are now guarded
by `bin/check-install-integrity.php` (a CI gate — see
`.github/workflows/tests.yml`) and by the packaging script. Do **not** delete or
`.gitignore` any of them:

| File / path | Why it's install-critical |
| --- | --- |
| `config/view.php` | Without it Laravel's default compiled-view path is `realpath(storage/framework/views)`, which is `false` before that dir exists on a fresh install → Blade fails with "Please provide a valid cache path". This file uses `storage_path(...)` (no `realpath`) so the path resolves regardless. |
| `resources/views/components/layouts/install.blade.php` | The DB-free layout every install step renders through. Without it the wizard throws "component not found"; using the full app layout instead would paint the wizard with splash/preloader/PWA chrome and query the not-yet-migrated `settings` table. |
| `database/migrations/*_create_partner_earnings_table.php` timestamp | Must sort **after** `create_partners_table` (it has a foreign key into it). Kept one second later (`130956` vs `130955`) so `migrate` never hits a foreign-key-order failure. The integrity check fails on any new migration timestamp collision. |
| The `.gitignore` placeholders in `bootstrap/cache` + `storage/framework/*` + `storage/logs` | Keep those writable runtime directories present through a `git clone`/export. (`public/storage` is intentionally **not** committed — it's the symlink created by `php artisan storage:link` at install.) |
