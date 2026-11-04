# Cron and queue for this install (cPanel, VPS, Cloudways)

One cron line, every minute, runs everything: the licence check-in, emails, backups and the queue.

```
* * * * * cd /full/path/to/app && /path/to/php artisan schedule:run >> /dev/null 2>&1
```

Use the PHP path your host shows (PHP 8.2 or newer).

* **cPanel / shared hosting:** `QUEUE_CONNECTION=database`. No worker needed: the scheduler drains the queue every minute.
* **VPS:** `QUEUE_CONNECTION=redis` and Horizon under Supervisor (`php artisan horizon`), plus the same cron line.
* **Cloudways:** add the cron line in Application > Cron Job Management. Use `QUEUE_CONNECTION=database` for the simple setup, or Redis + Horizon under Supervisor for the faster one. Make sure `/api/*` is not cached or challenged: your licence provider reaches `POST /api/licence-signal` to tell your site its licence changed.

What the licence does on its own: your site checks in with the licensing platform **every hour**, and also immediately when the platform says your licence changed (for example when you pay your balance). If the nudge cannot reach you, the hourly check-in catches up.

## Prove it works

```
php artisan whitelabel:doctor     # licence standing, platform public key, last check-in, queue
php artisan whitelabel:check-in   # force one check-in now
```

Put `NAARA_LICENCE_PUBLIC_KEY=<the key your provider gave you>` in `.env`. Without it this install cannot verify licence answers and the doctor reports a failure.
