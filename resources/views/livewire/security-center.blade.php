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

    {{-- Connected accounts --}}
    <section class="ns-pg__card ns-ring">
        <h2 class="ns-pg__h2" style="margin-bottom:12px">Connected accounts</h2>
        <div class="ns-pg__head" style="flex-wrap:nowrap;align-items:center">
            <span style="display:flex;align-items:center;gap:10px;font-weight:600;color:rgb(var(--nx-text))">
                {{-- nx:allow:start the Google "G" is Google's own brand artwork --}}
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.76h3.56c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.56-2.76c-.98.66-2.23 1.05-3.72 1.05-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/></svg>
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
