<div>
    @if ($open)
        <div class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center"
             x-data="{
                 q: '',
                 view: $persist('list').as('nx_collection_view'),
                 favs: $persist([]).as('nx_service_favs'),
                 isFav(s) { return this.favs.includes(s) },
                 toggleFav(s) { this.isFav(s) ? this.favs = this.favs.filter(f => f !== s) : this.favs = [...this.favs, s] },
                 matches(el) { return this.q === '' || el.dataset.name.includes(this.q.toLowerCase().trim()) },
             }"
             @keydown.escape.window="$wire.close()"
             role="dialog" aria-modal="true" aria-label="Choose a service">
            <div class="absolute inset-0 bg-black/60" wire:click="close"></div>

            <div class="relative flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-white/10">
                    <h2 class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white">
                        <x-icon name="grid" class="h-5 w-5" gradient /> {{ $title ?? 'Choose a service' }}
                    </h2>
                    <button type="button" wire:click="close" aria-label="Close"
                            class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="px-5 pt-4">
                    <x-ui.collection-controls placeholder="Search services…" />
                </div>

                {{-- Favourites float to the top via flex order; list vs grid via view. --}}
                <div class="mt-3 flex-1 overflow-y-auto px-3 pb-4">
                    <div class="flex flex-col" :class="view === 'grid' && 'grid grid-cols-3 gap-2 flex-none'">
                        @foreach ($options as $opt)
                            <button type="button"
                                    wire:key="sp-{{ $opt['slug'] }}"
                                    wire:click="pick('{{ $opt['slug'] }}', @js($opt['name']))"
                                    data-name="{{ Str::lower($opt['name']) }}"
                                    x-show="matches($el)"
                                    :style="{ order: isFav('{{ $opt['slug'] }}') ? 0 : 1 }"
                                    x-bind:class="view === 'grid'
                                        ? 'relative flex flex-col items-center gap-1.5 rounded-xl p-3 text-center hover:bg-slate-50 dark:hover:bg-white/5'
                                        : 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-left hover:bg-slate-50 dark:hover:bg-white/5'">
                                <x-service-icon :slug="$opt['slug']" class="h-8 w-8 shrink-0" />
                                <span class="flex-1 truncate text-sm font-medium text-slate-800 dark:text-slate-100"
                                      :class="view === 'grid' && 'flex-none w-full text-xs'">{{ $opt['name'] }}</span>
                                <button type="button" @click.stop="toggleFav('{{ $opt['slug'] }}')"
                                        aria-label="Favourite {{ $opt['name'] }}"
                                        class="shrink-0 rounded-full p-1"
                                        :class="view === 'grid' && 'absolute top-1 right-1'">
                                    <x-icon name="star" class="h-4 w-4"
                                            ::class="isFav('{{ $opt['slug'] }}') ? 'text-accent-dark fill-accent-dark dark:text-accent dark:fill-accent' : 'text-slate-300 dark:text-slate-600'" />
                                </button>
                            </button>
                        @endforeach
                    </div>

                    <p class="hidden px-3 py-8 text-center text-sm text-slate-500 dark:text-slate-400"
                       x-show="q !== '' && ![...$root.querySelectorAll('[data-name]')].some(el => el.dataset.name.includes(q.toLowerCase().trim()))"
                       x-cloak>
                        No service matches “<span x-text="q"></span>”.
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
