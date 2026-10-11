# Livewire "hang" playbook

A control that "stops responding until I refresh" is almost never a server problem. Work the list in order and stop at the first hit. Do not guess.

1. **Console first.** One uncaught exception inside an Alpine `x-data` stops that whole component's reactivity (the commonest cause of tabs and quick-amount buttons freezing). The client error beacon (`resources/js/client-errors.js`) records these; check Admin, Setup Health, Client errors.
2. **Network.** Does the Livewire request fire? 500, 419 (expired CSRF/session) or 422 (validation)? Did it return 200 but the DOM did not change (a morph problem)?
3. **Keys and roots.** Duplicate or missing `wire:key` in loops, nested components without keys, `@if` blocks swapping similar markup, and more than one root element (a Livewire component has exactly one root).
4. **Alpine and Livewire fighting.** `x-data` re-initialised on morph, `@entangle` without `.live` expectations, `x-model` and `wire:model` on one field, a third-party widget (editor, chart, file input, LiveKit video) without `wire:ignore`.
5. **Leftover layers.** `wire:loading`/`wire:target` that hides or disables a control for good, and `pointer-events: none` overlays or backdrops that never removed themselves (search `nx-preloader`, `fixed inset-0`).
6. **Slow renders.** Heavy queries in `render()`/`mount()`/computed properties run on every request: use `#[Computed]`, pagination, caching, or `wire:init`.
7. **Echo/Reverb listeners** that call `$refresh` in a loop or re-render on every keystroke.
8. **Only then change code**, and add a regression: click each tab or quick amount 20 times rapidly and assert state and no console errors (`scripts/responsive/audit.mjs` is the place to extend).

Layers: the z-index scale and the dock/header measurements live in `resources/css/nx-layers.css` and `resources/js/nx-layers.js`. `window.nxLayers.audit()` lists every visible text element a fixed layer is covering.
