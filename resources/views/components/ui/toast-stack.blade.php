{{-- Global toast engine (Modules 32 + platform audit §8). Mounted ONCE in the
     base layout. ONE engine, two shapes:

     • Small corner toast (admin saves, light confirmations):
         $this->dispatch('nx-toast', type: 'success', message: '…');

     • Hero toast — a big, centred status card for MONEY/ACTIVITY outcomes
       (checkout, top-up, refund, credits). Server-anchored: dispatch only
       AFTER the action commits, so the user never sees a false "success":
         $this->dispatch('nx-toast', variant: 'hero', type: 'success',
             title: 'Order confirmed',
             message: 'Your eSIM is being provisioned.',
             cta: ['label' => 'View my eSIM', 'href' => route('dashboard')]);

     Types: success | error | pending | info. Failures are sticky by default
     (reassuring "you were not charged" copy); success/info auto-dismiss.
     SVG glyphs only (no emoji). Accessible: role=alert for errors, role=status
     otherwise. Reduced-motion + dark-mode handled in CSS. --}}
<div x-data="{
        toasts: [],
        hero: null,
        heroTimer: null,
        push(detail) {
            if ((detail.variant || 'toast') === 'hero') return this.showHero(detail);
            const t = { id: Date.now() + Math.random(), type: detail.type || 'info', message: detail.message || '' };
            this.toasts.push(t);
            if (this.toasts.length > 4) this.toasts.shift();
            setTimeout(() => this.dismiss(t.id), 4500);
        },
        dismiss(id) { this.toasts = this.toasts.filter(t => t.id !== id); },
        showHero(detail) {
            clearTimeout(this.heroTimer);
            const type = detail.type || 'info';
            // Failures stay until dismissed; everything else auto-clears.
            const sticky = detail.sticky ?? (type === 'error');
            this.hero = {
                id: Date.now() + Math.random(),
                type,
                title: detail.title || '',
                message: detail.message || '',
                cta: detail.cta || null,
                sticky,
            };
            if (! sticky) this.heroTimer = setTimeout(() => this.dismissHero(), 5500);
        },
        dismissHero() { clearTimeout(this.heroTimer); this.hero = null; },
     }"
     x-on:nx-toast.window="push($event.detail)"
     x-on:keydown.escape.window="dismissHero()">

    {{-- Hero layer — a premium, centred success/status moment (fintech-style),
         well clear of the header. A soft scrim focuses it; tap-away or the
         button dismisses. --}}
    <div x-show="hero" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center px-4"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-black/45" x-on:click="dismissHero()"></div>
        <template x-if="hero">
            <div class="nx-hero pointer-events-auto"
                 :class="'nx-hero--' + hero.type"
                 :key="hero.id"
                 x-transition:enter="nx-hero-enter"
                 :role="hero.type === 'error' ? 'alert' : 'status'"
                 aria-live="assertive">
                <button type="button" class="nx-hero__close" x-on:click="dismissHero()" aria-label="Dismiss">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
                <div class="nx-hero__glyph" :class="'nx-hero__glyph--' + hero.type" aria-hidden="true">
                    {{-- success: drawn check --}}
                    <svg x-show="hero.type === 'success'" class="nx-hero__draw" viewBox="0 0 52 52"><path class="nx-hero__tick" fill="none" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" d="M14 27l8 8 16-18"/></svg>
                    {{-- error: cross --}}
                    <svg x-show="hero.type === 'error'" class="nx-hero__draw" viewBox="0 0 52 52"><path class="nx-hero__tick" fill="none" stroke-width="5" stroke-linecap="round" d="M18 18l16 16M34 18L18 34"/></svg>
                    {{-- pending: spinner --}}
                    <svg x-show="hero.type === 'pending'" class="nx-hero__spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                    {{-- info --}}
                    <svg x-show="hero.type === 'info'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round" class="h-9 w-9"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                </div>
                <p class="nx-hero__title" x-text="hero.title" x-show="hero.title"></p>
                <p class="nx-hero__msg" x-text="hero.message" x-show="hero.message"></p>
                <template x-if="hero.cta">
                    <a :href="hero.cta.href" class="nx-hero__cta" x-text="hero.cta.label"></a>
                </template>
            </div>
        </template>
    </div>

    {{-- Corner layer — light confirmations. Bottom-centre on mobile (noticed,
         not tucked in a corner), bottom-right on desktop. --}}
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[90] flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-4 sm:items-end sm:px-0" aria-live="polite">
        <template x-for="t in toasts" :key="t.id">
            <div class="nx-toast pointer-events-auto"
                 :class="{ 'nx-toast--success': t.type === 'success', 'nx-toast--error': t.type === 'error', 'nx-toast--info': t.type === 'info' }"
                 role="status">
                <span class="nx-toast__glyph" :class="'nx-toast__glyph--' + t.type" aria-hidden="true">
                    <svg x-show="t.type === 'success'" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    <svg x-show="t.type === 'error'" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    <svg x-show="t.type === 'info'" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                </span>
                <span class="min-w-0 flex-1 font-medium" x-text="t.message"></span>
                <button type="button" class="shrink-0 opacity-50 transition hover:opacity-100" x-on:click="dismiss(t.id)" aria-label="Dismiss notification">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
        </template>
    </div>
</div>
