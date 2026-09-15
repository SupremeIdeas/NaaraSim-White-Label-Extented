{{-- Eclipse Orb — inspired by Uiverse.io by Yaya12085's realistic 3D sphere
     switch (SupremeIdeas/Uicomponents, uiverse-raw-components-batch1). A
     tactile, skeuomorphic orb: a bright gold sun-sphere by day, an eclipsed
     dark sphere by night, with a subtle hover tilt. --}}
<label class="nx-theme-toggle nx-theme-toggle--eclipse-orb align-middle"
       x-data="{ dark: document.documentElement.classList.contains('dark') }"
       aria-label="Toggle dark mode" :title="dark ? 'Switch to light mode' : 'Switch to dark mode'">
    <input type="checkbox" :checked="dark" :aria-pressed="dark.toString()"
           @change="dark = !dark;
                    localStorage.setItem('theme', dark ? 'dark' : 'light');
                    document.documentElement.classList.toggle('dark', dark);
                    $dispatch('theme-changed', { dark })">
    <span class="track"></span>
    <span class="orb"></span>
</label>
