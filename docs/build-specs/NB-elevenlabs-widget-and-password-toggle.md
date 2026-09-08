You are working in the existing NaaraSim Laravel codebase. Do NOT assume folder
structure or invent new patterns — this repo already has working precedent for
almost everything below. Match it exactly.

FIRST, READ (in this order) BEFORE WRITING ANY CODE:
1. app/Models/Setting.php — the only sanctioned key/value config store
   (Setting::getValue / Setting::setValue, encrypted at rest, cached).
2. app/Support/Tracking.php + resources/views/partials/tracking.blade.php +
   its `@include` in resources/views/components/layouts/app.blade.php —
   this is the reference implementation for "admin pastes a snippet/ID,
   it's validated, stored via Setting, and injected into <head> only when
   present." Feature 1 below is the same shape, just injected in the footer
   instead of <head>, and storing a trusted snippet instead of an ID.
3. app/Livewire/Admin/Integrations.php +
   resources/views/livewire/admin/integrations.blade.php — the admin UI
   pattern (role-gated with `abort_unless(Auth::user()->hasAnyRole([...]))`,
   mount() hydrates from Setting, a save method validates + persists +
   toasts via `$this->dispatch('nx-toast', ...)`).
4. routes/web.php around line 242 — how admin Livewire pages get routed
   under the admin group/prefix.
5. app/Support/HtmlSanitizer.php — read this CAREFULLY. It is the allowlist
   sanitizer used for the Custom-HTML section builder type. It strips every
   `<script>` tag and unwraps any element not on its tag allowlist (which
   `<elevenlabs-convai>` is not — it would be unwrapped to nothing). DO NOT
   route the ElevenLabs snippet through this sanitizer or reuse its
   allowlist for this feature — it will silently reduce the widget to an
   empty string. This field needs its own narrow, admin-only trusted-input
   path, the same trust model Tracking.php already uses for the Pixel/GA
   snippets (only admins/super_admins can write it; it's rendered raw).
6. config/security.php + app/Http/Middleware/SecurityHeaders.php — read the
   `policyWithTurnstile()` method specifically. This is how the app already
   whitelists ONE external origin in the CSP (`script-src` + `frame-src`) at
   runtime, only when that integration is active, without loosening the
   policy globally. Feature 1 requires the same trick for ElevenLabs'
   script host and API/websocket host, added to `script-src` and
   `connect-src` (and `worker-src`/`style-src` if ElevenLabs' current embed
   needs them — verify from their docs, don't guess). Skipping this step
   means the widget fails closed with zero visible error: the browser just
   silently blocks the script per CSP, so QA will look like "it doesn't
   work" with no stack trace pointing at the cause.
7. resources/views/components/site-footer.blade.php and where it's rendered
   from resources/views/components/layouts/marketing.blade.php (`<x-site-footer
   variant="full" />` and the `slim` variant on auth pages) — confirms this
   footer is marketing-scope, not the authenticated app shell. Confirm with
   me whether "site wide" means marketing pages only (nav/footer visible
   there) or literally every authenticated page too, before wiring it in —
   default to marketing pages only if unspecified, since that's what "front
   end pages" in the request implies.
8. resources/views/partials/icon-sprite.blade.php and
   resources/views/components/icon.blade.php — the `<symbol id="i-name">`
   + `<x-icon name="...">` pattern used for every icon in the app. There is
   currently no eye / eye-off icon defined.
9. Every file with a raw `type="password"` input (grep confirmed these 10):
   resources/views/auth/login.blade.php
   resources/views/auth/register.blade.php
   resources/views/auth/admin-login.blade.php
   resources/views/auth/reset-password.blade.php
   resources/views/install/setup.blade.php
   resources/views/livewire/admin/email-settings.blade.php
   resources/views/livewire/admin/recover-password.blade.php
   resources/views/livewire/admin/account.blade.php
   resources/views/livewire/admin/staff.blade.php
   resources/views/livewire/security-center.blade.php
   Note some are plain Blade forms (`name="password"`) and some are inside
   Livewire components (likely `wire:model` bound). The toggle must work for
   both without interfering with the existing `name`/`wire:model`/validation
   error markup already on each field.

---

FEATURE 1 — ADMIN-CONTROLLED ELEVENLABS AI AGENT WIDGET (SITE-WIDE FOOTER)

GOAL: Admin logs into ElevenLabs, copies the embed HTML for their Convai
agent, pastes it into our admin panel, saves — and it just works, site-wide,
with no CSP block, no sanitizer mangling, no layout shift, no duplicate
widget on client-navigated (wire:navigate) page transitions.

RESEARCH STEP (do this first, before coding): Look up ElevenLabs' current
Convai widget embed documentation. Confirm: (a) the exact tag/script markup
they currently ship (it's typically a custom element like
`<elevenlabs-convai agent-id="...">` plus a `<script src="...">` loader —
verify, don't assume), (b) which host(s) the loader script is served from,
(c) which host(s) the widget calls at runtime for the actual
voice/websocket connection, and (d) how ElevenLabs' own widget expects to be
repositioned — check whether the custom element exposes documented
attributes (e.g. a `variant`/`placement` prop) or CSS custom properties for
this, versus needing a plain CSS override on the host element from our side.
Build the offset control (below) on top of whatever mechanism they actually
support — don't guess at their internal CSS structure if it's undocumented
and likely to break on their next widget update.

REQUIREMENTS:

1. Storage
   - Use `Setting::setValue()` / `Setting::getValue()`, following
     `App\Support\Tracking`'s pattern exactly — a new `App\Support\ElevenLabsWidget`
     (or similar name matching repo convention) class with a settings key
     like `integrations.elevenlabs_widget_html`, plus an `enabled` boolean
     toggle key so admin can disable without deleting the saved snippet.
   - Store the snippet as-is (trusted admin input, same trust boundary as
     Tracking's IDs). Do NOT pass it through HtmlSanitizer.
   - Do basic defensive validation before saving (not full sanitization):
     reject anything that doesn't look like it originates from ElevenLabs
     (e.g. require it to reference an elevenlabs.io / their CDN domain
     somewhere in the markup) so a compromised admin session can't be used
     to paste arbitrary third-party script through this specific field.
     Document this as a deliberate narrower trust model than the general
     Custom-HTML section builder, not an oversight.

2. Admin UI
   - Add to the existing `App\Livewire\Admin\Integrations` component (this
     already owns "growth stack" settings like Pixel/GA) rather than
     creating a whole new admin page, unless you find a reason the existing
     component is a bad fit — if so, tell me why before deviating.
   - A textarea for the raw embed HTML, an enable/disable toggle, a "Saved"
     confirmation via the existing `nx-toast` dispatch pattern, and a short
     inline note reminding the admin the widget won't appear until CSP
     picks up the ElevenLabs host (should be automatic — just document it).
   - Role-gated the same way (`abort_unless(Auth::user()->hasAnyRole(['super_admin','admin']), 404)`).

2a. Position adjuster (no-CSS nudge control, admin's explicit ask)
   - Reason for this: the admin has hit this exact need before on a WordPress/
     Elementor site and solved it with hand-written custom CSS. The point
     here is admin needs zero CSS knowledge — just small directional nudges
     to dodge a floating nav, cookie banner, or chat bubble that might land
     near the same corner.
   - New Setting keys alongside the widget HTML/enabled ones, e.g.
     `integrations.elevenlabs_widget_offset_x` and `..._offset_y`, integers
     in pixels, defaulting to 0 (ElevenLabs' own default position,
     untouched, when admin never sets an offset).
   - Admin UI controls: four nudge buttons (up/down/left/right, e.g. ±4px or
     ±8px per click) plus the current offset shown as editable numbers, and
     a "Reset to default" action. Don't build a raw free-text CSS field —
     that reopens the exact problem (admin needing to know CSS) this is
     meant to solve.
   - Clamp both offsets server-side to a sane range (e.g. -120 to 120px) so
     a typo can't push the widget fully off-screen or under other UI.
   - Rendering: apply the offset as a small scoped `<style>` block or inline
     CSS custom properties targeting the widget's host element (via an ID/
     class we control on the wrapper, not by reaching into ElevenLabs'
     shadow DOM), emitted from the same partial, only when non-zero. Use
     `transform: translate(Xpx, Ypx)` on the host element unless the
     research step above found ElevenLabs supports positioning offsets
     natively (their attributes are preferable if available, since
     `transform` on a `position: fixed` element can misbehave — verify
     which approach actually holds the widget in place across viewport
     sizes/mobile before committing to it).
   - If a live preview in the admin panel is easy to add (e.g. an iframe or
     a mounted instance of the widget reacting to the nudge buttons in real
     time) include it; if it adds real complexity, skip it and instead make
     saving instant with a toast, and tell me to just check the live site
     in another tab — don't over-build this part.

3. Rendering
   - New partial, e.g. `resources/views/partials/elevenlabs-widget.blade.php`,
     mirroring `partials/tracking.blade.php`'s `@if` pattern — renders only
     when enabled AND a snippet is saved.
   - Include it in the marketing footer scope confirmed in step 7 above —
     most likely inside `site-footer.blade.php` or right before `</body>` in
     `marketing.blade.php`, not globally in `app.blade.php` (that layout is
     shared with authenticated/admin pages). Confirm scope with me if
     ambiguous before finalizing placement.
   - If the app uses `wire:navigate` for marketing nav (it does — check
     `site-footer`/`marketing.blade.php` links), verify the widget script
     re-initializes correctly across navigations rather than either
     disappearing or duplicating. Test this explicitly.

4. CSP (the step most likely to be skipped and cause "it doesn't work")
   - Extend `SecurityHeaders.php` with an ElevenLabs equivalent of
     `policyWithTurnstile()`: when the widget is enabled + configured, append
     ElevenLabs' script host to `script-src` and its API/websocket host to
     `connect-src`, only on the pages where the widget actually renders if
     that's easy to scope, otherwise globally-but-conditionally (same
     conditional-injection principle as Turnstile — never loosen the policy
     when the feature is off).
   - If ElevenLabs' loader needs `worker-src` or additional `style-src`
     allowances (common for audio-processing widgets), add those
     conditionally too, based on what you find in their current docs.

5. No regressions
   - Confirm Turnstile's existing CSP injection still works unmodified —
     don't refactor `policyWithTurnstile` in a way that breaks it; add the
     ElevenLabs logic alongside it cleanly (extract a small shared helper if
     that reduces duplication, but don't restructure the Turnstile path).

---

FEATURE 2 — SHOW/HIDE PASSWORD TOGGLE (EYE ICON) ON ALL PASSWORD FIELDS

REQUIREMENTS:

1. Icons
   - Add `i-eye` and `i-eye-off` (or `eye-closed`) symbols to
     `resources/views/partials/icon-sprite.blade.php`, matching the existing
     stroke-based style (`fill="none" stroke="currentColor" stroke-width="2"
     stroke-linecap="round" stroke-linejoin="round"`) so they inherit
     text color and dark-mode automatically like every other icon here.

2. Reusable component
   - Build one Blade component (e.g. `x-password-input`) that wraps a
     password `<input>` with an absolutely-positioned toggle button using
     Alpine (`x-data="{ show: false }"`, toggling `type` between
     `password`/`text` and swapping the `<x-icon>` shown), styled to match
     the existing input classes in this repo (see the exact Tailwind classes
     already used in `auth/login.blade.php`'s password field as the
     baseline — don't introduce a new visual style).
   - The component must accept and forward through: `name`, `wire:model`
     (or `wire:model.live`, whichever each call site currently uses),
     `id`, `required`, `autocomplete`, `placeholder`, extra classes, and
     must NOT interfere with Laravel's `@error`/`old()` display already
     present around each existing field, or with Livewire's diffing (avoid
     re-keying the input in a way that loses focus/cursor position on
     Livewire re-renders).
   - Toggle button must be a `type="button"` (never submits the form),
     keyboard-accessible, with an `aria-label` that changes between "Show
     password" / "Hide password", and `aria-pressed` reflecting state.

3. Apply to all 10 files found in the read-first step
   - Swap each existing `<input type="password" ...>` for the new component,
     preserving every existing attribute/name/wire:model/validation/error
     markup around it exactly as-is — this is a surgical swap, not a
     re-design of those forms.
   - For the Livewire-bound ones (email-settings, recover-password, account,
     staff, security-center) confirm the toggle is purely client-side (no
     extra network round-trip on every keystroke or every toggle click).

4. No regressions
   - Password managers/autofill must keep working (don't strip
     `autocomplete` attributes).
   - Existing `@error` styling/positioning around each field must be
     unaffected.

---

CONSTRAINTS (both features):
- Read the actual current code before touching it — several files above are
  large; view them in full, don't guess at surrounding markup.
- Match naming, folder structure, and Tailwind/dark-mode conventions 100%.
- Add comments explaining the CSP conditional-injection logic and the
  "don't route this through HtmlSanitizer" decision, so a future dev doesn't
  "fix" it by adding sanitization back in and silently breaking the widget.
- Do not break Turnstile's CSP handling, Tracking's Pixel/GA injection, or
  any existing login/register/reset flow.

DELIVERABLES:
List every file created/modified with full code, a summary of what current
ElevenLabs embed markup/hosts you found and are allowlisting (with source
links), and the exact steps for me to test both features end-to-end
(including how to verify the widget survives a `wire:navigate` transition,
that the CSP header actually contains the new origins in a real response
(not just in code), and that nudging the position offsets up/down/left/right
in admin visibly moves the widget on the live marketing page without
breaking it on mobile viewport widths).
