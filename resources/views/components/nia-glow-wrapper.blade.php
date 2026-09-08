@props([
    // Bind the glow to Nia's typing state (Alpine expression, e.g. "isNiaTyping").
    // When true the glow turns on regardless of focus — used on AI/typing bubbles.
    'typing' => null,
    // When true, focus/input on inner fields also drives the glow (the input bar).
    'interactive' => false,
])

{{--
    BUILD-3 §5 — Nia brand glow wrapper. Wraps ONLY the Nia chat input and Nia's
    AI/typing bubbles (never search, login, forms, filters, admin, or the Wizard).

    Turns the halo on for: focus / input / `typing` truthy. Turns it off 300ms
    after blur or typing stops (Alpine debounce). Pauses the rotation while
    scrolled off-screen via x-intersect, and inherits the wrapped element's
    border-radius so it hugs whatever it wraps.
--}}
<div
    x-data="{
        focused: false,
        typing: {{ $typing !== null ? $typing : 'false' }},
        offscreen: false,
        _t: null,
        get on() { return this.focused || this.typing; },
        turnOn() { if (this._t) { clearTimeout(this._t); this._t = null; } this.focused = true; },
        scheduleOff() { if (this._t) clearTimeout(this._t); this._t = setTimeout(() => { this.focused = false; }, 300); },
    }"
    @if ($typing !== null) x-effect="typing = ({{ $typing }})" @endif
    @if ($interactive)
        x-on:focusin="turnOn()"
        x-on:input="turnOn()"
        x-on:focusout="scheduleOff()"
    @endif
    x-intersect:enter="offscreen = false"
    x-intersect:leave="offscreen = true"
    :class="{ 'nia-glow--on': on, 'nia-glow--offscreen': offscreen }"
    {{ $attributes->class('nia-glow') }}
>
    {{ $slot }}
</div>
