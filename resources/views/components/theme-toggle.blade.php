{{--
    Theme toggle (blueprint Section 4.3 · BLUEPRINT-batch1-sections §2). A sun/moon
    switch (Uiverse.io by RiccardoRapelli, rebuilt on brand + class-scoped so it is
    valid HTML when rendered twice on one page). SVG/CSS only — no emoji. The
    behavioural contract is unchanged from the old icon button: state persists in
    localStorage, is applied to <html> via Alpine, and a `theme-changed` event is
    dispatched for any listener (charts, GSAP). The CSS lives in ui-elements.css.
--}}
<label class="nx-switch align-middle"
       x-data="{ dark: document.documentElement.classList.contains('dark') }"
       aria-label="Toggle dark mode" :title="dark ? 'Switch to light mode' : 'Switch to dark mode'">
    <input type="checkbox" :checked="dark" :aria-pressed="dark.toString()"
           @change="dark = !dark;
                    localStorage.setItem('theme', dark ? 'dark' : 'light');
                    document.documentElement.classList.toggle('dark', dark);
                    $dispatch('theme-changed', { dark })">
    <span class="slider">
        <span class="sun-moon">
            <span class="moon-dot d1"></span>
            <span class="moon-dot d2"></span>
            <span class="moon-dot d3"></span>
            <span class="light-ray r1"></span>
            <span class="light-ray r2"></span>
            <span class="light-ray r3"></span>
        </span>
        <span class="cloud c1"></span>
        <span class="cloud c2"></span>
        <span class="cloud c3"></span>
        <span class="stars">
            <span class="star s1"></span>
            <span class="star s2"></span>
            <span class="star s3"></span>
            <span class="star s4"></span>
        </span>
    </span>
</label>
