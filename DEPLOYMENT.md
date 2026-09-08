# NaaraSim — Deployment Guide

NaaraSim installs like a CodeCanyon product: upload, open the site, and the web
installer runs. It is **production-ready on two very different hosts** and the
web installer configures itself for whichever you pick:

| | **Shared / cPanel** | **VPS / Cloud** |
|---|---|---|
| Cache · Session · Queue | **database** (no Redis needed) | **redis** |
| Queue worker | one cron drains it each minute | Horizon daemon (Supervisor) |
| Best for | launching, small–medium traffic | scaling to 1M+ users |
| Migration | → move to VPS any time, no data loss | — |

You can **start on shared cPanel and migrate to a VPS later** — the codebase is
identical; only the three drivers and the worker change (see §4). Provider keys
left blank show **Coming Soon** and flip to **Active** the moment a real key is
saved, so you can launch one product at a time. (Blueprint Sections 22 & 17.4.)

---

## 1. Web installer (both hosts)

1. Upload the build and point the domain's document root at **`public/`**.
2. Make `storage/` and `bootstrap/cache/` writable (`755`/`775`).
3. Visit the site — you'll be redirected to **`/install`**:
   - **Requirements** — PHP 8.2+, required extensions, writable paths. (Redis is
     listed as *recommended*, not required — shared hosting passes without it.)
   - **Environment** — app name, app URL (no trailing slash), and **Hosting
     Type** → *Shared / cPanel* or *VPS / Cloud*. This choice writes the correct
     `CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION` for you.
   - **Database** — host / port / name / user / password.
   - **Install** — writes `.env`, generates `APP_KEY`, runs `migrate --seed`
     (creating the `cache`, `jobs`, `failed_jobs`, and `sessions` tables among
     the rest), caches config/routes/views/icons, links storage, writes the
     `storage/installed` lock, and shows the default super-admin login.
4. Sign in at `/adminmaster`, change the admin password, enrol 2FA, then open
   **Admin → API keys** and paste your provider/gateway keys.

To re-run the installer, delete `storage/installed`.

---

## 2. Shared / cPanel — production setup

Everything runs on the database; **no Redis and no long-running process** is
required. This is the profile most Namecheap / cPanel plans support.

### 2.1 Files & document root
- Upload to e.g. `/home/USER/naarasim`.
- In cPanel → **Domains**, set the domain's document root to
  `/home/USER/naarasim/public` (or use **Setup Node/PHP App** / **MultiPHP** and
  set the app root, then point the domain at `public/`).
- PHP version: **8.2 or 8.3** (cPanel → **Select PHP Version**). Enable the
  extensions: `pdo`, `mbstring`, `openssl`, `curl`, `gd`, `zip`, `bcmath`,
  `intl`, `fileinfo`.

### 2.2 Database
- cPanel → **MySQL® Databases**: create a database + user, grant **ALL
  PRIVILEGES**. Use those in the installer's Database step.

### 2.3 Cron — the single entry that runs everything
Add **one** cron job (cPanel → **Cron Jobs**). It drives the scheduler, and the
scheduler in turn drains the queue every minute (money jobs included):

```
* * * * * cd /home/USER/naarasim && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

- Use the **full PHP binary path** cPanel shows for your selected version (often
  `/usr/local/bin/ea-php82` or similar) — not a bare `php`.
- That is the **only** cron entry you need. You do **not** add a separate
  `queue:work` cron: when the queue driver is `database`, `routes/console.php`
  schedules a short-lived `queue:work --stop-when-empty` drain each minute
  automatically, so jobs are processed within ~60s.

### 2.4 What the one cron does for you
Triggered by `schedule:run` each minute:
- **Queue drain** — `queue:work --stop-when-empty` (only when queue = database).
- **`providers:health-check`** — every 15 min (low-balance alerts).
- **`esim:sync`** — daily 03:00 (refresh catalogues + reprice).
- **`backup:clean` / `backup:run --only-db`** — nightly DB backup to Wasabi.

### 2.5 Storage
- Set the `WASABI_*` keys so large files (eSIM QR, exports, backups) never touch
  the shared-disk quota. `FILESYSTEM_DISK=wasabi` is the default.
- If mysqldump is blocked on your plan, the backup falls back to
  `ifsnop/mysqldump-php` automatically (needs `SELECT` + `SHOW VIEW`).

### 2.6 Going live
- Keep `APP_ENV=production`, `APP_DEBUG=false` (the installer sets both).
- Force HTTPS with the free cPanel **AutoSSL** certificate.
- Re-run `php artisan config:cache route:cache view:cache` after any `.env`
  change (or via cPanel Terminal if available).

---

## 3. VPS / Cloud — production setup

For scale: Nginx + PHP-FPM 8.2/8.3, MySQL 8, Redis, Supervisor running Horizon,
optional Cloudflare in front. Pick **VPS / Cloud** in the installer so the three
drivers are written as `redis`.

### 3.1 Services
- **Nginx** vhost → root `…/naarasim/public`, standard Laravel `try_files`.
- **PHP-FPM 8.2+** with the same extensions as §2.1 plus **`redis`**.
- **Redis** running locally (or managed).

### 3.2 Cron — same single entry
```
* * * * * cd /var/www/naarasim && php artisan schedule:run >> /dev/null 2>&1
```
On a VPS the queue is Redis + Horizon, so the database queue-drain is **not**
scheduled — the scheduler only runs health-check / sync / backup.

### 3.3 Queue worker — Horizon via Supervisor
Create `/etc/supervisor/conf.d/naarasim-horizon.conf`:

```ini
[program:naarasim-horizon]
process_name=%(program_name)s
command=php /var/www/naarasim/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/naarasim/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start naarasim-horizon
```

Deploys should run `php artisan horizon:terminate` so Supervisor restarts
workers on the new code.

---

## 4. Migrating shared cPanel → VPS (no data loss)

1. Provision the VPS (§3), install Redis + Supervisor.
2. Copy the app files and export/import the MySQL database (or point the new
   `.env` at the same managed DB).
3. In `.env`, switch the three drivers to Redis:
   ```
   CACHE_STORE=redis
   SESSION_DRIVER=redis
   QUEUE_CONNECTION=redis
   ```
   (Set `REDIS_HOST` / `REDIS_PASSWORD` / `REDIS_PORT` too.)
4. Start Horizon under Supervisor (§3.3). The database queue-drain in the
   scheduler stops running by itself once `QUEUE_CONNECTION` is no longer
   `database` — nothing else to remove.
5. `php artisan config:cache route:cache view:cache icons:cache`.
6. Keep the same single `schedule:run` cron.

Encrypted admin-managed API keys live in the `settings` table, so they migrate
with the database — no re-entry.

---

## 5. API keys — managed entirely in the admin panel

Every provider, payment-gateway and integration credential is set in **Admin →
API keys** (super-admin only), **not** by hand-editing `.env`:

- Keys are **encrypted at rest** and never shown back to the browser (the form
  displays a masked preview; a blank field keeps the existing key).
- A saved key **overrides** the matching `.env` value and takes effect
  immediately — no redeploy — flipping that provider from *Coming Soon* to
  *Active*.
- Covers eSIM (eSIM Go, Airalo, Quibity), numbers (Getatext, 5sim, SMS-Activate,
  Twilio, Telnyx), payments (Paystack, Flutterwave, Stripe) and integrations
  (Anthropic, GitHub maintenance token).

`.env` remains a valid fallback for any key you'd rather set at the file level.

### 5.1 Keeping `.env.example` in sync (contributors)

When you wire a new provider into `config/services.php`, add its key to **one**
of the two operator-facing surfaces so it isn't a silent gap: a blank slot in
`.env.example`, or a field in `App\Support\ProviderKeys::schema()` (the Admin →
API keys UI). Before opening a PR, run:

```
php artisan env:check-example
```

It flags any credential read as `env('KEY')` (no default) in `config/services.php`
that appears on neither surface. The same rule runs in CI as
`tests/Feature/EnvExampleSyncTest.php`, so a forgotten key fails the build
instead of shipping as a dead provider.

---

## 6. CI/CD (`.github/workflows/deploy.yml`)

On push to `main`: `composer install --no-dev -o` → `npm ci && npm run build` →
PHPUnit (fails the build on any failure) → SSH deploy. The deploy step runs only
when these repo secrets are set (otherwise the build still passes):
`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_PATH`.

The deploy runs: `artisan down` → `git pull` → install → build →
`migrate --force` → `config:cache` + `route:cache` + `view:cache` +
`icons:cache` → `horizon:terminate` → `artisan up`.

---

## 7. Scaling to millions

Queues absorb every provider call, so spikes never block requests; Horizon
scales workers per queue. Catalogue/price caches keep provider APIs off the hot
path. Stateless app nodes + shared Redis/DB = add nodes horizontally; pre-signed
Wasabi/CDN URLs offload all file delivery.
