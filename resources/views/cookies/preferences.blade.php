{{-- nx:converted --}}
{{-- Cookie preferences: every category and service in plain words, the visitor's current choice, and the controls to change it. Opt-in; reject is as easy as accept. --}}
<x-layouts.marketing :title="'Cookie preferences — '.\App\Support\BrandSettings::name()">
    <article class="mx-auto max-w-3xl px-4 py-16" data-pref-root data-cookie-version="{{ $version }}" data-renew-days="{{ $design['renew_days'] }}">
        <span class="ns-pg__kicker">Privacy</span>
        <h1 class="ns-h1" style="margin-top:6px">Cookie preferences</h1>
        <p class="ns-sub" style="margin:8px 0 0">Cookies are small files a website saves in your browser. The strictly necessary ones keep {{ \App\Support\BrandSettings::name() }} working. Everything else is off until you say yes, and you can change your mind here at any time. Saying no never stops you using the site.</p>

        <div class="ns-pg__card ns-ring" style="margin-top:20px" data-pref-status role="status" aria-live="polite">
            <b style="color:rgb(var(--nx-text))" data-pref-status-title>Checking your choice&hellip;</b>
            <p class="ns-pg__hint" style="margin:4px 0 0" data-pref-status-text></p>
        </div>

        <div style="display:grid;gap:14px;margin-top:20px">
            @foreach ($inUse as $cat)
                @php($meta = $categories[$cat])
                <section class="ns-pg__card ns-ring" aria-labelledby="pref-{{ $cat }}">
                    <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start">
                        <div>
                            <h2 id="pref-{{ $cat }}" class="ns-pg__h2" style="margin:0">{{ $meta['label'] }}</h2>
                            <p class="ns-pg__hint" style="margin:4px 0 0">{{ $meta['description'] }}</p>
                        </div>
                        @if (! empty($meta['locked']))
                            <span class="ns-pg__hint" style="white-space:nowrap;font-weight:700">Always on</span>
                        @else
                            <label class="ck__switch" style="--_btn:rgb(var(--nx-teal));--_tx:rgb(var(--nx-text))"><input type="checkbox" data-pref-cat="{{ $cat }}" aria-labelledby="pref-{{ $cat }}"><span class="ck__track" aria-hidden="true"></span></label>
                        @endif
                    </div>
                    @php($list = collect($services)->where('category', $cat))
                    @if ($list->isNotEmpty())
                        <div style="margin-top:12px;display:grid;gap:10px">
                            @foreach ($list as $svc)
                                <div style="border-top:1px solid rgb(var(--nx-line));padding-top:10px">
                                    <b style="color:rgb(var(--nx-text))">{{ $svc['name'] }}</b> <span class="ns-pg__hint">by {{ $svc['provider'] }}</span>
                                    <p class="ns-pg__hint" style="margin:2px 0 6px">{{ $svc['purpose'] }}</p>
                                    <ul class="ns-pg__hint" style="margin:0;padding-inline-start:18px">
                                        @foreach ($svc['cookies'] as [$cname, $cdur])<li><code>{{ $cname }}</code>: {{ $cdur }}</li>@endforeach
                                    </ul>
                                    @if ($svc['privacy'])<a href="{{ $svc['privacy'] }}" target="_blank" rel="noopener noreferrer" class="ns-pg__hint" style="text-decoration:underline">Their privacy policy</a>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:20px">
            <button type="button" class="ns-cta ns-cta--pill" data-pref-save>Save my choices</button>
            <button type="button" class="ns-cta ns-cta--pill ns-cta--ghost" data-pref-accept>{{ $design['accept'] }}</button>
            <button type="button" class="ns-cta ns-cta--pill ns-cta--ghost" data-pref-reject>{{ $design['reject'] }}</button>
        </div>

        <p class="ns-pg__hint" style="margin-top:22px">Your choice is saved in a cookie on this device for {{ $design['renew_days'] }} days, then we ask again. We also keep a record of each choice (a random reference, the time, what you chose, and a scrambled form of your connection address, never the address itself) so we can show it was respected. @if ($design['respect_gpc'])If your browser sends the Global Privacy Control signal we treat it as &ldquo;reject all&rdquo;.@endif See our <a href="{{ route('legal.show', 'privacy') }}" style="text-decoration:underline">Privacy Policy</a> and <a href="{{ route('legal.show', 'cookies') }}" style="text-decoration:underline">Cookie Policy</a>.</p>
    </article>
</x-layouts.marketing>
