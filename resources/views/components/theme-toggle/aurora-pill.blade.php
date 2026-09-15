{{-- Aurora Pill — a minimal modern pill switch. The track shifts from a
     warm gold/teal gradient (day) to a deep indigo/navy aurora (night); the
     knob carries a tiny sun/moon glyph that crossfades. --}}
<label class="nx-theme-toggle nx-theme-toggle--aurora-pill align-middle"
       x-data="{ dark: document.documentElement.classList.contains('dark') }"
       aria-label="Toggle dark mode" :title="dark ? 'Switch to light mode' : 'Switch to dark mode'">
    <input type="checkbox" :checked="dark" :aria-pressed="dark.toString()"
           @change="dark = !dark;
                    localStorage.setItem('theme', dark ? 'dark' : 'light');
                    document.documentElement.classList.toggle('dark', dark);
                    $dispatch('theme-changed', { dark })">
    <span class="track"></span>
    <span class="knob">
        <x-icon name="sun" class="icon-sun" />
        <x-icon name="moon" class="icon-moon" />
    </span>
</label>
