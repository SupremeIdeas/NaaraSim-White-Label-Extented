{{-- nx:converted --}}
{{-- Cookie consent banner (generic platform feature). Opt-in, "Reject all" beside "Accept all" with equal weight, granular choices, always re-openable. Hidden until
     resources/js/cookie-consent.js decides it is needed (no valid choice yet, an expired one, or a newer policy version). Colours: the site skin, or a fixed theme set in Admin > Cookies. --}}
@php
    $ckd = \App\Support\CookieConsent\CookieConsent::design();
    $ckCats = (array) config('cookie_consent.categories');
    $ckUse = \App\Support\CookieConsent\CookieConsent::categoriesInUse();
    $ckServices = \App\Support\CookieConsent\CookieConsent::services();
@endphp
@if (\App\Support\CookieConsent\CookieConsent::enabled() && \App\Support\CookieConsent\CookieConsent::hasOptional())
<div id="naara-cookie" class="ck ck--{{ $ckd['layout'] }} ck--{{ $ckd['position'] }} ck-theme-{{ $ckd['theme'] }}" style="{{ \App\Support\CookieConsent\CookieConsent::cssVars($ckd) }}" hidden
     data-cookie-root data-cookie-name="{{ \App\Support\CookieConsent\CookieConsent::cookieName() }}" data-version="{{ \App\Support\CookieConsent\CookieConsent::version() }}" data-renew-days="{{ $ckd['renew_days'] }}"
     data-gpc="{{ $ckd['respect_gpc'] ? '1' : '0' }}" data-endpoint="{{ route('cookie-consent.store') }}" data-cats="{{ implode(',', array_values(array_intersect($ckUse, ['functional', 'analytics', 'marketing']))) }}">
    @if ($ckd['layout'] === 'modal')<div class="ck__scrim" data-cookie-close-scrim></div>@endif
    <section class="ck__box" role="dialog" aria-modal="{{ $ckd['layout'] === 'modal' ? 'true' : 'false' }}" aria-labelledby="ck-title" aria-describedby="ck-body">
        <div class="ck__main" data-cookie-main>
            <div class="ck__head">
                <span class="ck__ic" aria-hidden="true"><x-nx.icon name="shield-check" /></span>
                <h2 id="ck-title" class="ck__title">{{ $ckd['title'] }}</h2>
            </div>
            <p id="ck-body" class="ck__body">{{ $ckd['body'] }} <a href="{{ route('cookie-preferences') }}" class="ck__link">Cookie details</a></p>
            <div class="ck__actions">
                <button type="button" class="ck__btn ck__btn--solid" data-cookie-accept>{{ $ckd['accept'] }}</button>
                <button type="button" class="ck__btn ck__btn--solid ck__btn--alt" data-cookie-reject>{{ $ckd['reject'] }}</button>
                <button type="button" class="ck__btn ck__btn--ghost" data-cookie-customise aria-expanded="false" aria-controls="ck-panel">{{ $ckd['customise'] }}</button>
            </div>
        </div>
        <div id="ck-panel" class="ck__panel" data-cookie-panel hidden>
            <h2 class="ck__title">Choose what you allow</h2>
            <ul class="ck__cats">
                @foreach ($ckUse as $cat)
                    @php($meta = $ckCats[$cat])
                    <li class="ck__cat">
                        <div class="ck__cat-top">
                            <div><b>{{ $meta['label'] }}</b><p>{{ $meta['description'] }}</p></div>
                            @if (! empty($meta['locked']))
                                <span class="ck__always">Always on</span>
                            @else
                                <label class="ck__switch"><input type="checkbox" data-cookie-cat="{{ $cat }}" aria-label="Allow {{ strtolower($meta['label']) }} cookies"><span class="ck__track" aria-hidden="true"></span></label>
                            @endif
                        </div>
                        @php($ckNames = collect($ckServices)->where('category', $cat)->pluck('name')->implode(', '))
                        @if ($ckNames !== '')<p class="ck__svcs">{{ $ckNames }}</p>@endif
                    </li>
                @endforeach
            </ul>
            <div class="ck__actions">
                <button type="button" class="ck__btn ck__btn--solid" data-cookie-save>Save my choices</button>
                <button type="button" class="ck__btn ck__btn--solid ck__btn--alt" data-cookie-reject>{{ $ckd['reject'] }}</button>
                <button type="button" class="ck__btn ck__btn--ghost" data-cookie-back>Back</button>
            </div>
        </div>
    </section>
</div>
@if ($ckd['show_reopen'])
    <button type="button" class="ck-reopen" data-cookie-settings aria-label="Cookie settings" title="Cookie settings" hidden><x-nx.icon name="shield-check" /></button>
@endif
@endif
