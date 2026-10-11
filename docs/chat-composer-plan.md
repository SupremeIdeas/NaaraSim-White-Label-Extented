# Chat Composer Pro — Phase 1 plan

## Packaging
`<naara-composer>` — a **light-DOM Custom Element** backed by an ES module under `resources/js/composer/`, code-split (loaded only on pages that render it). Per-instance state in a `Composer` class: no globals, no function reassignment, no element ids (so two composers on one page cannot collide). Styling: `resources/css/composer.css`, brand-token driven (`--brand-primary`), light + dark.

## Transport (the consolidated question)
- (a) Livewire per interaction — offline queue/retry only while the tab is open.
- (b) a dedicated HTTP endpoint — full offline queue.
**Decision:** the composer is transport-agnostic. It builds one payload and fires a cancelable `cc:transmit` event; the host answers it. Livewire surfaces answer with `$wire.upload()` + their existing action (**no second transport, no new server surface, existing validation/throttle/auth untouched**). A surface that sets `endpoint` gets the built-in HTTP path (multipart, `Idempotency-Key`, IndexedDB outbox, exponential-backoff retry, scheduled send) — that is option (b), ready for the Naara-to-Naara batch (recommended there). Nothing new is exposed today.

## Modes
- `chat` — Send button, 3 s undo ring, outbox, drafts. Used by NaaraCare.
- `field` — no send button/undo/outbox; it only collects text + attachments + voice and emits `cc:change`. Used inside the paid Naara Line modal so a money action keeps its own explicit button.

## Events (bubbling CustomEvents on the element)
`cc:typing`, `cc:change`, `cc:transmit` (cancelable, host handles), `cc:queued`, `cc:sent`, `cc:cancelled`, `cc:error`. Nothing listens on a broadcast channel yet; the events are the extension point for the real-time batch.

## Safety
- Server remains the authority: `SupportAttachment::uploadRules()`, the `voiceNote` MIME list + transcoder, throttle, MMS rules are unchanged.
- Client-side: type/size caps from config, image auto-compress (never GIF), no `innerHTML` with user data (all text via `textContent`), blob URLs revoked, mic permission explained before the OS prompt, no `alert/prompt/confirm` (inline dialogs), no demo-mode, no hot-linked assets, SVG icons only.
- Rollout: `config('composer.enabled')` flag per surface (`composer.surfaces.support_chat|send_message`); the old markup stays in the view behind the flag until the new one is verified, then is removed.

## Verification bar
Unit/feature tests for the Blade wrapper + flags + glue contract; Playwright matrix (Chromium: mobile 430 + desktop 1280, light + dark, two composers on one page, keyboard-only, offline queue via `endpoint` mode). Real-browser limits are reported honestly (mic/camera/geolocation need a real device).

## Recent photos (attach sheet) — what is and is not possible
A web page is **not allowed** to list a phone's photo library; no browser API does it. So the strip only ever shows real images, from the first source that has any:
1. **Native app bridge** — `window.NaaraNative.recentPhotos({limit})` (returns `[{id, thumb, file(): Promise<File>}]`) or the Capacitor `Media` plugin (`@capacitor-community/media`, `getMedias`/`getMediaByIdentifier`). This is the real "device gallery" path and works only in the installed Android/iOS builds, which must add that plugin and the photo-library permission (`iosPhotoLibraryUsageDescription` is already emitted by AppStudio when Camera is enabled; the CI does not yet `npm i` the plugin — one line in `android-build.yml` / `codemagic.yaml` when you want it).
2. **`recentEndpoint`** — a member's recent uploads from our own backend.
3. **This device's own history** — photos the member attached/pasted here, stored on this device only (IndexedDB thumbnail + the file), per account, 12 max, 30 days, with a Clear button.
The first tile is always **Browse** (the OS photo picker — on phones it opens on the newest photos).
