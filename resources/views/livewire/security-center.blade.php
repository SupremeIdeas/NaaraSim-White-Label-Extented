{{-- Security on the skin system (S3 Batch 6): password, two-factor, email, connected accounts, sessions. --}}
<div x-data="{ codes: false }">
<x-nx.page class="ns-pg ns-pg--mid">
    <h1 class="ns-h1" style="margin-top:6px">Security</h1>
    <p class="ns-sub">Protect your account: password, two-factor, email and sessions.</p>

    @if ($flash)
        <div class="ns-pg__callout {{ $flashType === 'success' ? 'ns-pg__callout--ok' : 'ns-pg__callout--bad' }}" style="margin-top:16px" role="status">
            <x-nx.icon name="{{ $flashType === 'success' ? 'check' : 'info' }}" /><div><p style="margin:0;color:rgb(var(--nx-text))">{{ $flash }}</p></div>
        </div>
    @endif

    {{-- Password --}}
    <section class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:12px">Change password</h2>
        <form wire:submit="updatePassword" class="ns-pg__stack ns-pg__stack--sm">
            <x-ui.password wire:model="current_password" placeholder="Current password" autocomplete="current-password" aria-label="Current password" class="ns-input" />
            @error('current_password') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
            <div class="ns-pg__two">
                <x-ui.password wire:model="password" placeholder="New password" autocomplete="new-password" aria-label="New password" class="ns-input" />
                <x-ui.password wire:model="password_confirmation" placeholder="Confirm new password" autocomplete="new-password" aria-label="Confirm new password" class="ns-input" />
            </div>
            @error('password') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
            <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword" class="ns-cta ns-cta--pill" style="align-self:flex-start;margin-top:4px"><x-nx.icon name="shield" /> Update password</button>
        </form>
    </section>

    {{-- Two-factor --}}
    <section class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2">Two-factor authentication</h2>
        <p class="ns-pg__hint" style="margin-bottom:14px">Add a one-time code from an authenticator app on top of your password.</p>

        @if ($confirmed)
            <div style="display:flex;align-items:center;gap:8px;font-weight:700;color:color-mix(in srgb, rgb(var(--nx-ok)) 72%, rgb(var(--nx-text)))"><x-nx.icon name="check" /> Two-factor is active.</div>
            @if (! empty($recoveryCodes))
                <button type="button" @click="codes = !codes" class="ns-pg__act ns-pg__act--link" style="margin-top:12px">Show / hide recovery codes</button>
                <div x-show="codes" x-cloak class="ns-pg__code" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px">
                    @foreach ($recoveryCodes as $rc)<span>{{ $rc }}</span>@endforeach
                </div>
            @endif
            <div class="ns-pg__btns">
                <button type="button" wire:click="regenerateRecoveryCodes" wire:loading.attr="disabled" wire:target="regenerateRecoveryCodes" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm"><x-nx.icon name="refresh" /> New recovery codes</button>
                <button type="button" wire:click="disable2fa" wire:confirm="Turn off two-factor authentication?" wire:loading.attr="disabled" wire:target="disable2fa" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm ns-ct__danger"><x-nx.icon name="x" /> Disable</button>
            </div>
        @elseif ($showing2faSetup && $qr)
            <div class="ns-pg__row" style="align-items:flex-start;flex-wrap:wrap">
                <div class="ns-pg__qrbox">{!! $qr !!}</div>
                <div class="is-grow" style="min-width:12rem">
                    <p class="ns-pg__hint" style="margin:0 0 8px;font-size:14px">Scan with an authenticator app, or enter this key:</p>
                    <code class="ns-pg__code" style="word-break:break-all;white-space:normal">{{ $secret }}</code>
                    <form wire:submit="confirm2fa" class="ns-pg__row" style="margin-top:12px;flex-wrap:nowrap">
                        <input type="text" wire:model="code" inputmode="numeric" placeholder="123456" aria-label="Authenticator code" class="ns-input" style="width:8rem">
                        <button type="submit" wire:loading.attr="disabled" wire:target="confirm2fa" class="ns-cta ns-cta--pill" style="height:50px">Confirm</button>
                    </form>
                    @error('code') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
                </div>
            </div>
        @else
            <button type="button" wire:click="enable2fa" wire:loading.attr="disabled" wire:target="enable2fa" class="ns-cta ns-cta--pill"><x-nx.icon name="shield" /> Turn on two-factor</button>
        @endif
    </section>

    {{-- Email --}}
    <section class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2">Email address</h2>
        <p class="ns-pg__hint" style="margin-bottom:14px">
            @if ($user->email_verified_at)<strong style="color:color-mix(in srgb, rgb(var(--nx-ok)) 72%, rgb(var(--nx-text)))">Verified.</strong>@else<strong style="color:color-mix(in srgb, rgb(var(--nx-warn)) 72%, rgb(var(--nx-text)))">Not verified.</strong>@endif
            Changing it requires confirming the new address.
        </p>
        <form wire:submit="updateEmail" class="ns-pg__stack ns-pg__stack--sm">
            <input type="email" wire:model="new_email" placeholder="you@example.com" aria-label="New email address" class="ns-input">
            @error('new_email') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
            <x-ui.password wire:model="email_password" placeholder="Your current password" autocomplete="current-password" aria-label="Your current password" class="ns-input" />
            @error('email_password') <span class="ns-pg__fielderr">{{ $message }}</span> @enderror
            <button type="submit" wire:loading.attr="disabled" wire:target="updateEmail" class="ns-cta ns-cta--pill" style="align-self:flex-start;margin-top:4px"><x-nx.icon name="send" /> Update email</button>
        </form>
    </section>

    {{-- Fingerprint / Face / screen lock (passkeys) --}}
    <section class="ns-pg__card ns-ring" data-passkeys x-data="{ ok: false, busy: false, msg: '', name: '',
            go(r) { this.busy = false; if (r.ok) { this.msg = ''; this.name = ''; $wire.$refresh(); } else if (r.error === 'confirm') { location.href = @js(route('password.confirm')) } else if (r.error !== 'cancelled') { this.msg = 'That did not work. Try again, or use another device.' } } }"
            x-init="window.nxPasskey && window.nxPasskey.platformReady().then(v => ok = v)">
        <h2 class="ns-pg__h2" style="margin-bottom:4px">Fingerprint, face or screen lock</h2>
        <p class="ns-small" style="margin:0 0 12px">Sign in with the lock you already use on this device. Your fingerprint never leaves your phone; we only store a public key. You can always use your password instead.</p>
        @forelse ($passkeys as $pk)
            <div class="ns-pg__head" style="flex-wrap:nowrap;align-items:center;margin-bottom:8px">
                <span style="display:flex;align-items:center;gap:10px;font-weight:600;color:rgb(var(--nx-text))"><x-nx.icon name="lock" /> {{ $pk->name }} <small class="ns-small" style="font-weight:400">{{ $pk->last_used_at ? 'used '.$pk->last_used_at->diffForHumans() : 'not used yet' }}</small></span>
                <button type="button" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm" @click="if (confirm('Remove this device?')) window.nxPasskey.remove({{ $pk->id }}).then(r => go(r))">Remove</button>
            </div>
        @empty
            <p class="ns-small" style="margin:0 0 12px">No device added yet.</p>
        @endforelse
        <div x-show="ok" x-cloak style="display:none">
            <label class="ns-lbl" for="pk-name">Name this device</label>
            <input id="pk-name" type="text" x-model="name" maxlength="60" placeholder="My phone" class="ns-input" style="margin-bottom:10px">
            <button type="button" :disabled="busy" @click="busy = true; window.nxPasskey.register(name || 'This device').then(r => go(r))" class="ns-cta ns-cta--pill"><x-nx.icon name="shield-check" /> <span x-text="busy ? 'Waiting for your fingerprint...' : 'Add this device'"></span></button>
        </div>
        <p x-show="!ok" class="ns-small" style="margin:0">This browser or device has no fingerprint, face or screen lock available. Open this site on your phone to add one.</p>
        <div style="margin-top:16px;padding-top:14px;border-top:1px solid rgb(var(--nx-line))" data-app-lock-setting><a href="{{ route('account.app-lock') }}" wire:navigate class="ns-cta ns-cta--pill ns-cta--ghost">App lock settings</a></div>
        <p x-show="msg" x-text="msg" class="ns-small" style="color:rgb(var(--nx-danger,200 40 40));margin-top:8px" role="alert"></p>
    </section>

    {{-- Connected accounts --}}
    <section class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:12px">Connected accounts</h2>
        <div class="ns-pg__head" style="flex-wrap:nowrap;align-items:center">
            <span style="display:flex;align-items:center;gap:10px;font-weight:600;color:rgb(var(--nx-text))">
                {{-- nx:allow:start the Google "G" is Google's own brand artwork --}}
                <img src="{{ asset('images/brand/google/google-g.svg') }}" alt="" width="20" height="20" class="h-5 w-5" aria-hidden="true">
                {{-- nx:allow:end --}}
                Google
            </span>
            @if ($googleLinked)
                <button type="button" wire:click="unlinkGoogle" wire:confirm="Unlink your Google account? You can still sign in with your email and password." class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Unlink</button>
            @elseif (\App\Support\SocialLogin::googleEnabled())
                <a href="{{ route('social.redirect', 'google') }}" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Link</a>
            @else
                <span class="ns-small">Not available</span>
            @endif
        </div>
    </section>

    {{-- Sessions --}}
    @if ($sessions->isNotEmpty())
        <section class="ns-pg__card ns-ring">
            <div class="ns-pg__head" style="align-items:center;margin-bottom:12px">
                <h2 class="ns-pg__h2">Active sessions</h2>
                <button type="button" wire:click="signOutOtherSessions" wire:loading.attr="disabled" wire:target="signOutOtherSessions" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">Sign out other sessions</button>
            </div>
            <ul class="ns-pg__stack ns-pg__stack--sm" style="list-style:none;margin:0;padding:0">
                @foreach ($sessions as $s)
                    <li class="ns-pg__kv" style="font-size:13px;gap:12px">
                        <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $s['ip'] ?: 'unknown IP' }} — {{ \Illuminate\Support\Str::limit($s['agent'], 48) }}</span>
                        <span style="flex:none;{{ $s['current'] ? 'font-weight:700;color:color-mix(in srgb, rgb(var(--nx-ok)) 72%, rgb(var(--nx-text)))' : '' }}">{{ $s['current'] ? 'This device' : $s['last'] }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-nx.page>
</div>
