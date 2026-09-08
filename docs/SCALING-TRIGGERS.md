# Scaling triggers — when to move off shared hosting (BUILD-5 §5)

NaaraSim runs today on **shared cPanel hosting** with a **cron-drained database
queue**. That's deliberate and fine for launch traffic — but it has real,
known ceilings. This doc names the concrete signals that mean "it's time to move
to the VPS + Redis + Horizon path this codebase already supports," so that
decision is made **ahead of time**, not forced by an outage.

The migration path is already half-built: `config/horizon.php` +
`HorizonServiceProvider` exist, and `config/queue.php` switches to Redis simply
by changing `QUEUE_CONNECTION`. Nothing needs rewriting — only promoting.

---

## The two hard ceilings of the current setup

1. **Latency floor (~60s).** Jobs are drained by `schedule:run` firing
   `queue:work --stop-when-empty` once a minute (see `routes/console.php`). So
   every queued job — including a provider order or a payout send — waits up to
   ~60 seconds before it even starts.
2. **Throughput ceiling (~50s/min per drain).** Each drain runs
   `--max-time=50`, so at most ~50 seconds of work clears per minute per worker.
   Sustained inflow above what clears in that window means the `jobs` table
   backlog grows without bound.

Neither is a bug. They are the reason to graduate to Horizon (always-on workers,
sub-second pickup, horizontal scaling, retries/metrics UI).

---

## Move to VPS + Redis + Horizon when ANY of these holds

| Signal | Threshold | How to watch it |
|--------|-----------|-----------------|
| **Queue backlog** | `jobs` table consistently > **500** rows, or oldest job age > **3 min**, across a normal hour | `SELECT COUNT(*), MIN(created_at) FROM jobs;` (add to a monitor) |
| **Order latency complaints** | Customers report eSIM/number delivery routinely taking > **1–2 min** | Support tickets + `order_logs.created_at` vs delivery time |
| **WhatsApp Autopilot volume** | Sustained > **1,000 template sends/day** (each is a queued external call) | `SendWhatsAppTemplateJob` volume |
| **Provider polling pressure** | OTP polling (`PollSmsOtpJob`) + catalogue syncs can't keep their cadence within the 50s window | Backlog age of those job classes specifically |
| **Payment/payout webhooks** | Webhook-triggered jobs (settlement, reconciliation) lag > **1 min** behind receipt | Compare `payment_charges.created_at` to wallet-credit time |
| **Scheduled jobs overrun** | `schedule:run` tasks (esim:sync, giftcards:sync, backups) start overlapping/skipping | Horizon-less: watch for `withoutOverlapping` skips in logs |
| **Concurrent users** | Approaching **10k+ DAU** or any traffic spike that pushes DB-queue writes into lock contention | App metrics |

A single sustained signal is enough — don't wait for several. The backlog and
order-latency rows are the earliest and most reliable.

---

## The migration itself (already supported — no rewrite)

1. Provision a VPS with **Redis** and a supervisor (systemd/supervisord).
2. Point `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`.
3. Run **Horizon** as the worker (`php artisan horizon`, supervised). The
   shared-hosting `queue:work --stop-when-empty` drain in `routes/console.php`
   is already guarded by `if (config('queue.default') === 'database')`, so it
   simply stops running once you switch to Redis — no code change.
4. Keep the one `schedule:run` cron for the time-based tasks (syncs, backups).
5. `viewHorizon` gate is already admin-only.

---

## Note
Revisit this doc whenever the queue backlog or order-latency signals first
appear — not after they cause an incident. Update thresholds here if real
traffic shows they were set too loose or too tight.
