# Chat Composer Pro — Phase 0 audit (2026-10-03)

Prototype: `chat-composer-pro….html` (owner upload). Spec: `NAARASIM-BATCH1-CHAT-COMPOSER-PRO.md`.

## Stack facts (verified)
- Laravel 12 + Blade + Livewire 3 + Alpine 3, **Vite** (`laravel-vite-plugin`), Tailwind 3.4 (`darkMode: 'class'`). → ES module + Custom Element path.
- Transport on every composer today is a **Livewire action** (`$wire.upload()` + a component method). No REST composer endpoint, no broadcasting/Echo yet.
- Auth: session cookie (same-origin). CSP: `connect-src 'self'`.
- One shared emoji dataset (`resources/js/data/emojis.json`, unicode-emoji-json) and one recents key (`naara-recent-emojis`) already exist.

## Every text-entry surface found
| Surface | File | What it is | Customer / staff | Verdict |
|---|---|---|---|---|
| NaaraCare / Help Center chat | `livewire/support-chat.blade.php` (+ `SupportChat.php`, `voiceRecorder` Alpine) | A real chat composer: text, emoji, one evidence file (image/PDF), voice note | Customer-facing (a human agent may be on the other end) | **Replace** (chat mode) |
| Naara Line "New message" | `livewire/send-message.blade.php` (+ `SendMessage.php`) | SMS/MMS compose inside a paid modal: body (918 chars), optional photo or voice note (MMS), live cost quote, wallet-charging Send button | Customer-facing, money action | **Replace the body + attachments with the composer in `field` mode**; keep the paid Send button, quote, errors and server rules untouched (a money action must not get an undo-timer / background retry) |
| Contact form | `livewire/contact-form.blade.php` | One-shot form (name/email/message) | Public | **Not a conversation — excluded** |
| Staff support queue / agent | `admin/support-queue.blade.php`, `admin/support-agent.blade.php` | Queue list; agent persona/knowledge settings (config textareas) | Staff | No chat composer exists; settings textareas are not composers — excluded |
| Messages inbox | `livewire/messages.blade.php` | Read-only conversation list today | Customer | No composer yet; **ready for it** (future Naara-to-Naara) |
| Wizard free text / coupon / search boxes | various | Single-line command inputs | — | Excluded |

## Feature flags per surface (customer vs staff)
Support chat (customer): text, emoji, attach (image/PDF — matches `SupportAttachment`), voice. **Off:** GIF, schedule, location, contact, poll, audio file, recent strip, slash snippets (server does not accept them; GIF and scheduling are staff conveniences).
Naara Line (customer, MMS): text, emoji, photo (jpeg/png/gif), voice — same as the server's MMS rules. Photo/voice only when the line is MMS-capable (`$canAttach`).
Everything else in the prototype is implemented and flag-gated for the Naara-to-Naara messaging batch.

## Ambiguous fork (surfaced, decided, reversible)
Livewire transport vs a dedicated HTTP send endpoint — see the plan.
