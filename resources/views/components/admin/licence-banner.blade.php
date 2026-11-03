{{-- Staged enforcement banner for the admin area only (never the public site). grace = friendly countdown, expired/suspended/revoked = firm, unreachable = quiet. --}}
@php
    $st = \App\Support\Activation\Enforcement::stage();
    $firm = in_array($st['stage'], ['expired', 'suspended', 'revoked'], true);
    $route = \Illuminate\Support\Facades\Route::has('admin.license') ? route('admin.license') : null;
@endphp
@if ($st['stage'] !== 'ok' && ! (request()->routeIs('admin.license')))
    <div role="{{ $firm ? 'alert' : 'status' }}" data-licence-banner="{{ $st['stage'] }}" style="margin:0 0 14px;padding:12px 14px;border-radius:14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;background:{{ $firm ? 'rgb(var(--nx-bad) / .12)' : 'rgb(var(--nx-warn) / .14)' }};border:1px solid {{ $firm ? 'rgb(var(--nx-bad))' : 'rgb(var(--nx-warn))' }}">
        <div style="min-width:0;flex:1"><b style="color:rgb(var(--nx-text))">{{ $st['title'] }}</b><span style="display:block;color:rgb(var(--nx-text-2));font-size:.9rem">{{ $st['message'] }}</span></div>
        @if ($route && in_array($st['stage'], ['grace', 'expired', 'revoked'], true))<a href="{{ $route }}" wire:navigate class="ns-cta ns-cta--pill ns-cta--sm" data-licence-banner-cta>{{ $st['stage'] === 'revoked' ? 'Open licence' : 'Activate now' }}</a>@endif
    </div>
@endif
