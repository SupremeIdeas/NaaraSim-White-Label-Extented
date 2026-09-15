{{-- Horizon Track — the day/night imagery lives in the TRACK itself (a
     warm-to-navy horizon gradient with stars fading in on the dark side),
     not the knob, distinguishing it from the Sun & Moon preset above. --}}
<label class="nx-theme-toggle nx-theme-toggle--horizon-track align-middle"
       x-data="{ dark: document.documentElement.classList.contains('dark') }"
       aria-label="Toggle dark mode" :title="dark ? 'Switch to light mode' : 'Switch to dark mode'">
    <input type="checkbox" :checked="dark" :aria-pressed="dark.toString()"
           @change="dark = !dark;
                    localStorage.setItem('theme', dark ? 'dark' : 'light');
                    document.documentElement.classList.toggle('dark', dark);
                    $dispatch('theme-changed', { dark })">
    <span class="track">
        <span class="dot h1"></span>
        <span class="dot h2"></span>
        <span class="dot h3"></span>
        <span class="dot h4"></span>
    </span>
    <span class="knob"></span>
</label>
