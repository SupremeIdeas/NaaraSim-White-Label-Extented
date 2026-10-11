// Sign-in field feedback (see resources/css/field-glow.css). While someone types in a field inside a `[data-glow-fields]` form it carries `.is-typing`;
// 700 ms after the last keystroke the class drops and the field eases back to its normal focus colour. One delegated listener, no per-field setup,
// so it also works for fields Livewire re-renders.
const IDLE_MS = 700;
const timers = new WeakMap();

function isGlowField(el) {
    return el instanceof HTMLInputElement && !!el.closest('[data-glow-fields]') && !['checkbox', 'radio', 'submit', 'button', 'file', 'hidden'].includes(el.type);
}

function settle(el) {
    clearTimeout(timers.get(el));
    timers.set(el, setTimeout(() => el.classList.remove('is-typing'), IDLE_MS));
}

export function registerFieldGlow() {
    if (window.__naaraFieldGlow) return;
    window.__naaraFieldGlow = true;

    document.addEventListener('input', (e) => {
        const el = e.target;
        if (!isGlowField(el)) return;
        el.classList.add('is-typing');
        settle(el);
    }, true);

    // Leaving the field ends the glow at once; also clears if the page restores a value without typing.
    document.addEventListener('focusout', (e) => {
        const el = e.target;
        if (!isGlowField(el)) return;
        clearTimeout(timers.get(el));
        el.classList.remove('is-typing');
    }, true);
}

registerFieldGlow();
