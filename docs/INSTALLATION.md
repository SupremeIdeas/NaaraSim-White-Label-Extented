# NaaraSim — Installation Guide

NaaraSim installs like a CodeCanyon product: upload, open your domain, and a
web wizard walks you through it (**Welcome → Server Requirements → Database →
Done**). It runs on both **shared cPanel hosting** and a **VPS / dedicated
server** — the wizard asks which one you're on and configures the right drivers
automatically.

> **On shared cPanel (Namecheap etc.)?** Follow the step-by-step
> **[cPanel install guide](CPANEL-INSTALL.md)** — it covers the exact upload
> location, the root `.htaccess` that routes `public_html` into Laravel's
> `public/` folder (the fix for a **404 at `/install`**), permissions, and the
> required cron line. The compiled front-end assets (`public/build/`) ship in the
> package, so no Node/npm is needed on the server.

---

## 1. Server requirements

| Requirement | Minimum | Notes |
| --- | --- | --- |
| **PHP** | **8.2+** (8.3 recommended) | 8.2, 8.3, 8.4 all supported |
| PHP extensions | `pdo`, `mbstring`, `openssl`, `curl`, `gd`, `zip`, `bcmath`, `fileinfo`, `intl` | the wizard checks these live |
| **MySQL** | 8.0+ (or MariaDB 10.6+) | utf8mb4, strict mode |
| **Composer** | 2.x | to install PHP dependencies |
| **Node.js** | 18+ (build only) | only needed to build front-end assets; not required at runtime |
| Web server | Apache or Nginx | HTTPS strongly recommended |
| Writable | `storage/`, `bootstrap/cache/` | the wizard verifies |
| **Redis** | optional (recommended on VPS) | powers queue/cache/session + Horizon on VPS |
| Disk | ~500 MB app + room for logs/cache | media goes to Wasabi S3 when configured |
| Cron | one entry, every minute | **required** — see §4 |

> The **Server Requirements** step of the installer checks all of the above and
> shows a green/red list before you continue.

---

## 2. Shared / cPanel install (no terminal needed)

1. **Create the database** in cPanel → *MySQL Databases*: a database, a user, and
   add the user to the database with **All Privileges**. Note the name/user/password.
2. **Upload the app**: put the release ZIP in your domain's document root
   (usually `public_html`) and *Extract*. NaaraSim ships with a front controller,
   so point your domain's document root at the app's `public/` folder (in cPanel →
   *Domains* → set the document root to `.../public`). If you can't change the
   docroot, use the included `.htaccess` redirect.
3. **Run the wizard**: open `https://your-domain.com/install`.
   - **Welcome** → Continue.
   - **Server Requirements** → all required rows should be green.
   - **Database** → choose **Shared / cPanel**, enter your DB name/user/password
     (host is usually `localhost`), your site URL and app name → **Install**.
   - The installer writes `.env`, migrates the database, seeds defaults, links
     storage, and caches config/routes/views.
4. **Add the cron job** (see §4) — copy the command shown on the **Done** screen.
5. **Sign in** with the default admin shown on the Done screen and **change the
   password immediately** (Account → Security).

On cPanel there is **no Redis and no long-running worker**: cache, session and
queue all use the database, and the single `schedule:run` cron both runs the
scheduler and **drains the payment/order queue every minute**. That's why the
cron in §4 is mandatory — money jobs sit in the `jobs` table until it runs.

---

## 3. VPS / dedicated install

```bash
# 1. Get the code
git clone <your-repo> naarasim && cd naarasim

# 2. Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Environment
cp .env.example .env
php artisan key:generate
#   set APP_URL, DB_*, REDIS_*, and (recommended) Wasabi + mail creds in .env
#   set QUEUE_CONNECTION=redis, CACHE_STORE=redis, SESSION_DRIVER=redis

# 4. Database
php artisan migrate --force
php artisan db:seed --force        # roles, pricing defaults, default admin, banners

# 5. Storage + caches
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 6. Point Nginx/Apache document root at  .../public
```

You can also just open `/install` on a VPS and choose the **VPS** profile — it
does steps 3–5 for you.

**Run the queue worker as a daemon** (VPS uses Redis + Horizon):

```ini
# /etc/supervisor/conf.d/naarasim-horizon.conf
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

Then add the cron in §4.

---

## 4. The cron job (required on every host)

NaaraSim needs **one** cron entry, running **every minute**. It drives the
scheduler — automatic eSIM catalogue + price sync (daily), provider wallet
health checks (15-min), and nightly encrypted DB backups — **and on cPanel it
also drains the queue**.

```
* * * * * cd /full/path/to/naarasim && php artisan schedule:run >> /dev/null 2>&1
```

- **cPanel**: *Advanced → Cron Jobs* → Common Settings **Once Per Minute
  (`* * * * *`)** → paste the command part
  (`cd /path && php artisan schedule:run >> /dev/null 2>&1`). If your host lists
  several PHP versions, replace `php` with the full binary path cPanel shows
  (e.g. `/usr/local/bin/ea-php82`).
- **VPS**: `crontab -e` and add the line above.

The exact command for **your** server (with the real path) is shown on the
installer's **Done** screen and any time under **Admin → Maintenance → Cron &
scheduler**, with a one-click **Copy** button.

---

## 5. After install — go live

Do these from the admin panel (nothing needs code):

1. **Admin → API keys**: paste provider, payment, Anthropic, Turnstile, mail and
   social keys (use **sandbox** first). Blank keys simply show "Coming soon".
2. **Run a catalogue sync** so real plans/prices replace the demo data, then use
   **Plan Price with Claude** to set retail.
3. **Wasabi S3** (recommended): set the keys in `.env`/API keys so uploads and
   backups go to object storage instead of local disk.
4. **Verify a sandbox top-up** end-to-end (the wallet is credited by the webhook).
5. Review the full **`docs/REMAINING-TO-FINALIZE.md`** go-live checklist.

---

## 6. Troubleshooting

- **Background jobs / top-ups not completing** → the cron isn't running. Re-check §4.
- **"No application encryption key"** → run `php artisan key:generate` (VPS) or
  re-run the wizard (it sets `APP_KEY`).
- **Uploads/logos 404** → run `php artisan storage:link`, or configure Wasabi.
- **Blank page / 500 after deploy** → `php artisan config:clear && php artisan
  optimize`, and confirm `storage/` + `bootstrap/cache/` are writable.
- **Re-run the installer** → delete `storage/installed` (development only).
