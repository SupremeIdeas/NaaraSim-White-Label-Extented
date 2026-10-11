/**
 * Applies a just-saved appearance to the open page (Prompt 20 §36). The server already renders the same attributes on every
 * full response; this only makes the change instant, with no reload. Fired by the Appearance page (Livewire dispatch).
 */
/** §45: dawn 05:00-07:59, day 08:00-16:59, dusk 17:00-19:59, night 20:00-04:59 on the device clock. */
export function timeOfDay(hour = new Date().getHours()) {
    return hour >= 5 && hour < 8 ? 'dawn' : hour >= 8 && hour < 17 ? 'day' : hour >= 17 && hour < 20 ? 'dusk' : 'night';
}

/** Golden Hour follows the member's own clock (no network, no animation); only that skin carries the attribute. */
function syncTimeOfDay() {
    const html = document.documentElement;
    if (html.getAttribute('data-nx-skin') === 'golden') html.setAttribute('data-nx-tod', timeOfDay());
    else html.removeAttribute('data-nx-tod');
}

/** Passport's big faded country code is `content: attr(data-cc)` on the app wrapper, so mirror the html attribute onto it. */
function syncCountryWatermark() {
    const code = (document.documentElement.getAttribute('data-nx-country') || '').toUpperCase();
    document.querySelectorAll('.ns-app').forEach((el) => (code ? el.setAttribute('data-cc', code) : el.removeAttribute('data-cc')));
}

export function registerAppearance() {
    syncTimeOfDay();
    syncCountryWatermark();
    setInterval(syncTimeOfDay, 10 * 60 * 1000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) syncTimeOfDay(); });
    document.addEventListener('livewire:navigated', () => { syncTimeOfDay(); syncCountryWatermark(); });

    window.addEventListener('nx-appearance', (event) => {
        const d = event.detail || {};
        const html = document.documentElement;
        if (d.skin) html.setAttribute('data-nx-skin', d.skin);
        if ('country' in d) { d.country ? html.setAttribute('data-nx-country', d.country) : html.removeAttribute('data-nx-country'); }
        if ('tod' in d || d.skin) { d.tod ? html.setAttribute('data-nx-tod', d.tod) : html.removeAttribute('data-nx-tod'); syncTimeOfDay(); }
        syncCountryWatermark();
        if (d.accent) html.setAttribute('data-nx-accent', d.accent);
        Object.entries(d.dials || {}).forEach(([dial, value]) => html.setAttribute(`data-nx-${dial}`, value));

        if (d.mode) {
            html.setAttribute('data-nx-mode', d.mode);
            const dark = d.mode === 'dark' || (d.mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            html.classList.toggle('dark', dark);
            try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) { /* storage blocked: the account setting still applies */ }
        } else {
            html.removeAttribute('data-nx-mode');
        }

        // Custom accent variables (both mode variants, picked by the .dark class). Empty css = no custom colour.
        let tag = document.getElementById('nx-accent-vars');
        if (d.css) {
            if (!tag) {
                tag = document.createElement('style');
                tag.id = 'nx-accent-vars';
                document.head.appendChild(tag);
            }
            tag.textContent = d.css;
        } else if (tag) {
            tag.remove();
        }
    });
}
