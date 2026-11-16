{{-- Account > App lock (fingerprint + app password). Skin tokens only, SVG icons only. --}}
<div>
<x-nx.page class="ns-narrow">
    <a href="{{ route('account') }}" wire:navigate class="ns-pg__back"><x-nx.icon name="left" /> Account</a>
    <h1 class="ns-h1" style="margin-top:6px">App lock</h1>
    <p class="ns-sub">Keep your account closed when you step away. You open it again with your fingerprint, face or screen lock, or your app password.</p>

    @if ($flash)<x-nx.note icon="badge-check" role="status" style="margin-top:14px">{{ $flash }}</x-nx.note>@endif
    @if ($error)<x-nx.note icon="info" variant="warn" role="alert" style="margin-top:14px">{{ $error }}</x-nx.note>@endif

    <div class="al-hero" style="margin-top:16px"><x-fingerprint :size="84" /><div><b>{{ $on ? 'App lock is on' : 'App lock is off' }}</b><small>{{ $on ? 'Your account locks when you leave, and opens with your fingerprint.' : 'Turn it on in three short steps.' }}</small></div></div>

    <section class="ns-pg__card ns-ring" style="margin-top:14px" data-app-lock-settings>
        <label class="ns-lbl" for="al-current">Your account password</label>
        <input id="al-current" type="password" wire:model="current" autocomplete="current-password" class="ns-input" placeholder="Needed to change these settings">
    </section>

    <section class="ns-pg__card ns-ring" style="margin-top:14px">
        <h2 class="ns-pg__h2"><span class="al-step">1</span> App password {!! $hasPassword ? '<span class="ns-pill ns-pill--rec">Set</span>' : '' !!}</h2>
        <p class="ns-small" style="margin:4px 0 12px">A password just for opening the app. It is not your account password.</p>
        <form wire:submit="savePassword" class="space-y-3">
            <input type="password" wire:model="newPassword" autocomplete="new-password" class="ns-input" placeholder="New app password (6 or more characters)" aria-label="New app password">
            <input type="password" wire:model="confirmPassword" autocomplete="new-password" class="ns-input" placeholder="Repeat it" aria-label="Repeat the app password">
            @error('newPassword')<p class="ns-small" style="color:rgb(var(--nx-danger,190 40 40))" role="alert">{{ $message }}</p>@enderror
            <button type="submit" wire:loading.attr="disabled" wire:target="savePassword" class="ns-cta ns-cta--pill">{{ $hasPassword ? 'Change app password' : 'Save app password' }}</button>
        </form>
    </section>

    <section class="ns-pg__card ns-ring" style="margin-top:14px" data-passkeys x-data="{ ok: false, busy: false, msg: '', name: '',
            async add() { this.busy = true; this.msg = ''; if (!(await $wire.prepare())) { this.busy = false; return } const r = await window.nxPasskey.register(this.name || 'This device'); this.busy = false; if (r.ok) { this.name = ''; $wire.$refresh() } else if (r.error !== 'cancelled') { this.msg = 'That did not work. Try again.' } } }"
            x-init="window.nxPasskey && window.nxPasskey.platformReady().then(v => ok = v)">
        <h2 class="ns-pg__h2"><span class="al-step">2</span> Fingerprint, face or screen lock</h2>
        <p class="ns-small" style="margin:4px 0 12px">Your fingerprint stays on your phone; we only keep a public key.</p>
        @forelse ($passkeys as $pk)
            <div class="ns-pg__head" style="flex-wrap:nowrap;align-items:center;margin-bottom:8px">
                <span style="display:flex;align-items:center;gap:10px;font-weight:600;color:rgb(var(--nx-text))"><x-fingerprint :size="26" /> {{ $pk->name }} <small class="ns-small" style="font-weight:400">{{ $pk->last_used_at ? 'used '.$pk->last_used_at->diffForHumans() : 'not used yet' }}</small></span>
                <button type="button" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" @click="if (confirm('Remove this device?')) window.nxPasskey.remove({{ $pk->id }}).then(() => $wire.$refresh())">Remove</button>
            </div>
        @empty
            <p class="ns-small" style="margin:0 0 12px">No device added yet.</p>
        @endforelse
        <div x-show="ok" x-cloak style="display:none">
            <input type="text" x-model="name" maxlength="60" placeholder="Name this device, e.g. My phone" class="ns-input" style="margin-bottom:10px" aria-label="Device name">
            <button type="button" :disabled="busy" @click="add()" class="ns-cta ns-cta--pill"><x-fingerprint :size="20" /> <span x-text="busy ? 'Waiting for your fingerprint...' : 'Add this device'"></span></button>
        </div>
        <p x-show="!ok" class="ns-small" style="margin:0">This browser or device has no fingerprint, face or screen lock available. Open this site on your phone to add one.</p>
        <p x-show="msg" x-text="msg" class="ns-small" style="color:rgb(var(--nx-danger,190 40 40));margin-top:8px" role="alert"></p>
    </section>

    <section class="ns-pg__card ns-ring" style="margin-top:14px">
        <h2 class="ns-pg__h2"><span class="al-step">3</span> When to lock</h2>
        <select wire:model="minutes" class="ns-select" aria-label="When to lock" style="margin:10px 0 12px">
            @foreach (\App\Support\AppLock::CHOICES as $m => $label)<option value="{{ $m }}">{{ $label }}</option>@endforeach
        </select>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="turnOn" wire:loading.attr="disabled" wire:target="turnOn" class="ns-cta ns-cta--pill" @disabled(! $hasPassword)>{{ $on ? 'Save' : 'Turn on app lock' }}</button>
            @if ($on)<button type="button" wire:click="turnOff" wire:loading.attr="disabled" wire:target="turnOff" class="ns-cta ns-cta--pill ns-cta--ghost">Turn off</button>@endif
        </div>
        @if (! $hasPassword)<p class="ns-small" style="margin:10px 0 0">Set your app password (step 1) to turn the lock on.</p>@endif
    </section>
</x-nx.page>
<style>
.al-hero { display: flex; align-items: center; gap: 16px; padding: 18px; border-radius: 22px; background: rgb(var(--nx-surface-2)); box-shadow: inset 0 0 0 1px rgb(var(--nx-line)); }
.al-hero b { display: block; font-size: 17px; color: rgb(var(--nx-text)); } .al-hero small { color: rgb(var(--nx-text-2)); }
.al-step { display: inline-grid; place-items: center; width: 24px; height: 24px; margin-inline-end: 8px; border-radius: 99px; font-size: 13px; font-weight: 800; background: rgb(var(--nx-teal-ink)); color: rgb(var(--nx-surface)); }
</style>
</div>
