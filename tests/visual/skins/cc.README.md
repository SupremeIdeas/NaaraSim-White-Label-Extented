# Chat Composer Pro — browser checks (Playwright, Chromium)

Run against the dev app (`php artisan serve --port=8099`, MariaDB up, `APP_URL=http://127.0.0.1:8099`).

| Script | What it proves |
|---|---|
| `cc.cjs` | `/support`: auto-grow, emoji panel + insertion, attach sheet (`MODE=light|dark W=430|1280`) |
| `cc-flow.cjs` | `/support` end to end through Livewire: undo ring, real send, evidence file, wrong file type refused, voice note with a fake mic (primer, pause/preview, send) |
| `cc-kit.cjs` | UI kit demo, every feature: hints, snippets, poll, contact, schedule, GIF maker (images -> GIF), saved GIFs |
| `cc-sms.cjs` | Naara Line modal (`/numbers/lines`): field mode mirrors text + photo into `body` / `attachment`, live quote, paid Send button |
| `cc-http.cjs` | `endpoint` mode: multipart + Idempotency-Key, offline queue + reconnect, 500 backoff, 422 returns the message, two composers isolated |

Real-device-only (not testable headless): microphone/camera hardware, geolocation prompt, iOS Safari recording format, background-tab behaviour.
