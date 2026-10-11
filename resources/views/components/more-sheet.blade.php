{{-- Shared "More" sheet (extracted from app-shell.blade.php, behavior-
     preserving): the same bottom sheet the main dashboard's mobile "More"
     button and the Numbers section header's grid-icon button both open.
     Requires an ancestor Alpine scope with `moreOpen` (bool) and
     `moreLayout` ('grid'|'list') — app-shell.blade.php's root x-data
     already provides these; any other shell (e.g. the Help Center layout)
     must declare the same two properties before rendering this. --}}
@props(['more' => [], 'promo' => false])
@php($isActive = fn ($route) => request()->routeIs($route))
{{-- Skin-system More sheet (Prompt 20 §4.6): the wireframe's sheet + list/grid rows, same markup in every skin. Solid, no blur. --}}
<div x-show="moreOpen" x-cloak class="fixed inset-0 z-[60] lg:hidden" style="display:none;" role="dialog" aria-modal="true" aria-label="More">
    {{-- HOTFIX §6: no backdrop-blur here — animating blur alongside the sheet's slide-up transform causes GPU-compositing
         artifacts on many Android builds. The dim alone is enough; the sheet is already opaque. --}}
    <div x-show="moreOpen" x-transition.opacity @click="moreOpen = false" class="ns-scrim"></div>
    <div x-show="moreOpen"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
         class="ns-sheet ns-sheet--short ns-more" style="-webkit-overflow-scrolling: touch;">
        <div class="ns-handle"></div>
        <div class="ns-sheet__head">
            <div class="ns-text"><b>More</b></div>
            {{-- Grid / list display toggle, persisted per-user (BUILD-3 §6.7). --}}
            <span class="ns-seg ns-small" role="group" aria-label="Layout">
                <button type="button" @click="moreLayout = 'grid'" aria-label="Grid view" :class="moreLayout === 'grid' ? 'is-on' : ''" :aria-pressed="moreLayout === 'grid'"><x-nx.icon name="grid" /></button>
                <button type="button" @click="moreLayout = 'list'" aria-label="List view" :class="moreLayout === 'list' ? 'is-on' : ''" :aria-pressed="moreLayout === 'list'"><x-nx.icon name="list" /></button>
            </span>
            <button type="button" class="ns-sheet__close" style="display:block" @click="moreOpen = false" aria-label="Close"><x-nx.icon name="x" /></button>
        </div>

        <div class="ns-sheet__body">
            @if ($promo)
                @php($menuBanners = \App\Support\Banners::for('menu_sheet'))
                @if ($menuBanners->isNotEmpty())
                    <x-banner-zone placement="menu_sheet" class="mb-4" />
                @else
                    {{-- nx:allow:start Default promo (Module 32 pick, rebuilt on brand): fixed brand artwork, shown until the admin publishes a banner for this zone. --}}
                    <div class="nx-float-card mb-4" aria-hidden="true">
                        <span class="nx-float-card__light"></span>
                        <span class="nx-float-card__ring"></span>
                        <div class="relative">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-accent">{{ \App\Support\BrandSettings::name() }}</p>
                            <p class="mt-1.5 font-display text-lg font-bold leading-snug text-white">Stay Connected. No&nbsp;Borders. No&nbsp;Swaps.</p>
                            <p class="mt-1 text-xs text-slate-300">eSIM data + numbers for 190+ countries, in one wallet.</p>
                        </div>
                    </div>
                    {{-- nx:allow:end --}}
                @endif
            @endif


            {{-- Grid (4-col tiles) or list (stacked rows), per the §6.7 toggle. The active row is a surface step plus a left edge
                 (is-on), never a colour flood; hierarchy comes from the tokens, so every skin restyles it. --}}
            <div class="ns-list" :class="moreLayout === 'grid' ? 'ns-list--grid' : ''">
                @foreach ($more as $item)
                    @continue(! empty($item['heading'])) {{-- headings are desktop-sidebar only --}}
                    <a href="{{ route($item['route']) }}" wire:navigate @click="moreOpen = false"
                       @class(['ns-list__row', 'is-on' => $isActive($item['route'])]) @if ($isActive($item['route'])) aria-current="page" @endif>
                        <span class="ns-tile"><x-nx.icon :name="$item['icon']" /></span>
                        <span>{{ $item['label'] }}</span>
                        <x-nx.icon name="chevron-right" class="ns-chevron" />
                    </a>
                @endforeach
            </div>

            <form method="POST" action="{{ route('logout') }}" class="ns-more__logout">
                @csrf
                <button type="submit" class="ns-btn" style="width:100%;justify-content:center;height:46px"><x-nx.icon name="log-out" /> Sign out</button>
            </form>
        </div>
    </div>
</div>
