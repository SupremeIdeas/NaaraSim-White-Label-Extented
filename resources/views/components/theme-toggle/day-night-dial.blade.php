{{-- Day/Night Dial — inspired by Uiverse.io by cssbuttons-io's skeuomorphic
     realistic-knob switch (SupremeIdeas/Uicomponents, uiverse-raw-components-
     batch1), re-tinted from its red/green states to warm-gold day / navy
     night. A brushed-metal-style knob slides across a rounded track. --}}
<label class="nx-theme-toggle nx-theme-toggle--day-night-dial align-middle"
       x-data="{ dark: document.documentElement.classList.contains('dark') }"
       aria-label="Toggle dark mode" :title="dark ? 'Switch to light mode' : 'Switch to dark mode'">
    <input type="checkbox" :checked="dark" :aria-pressed="dark.toString()"
           @change="dark = !dark;
                    localStorage.setItem('theme', dark ? 'dark' : 'light');
                    document.documentElement.classList.toggle('dark', dark);
                    $dispatch('theme-changed', { dark })">
    <span class="track"></span>
    <span class="knob"></span>
</label>
