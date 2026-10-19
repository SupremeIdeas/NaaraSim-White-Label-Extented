# NaaraSim

**NaaraSim** — a Pan-African travel-connectivity SaaS that sells **eSIM data
plans** for 190+ countries **and** **virtual / verification phone numbers** in one
app and one wallet. Built on the **Laravel 12 TALL stack** (Livewire 3, Alpine,
Tailwind) by **Supreme Ideas Agency**, Onitsha, Nigeria.

> *Stay Connected. No Borders. No Swaps.*

It ships like a CodeCanyon product — a web installer, admin-managed API keys, and
deploys on both **shared cPanel hosting** and a **VPS/dedicated server**.

---

## Server requirements

| | Minimum |
| --- | --- |
| **PHP** | **8.2+** (8.3 recommended; 8.2/8.3/8.4 supported) |
| PHP extensions | `pdo`, `mbstring`, `openssl`, `curl`, `gd`, `zip`, `bcmath`, `fileinfo`, `intl` |
| **Database** | MySQL 8.0+ / MariaDB 10.6+ (utf8mb4, strict) |
| Composer | 2.x |
| Node.js | 18+ (asset build only) |
| Web server | Apache or Nginx, HTTPS recommended |
| Redis | optional (recommended on VPS — queue/cache/session + Horizon) |
| Cron | **one** entry, every minute (see below) |
| Storage | `storage/` and `bootstrap/cache/` writable; media → Wasabi S3 when configured |

## Install

Open **`https://your-domain.com/install`** and follow the wizard
(**Welcome → Requirements → Database → Done**). It detects cPanel vs VPS and
sets the right drivers.

**One required cron entry** (drives the scheduler, and on cPanel also drains the
payment/order queue):

```
* * * * * cd /full/path/to/naarasim && php artisan schedule:run >> /dev/null 2>&1
```

The exact command for your server (with the real path + a Copy button) appears on
the installer's **Done** screen and under **Admin → Maintenance → Cron & scheduler**.

**Default admin login** (the installer's Done screen shows it too):
`adminmaster1234@gmail.com` / `123456789@AdminMaster`. **Change this password
immediately after first login** — the Done screen and `DEPLOYMENT.md` both say
so.

📖 **Full guide (cPanel + VPS, step by step):** [`docs/INSTALLATION.md`](docs/INSTALLATION.md)
· **Go-live checklist:** [`docs/REMAINING-TO-FINALIZE.md`](docs/REMAINING-TO-FINALIZE.md)

---

## What's inside

- **eSIM** via a profit-aware failover router (eSIM Go → Airalo → Quibity)
- **Numbers** via capability routing (Getatext, 5sim, SMS-Activate, Twilio, Telnyx)
- **Atomic wallet** (dual currency NGN/USD), **PricingEngine + MarginGuard** so a
  sale can never drop below cost + minimum profit
- **Plan Price with Claude** — AI proposes optimal retail, admin approves, the
  floor is always enforced
- **NaaraCredits** loyalty + rewards, coupons, promo banners (image **and video**)
- Full **admin panel** (`/adminmaster`), staff & scoped roles, GDPR account
  lifecycle, encrypted DB backups, Claude maintenance loop

## Tech stack

Laravel 12 (PHP 8.2+) · Livewire 3 · Alpine 3 · Tailwind 3.4 (dark mode) ·
MySQL 8 · Redis + Horizon · Sanctum + Fortify (2FA) · Spatie Permission ·
Wasabi S3.
