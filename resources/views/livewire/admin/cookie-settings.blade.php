{{-- nx:converted --}}
{{-- Admin > Cookies. Skin tokens only; the live preview uses the real banner classes. --}}
<div>
@php
    $card = 'ns-pg__card ns-ring';
    $in = 'ns-input';
    $lb = 'ns-pg__hint';
@endphp
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__head">
        <div>
            <h1 class="ns-h1">Cookies</h1>
            <p class="ns-sub" style="margin:4px 0 0">Consent for visitors on every page: opt-in, reject as easy as accept, logged as proof. Policy version <b>{{ $version }}</b>.</p>
        </div>
        <a href="{{ route('cookie-preferences') }}" target="_blank" rel="noopener" class="ns-cta ns-cta--pill ns-cta--ghost">View preferences page</a>
    </div>

    <div class="ns-pg__stats" data-cookie-stats>
        <div class="ns-pg__stat ns-ring"><small>Choices, last 30 days</small><b>{{ number_format($stats['total']) }}</b></div>
        <div class="ns-pg__stat ns-ring"><small>Allow analytics</small><b>{{ $stats['analytics'] }}%</b></div>
        <div class="ns-pg__stat ns-ring"><small>Allow marketing</small><b>{{ $stats['marketing'] }}%</b></div>
        <div class="ns-pg__stat ns-ring"><small>Allow functional</small><b>{{ $stats['functional'] }}%</b></div>
    </div>

    <form wire:submit="save" style="display:grid;gap:16px;margin-top:16px">
        <div class="{{ $card }}">
            <h2 class="ns-pg__h2">Banner</h2>
            <label style="display:flex;gap:8px;align-items:center;color:rgb(var(--nx-text));margin-bottom:10px"><input type="checkbox" wire:model.live="enabled"> Show the cookie banner and wait for consent before analytics and marketing run</label>
            @unless ($enabled)<p class="ns-pg__fielderr" role="alert">Off means tracking runs for everyone without asking. Only do that where no consent law applies to your visitors.</p>@endunless
            <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                <div style="grid-column:1/-1"><label class="{{ $lb }}" for="ck-t">Title</label><input id="ck-t" type="text" wire:model.live.debounce.300ms="title" maxlength="80" class="{{ $in }}">@error('title')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div style="grid-column:1/-1"><label class="{{ $lb }}" for="ck-b">Message</label><textarea id="ck-b" rows="3" wire:model.live.debounce.300ms="body" maxlength="400" class="{{ $in }}"></textarea>@error('body')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                <div><label class="{{ $lb }}" for="ck-a">Accept button</label><input id="ck-a" type="text" wire:model.live.debounce.300ms="accept" maxlength="24" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}" for="ck-r">Reject button</label><input id="ck-r" type="text" wire:model.live.debounce.300ms="reject" maxlength="24" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}" for="ck-c">Choose button</label><input id="ck-c" type="text" wire:model.live.debounce.300ms="customise" maxlength="24" class="{{ $in }}"></div>
            </div>
            <p class="ns-pg__hint" style="margin:8px 0 0">The Reject button always sits on the first screen, the same size as Accept. That is deliberate and cannot be hidden.</p>
        </div>

        <div class="{{ $card }}">
            <h2 class="ns-pg__h2">Look</h2>
            <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                <div><label class="{{ $lb }}" for="ck-l">Layout</label><select id="ck-l" wire:model.live="layout" class="{{ $in }}">@foreach (\App\Support\CookieConsent\CookieConsent::LAYOUTS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                @if ($layout === 'card')<div><label class="{{ $lb }}" for="ck-p">Corner</label><select id="ck-p" wire:model.live="position" class="{{ $in }}"><option value="right">Right</option><option value="left">Left</option></select></div>@endif
                <div><label class="{{ $lb }}" for="ck-th">Colours</label><select id="ck-th" wire:model.live="theme" class="{{ $in }}">@foreach (\App\Support\CookieConsent\CookieConsent::THEMES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            </div>
            @if ($theme === 'custom')
                <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(9rem,1fr));margin-top:10px">
                    @foreach (['bg' => 'Background', 'text' => 'Text', 'button' => 'Button', 'button_text' => 'Button text'] as $k => $label)
                        <div><label class="{{ $lb }}" for="ck-col-{{ $k }}">{{ $label }}</label><input id="ck-col-{{ $k }}" type="color" wire:model.live="custom.{{ $k }}" style="width:100%;height:44px;border-radius:10px">@error('custom.'.$k)<p class="ns-pg__fielderr">{{ $message }}</p>@enderror</div>
                    @endforeach
                </div>
            @endif

            <div class="ck-admin-preview" style="margin-top:14px;padding:18px;border-radius:16px;background:rgb(var(--nx-surface-2));box-shadow:inset 0 0 0 1px rgb(var(--nx-line))" data-cookie-preview aria-label="Preview">
                <div class="ck ck--{{ $layout === 'modal' ? 'card' : $layout }} ck-theme-{{ $theme }}" style="{{ $previewVars }}position:relative;inset:auto;padding:0;pointer-events:none">
                    <div class="ck__box" style="margin-inline:{{ $layout === 'card' ? 'auto' : 'auto' }}">
                        <div class="ck__main">
                            <div><div class="ck__head"><span class="ck__ic" aria-hidden="true"><x-nx.icon name="shield-check" /></span><p class="ck__title">{{ $title }}</p></div>
                            <p class="ck__body" style="{{ $layout === 'bar' ? 'margin-bottom:0' : '' }}">{{ $body }} <span class="ck__link">Cookie details</span></p></div>
                            <div class="ck__actions"><span class="ck__btn ck__btn--solid" style="display:inline-grid;place-items:center">{{ $accept }}</span><span class="ck__btn ck__btn--solid ck__btn--alt" style="display:inline-grid;place-items:center">{{ $reject }}</span><span class="ck__btn ck__btn--ghost" style="display:inline-grid;place-items:center">{{ $customise }}</span></div>
                        </div>
                    </div>
                </div>
                @if ($layout === 'modal')<p class="ns-pg__hint" style="margin:8px 0 0">Centre dialog: on the site it opens in the middle and dims the page.</p>@endif
            </div>
        </div>

        <div class="{{ $card }}">
            <h2 class="ns-pg__h2">Behaviour</h2>
            <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))">
                <div><label class="{{ $lb }}" for="ck-rd">Ask again after (days)</label><input id="ck-rd" type="number" min="30" max="395" wire:model="renewDays" class="{{ $in }}">@error('renewDays')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror<p class="ns-pg__hint" style="margin:4px 0 0">Regulators suggest about 6 months (180).</p></div>
                <div><label class="{{ $lb }}" for="ck-rt">Keep the consent log (months)</label><input id="ck-rt" type="number" min="6" max="60" wire:model="retentionMonths" class="{{ $in }}">@error('retentionMonths')<p class="ns-pg__fielderr">{{ $message }}</p>@enderror<p class="ns-pg__hint" style="margin:4px 0 0">Older records are deleted daily.</p></div>
            </div>
            <label style="display:flex;gap:8px;align-items:center;color:rgb(var(--nx-text));margin-top:12px"><input type="checkbox" wire:model="respectGpc"> Treat a browser's Global Privacy Control signal as "reject all"</label>
            <label style="display:flex;gap:8px;align-items:center;color:rgb(var(--nx-text));margin-top:8px"><input type="checkbox" wire:model="showReopen"> Show the small "Cookie settings" button on every page once someone has chosen</label>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="submit" class="ns-cta ns-cta--pill" wire:loading.attr="disabled" wire:target="save">Save</button>
            <button type="button" class="ns-cta ns-cta--pill ns-cta--ghost" wire:click="resetDesign" wire:confirm="Put the wording and look back to the defaults?">Reset wording and look</button>
        </div>
    </form>

    <div class="{{ $card }}" style="margin-top:16px">
        <h2 class="ns-pg__h2">What this controls</h2>
        <p class="ns-pg__hint" style="margin:0 0 8px">Only the services switched on elsewhere are listed (add a Google Analytics or Meta Pixel ID in Admin > Tracking and it appears here and is blocked until someone agrees).</p>
        <ul style="list-style:none;margin:0;padding:0;display:grid;gap:8px">
            @foreach ($services as $svc)
                <li style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><span><b style="color:rgb(var(--nx-text))">{{ $svc['name'] }}</b> <span class="ns-pg__hint">{{ $svc['provider'] }}</span></span><span class="ns-pg__hint">{{ $categories[$svc['category']]['label'] }}</span></li>
            @endforeach
        </ul>
        <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <button type="button" class="ns-cta ns-cta--pill ns-cta--ghost" wire:click="askAgain" wire:confirm="Ask every visitor for their choice again?">Ask everyone again</button>
            <span class="ns-pg__hint">Use after you add a new service or change your policy.</span>
        </div>
    </div>

    <div class="{{ $card }}" style="margin-top:16px">
        <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
            <h2 class="ns-pg__h2" style="margin:0">Consent log</h2>
            <div style="display:flex;gap:8px;align-items:center">
                <select wire:model.live="filter" class="{{ $in }}" style="width:auto" aria-label="Filter by choice"><option value="all">All choices</option>@foreach (['accept_all' => 'Accept all', 'reject_all' => 'Reject all', 'custom' => 'Custom', 'withdraw' => 'Withdrawn', 'gpc' => 'Browser signal'] as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                <a href="{{ route('admin.cookies.export') }}" class="ns-cta ns-cta--pill ns-cta--ghost">Export CSV</a>
            </div>
        </div>
        <div style="overflow:auto;margin-top:10px">
            <table class="ns-table" style="width:100%;font-size:13px" data-cookie-log>
                <thead><tr><th align="left">When</th><th align="left">Choice</th><th align="left">Functional</th><th align="left">Analytics</th><th align="left">Marketing</th><th align="left">Version</th><th align="left">Page</th></tr></thead>
                <tbody>
                @forelse ($logs as $l)
                    <tr><td>{{ $l->created_at->format('M j, H:i') }}</td><td>{{ str_replace('_', ' ', $l->action) }}</td><td>{{ $l->functional ? 'Yes' : 'No' }}</td><td>{{ $l->analytics ? 'Yes' : 'No' }}</td><td>{{ $l->marketing ? 'Yes' : 'No' }}</td><td>{{ $l->policy_version }}</td><td>{{ $l->path }}</td></tr>
                @empty
                    <tr><td colspan="7" class="ns-pg__hint">No choices recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-nx.page>
</div>
