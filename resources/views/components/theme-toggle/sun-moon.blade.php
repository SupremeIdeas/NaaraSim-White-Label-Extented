{{-- Sun & Moon (default) — Uiverse.io by RiccardoRapelli, rebuilt on brand. --}}
<label class="nx-theme-toggle nx-theme-toggle--sun-moon align-middle"
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
