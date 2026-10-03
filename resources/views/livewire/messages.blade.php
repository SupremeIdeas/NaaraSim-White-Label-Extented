{{-- Messages: three resizable panels (conversations + contacts | thread | contact info). Skin-aware: ns-* hooks and tokens only.
     Desktop: drag the grips (or focus one and use the arrow keys); widths are remembered in this browser. Phone: one pane at a time. --}}
<div>{{-- single Livewire root: x-nx.page opens with a conditional, which would otherwise take the wire attributes --}}
<x-nx.page
    x-data="{
        l: Number(localStorage.getItem('nx_msg_l')) || 310, r: Number(localStorage.getItem('nx_msg_r')) || 280,
        info: localStorage.getItem('nx_msg_info') === null ? window.innerWidth >= 1500 : localStorage.getItem('nx_msg_info') === '1', pane: '{{ $active ? 'thread' : 'list' }}',
        clamp(v, lo, hi) { return Math.min(Math.max(lo, hi), Math.max(lo, v)); },
        room(side) { const w = this.$el.querySelector('.ns-msg').clientWidth; return w - 24 - 340 - (side === 'l' ? (this.info ? this.r : 0) : this.l); },
        save() { try { localStorage.setItem('nx_msg_l', this.l); localStorage.setItem('nx_msg_r', this.r); localStorage.setItem('nx_msg_info', this.info ? '1' : '0'); } catch (e) {} },
        drag(e, side) {
            const sign = document.dir === 'rtl' ? -1 : 1, x0 = e.clientX, w0 = side === 'l' ? this.l : this.r;
            const move = (ev) => { const dx = (ev.clientX - x0) * sign; if (side === 'l') this.l = this.clamp(w0 + dx, 240, Math.min(520, this.room('l'))); else this.r = this.clamp(w0 - dx, 240, Math.min(460, this.room('r'))); };
            const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); document.body.classList.remove('ns-msg-dragging'); this.save(); };
            document.body.classList.add('ns-msg-dragging'); window.addEventListener('pointermove', move); window.addEventListener('pointerup', up);
        },
        nudge(side, d) { if (side === 'l') this.l = this.clamp(this.l + d, 240, Math.min(520, this.room('l'))); else this.r = this.clamp(this.r - d, 240, Math.min(460, this.room('r'))); this.save(); },
    }"
    x-init="$wire.$watch('active', v => { pane = v ? 'thread' : 'list' })" class="ns-msg-page">

    <div class="ns-msg" :class="[info ? '' : 'ns-msg--noinfo', 'ns-msg--' + pane]" :style="{ '--msg-l': l + 'px', '--msg-r': r + 'px' }">

        {{-- ============ LEFT: conversations / contacts ============ --}}
        <section class="ns-msg__panel ns-msg__list" aria-label="{{ __('messages.conversations') }}">
            <header class="ns-msg__head">
                <h1 class="ns-h1" style="font-size:22px">{{ __('messages.title') }}</h1>
                <button type="button" class="ns-msg__iconbtn" wire:click="$dispatch('open-send-message', { to: '', name: '' })" aria-label="{{ __('messages.new') }}" title="{{ __('messages.new') }}"><x-nx.icon name="send" /></button>
            </header>
            <div class="ns-seg" role="tablist" style="margin:0 14px">
                <button type="button" role="tab" wire:click="$set('tab','chats')" :aria-selected="'{{ $tab }}' === 'chats'" class="{{ $tab === 'chats' ? 'is-on' : '' }}">{{ __('messages.chats') }}</button>
                <button type="button" role="tab" wire:click="$set('tab','contacts')" :aria-selected="'{{ $tab }}' === 'contacts'" class="{{ $tab === 'contacts' ? 'is-on' : '' }}">{{ __('messages.contacts') }}</button>
            </div>
            <label class="ns-search" style="margin:10px 14px 6px;height:44px">
                <x-nx.icon name="search" />
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('messages.search') }}" aria-label="{{ __('messages.search') }}">
            </label>

            <div class="ns-msg__scroll">
                @if ($tab === 'chats')
                    @forelse ($threads as $thread)
                        @php($nm = $names[$thread->counterpart_number] ?? null)
                        <button type="button" wire:key="thread-{{ $thread->id }}" wire:click="openThread('{{ $thread->counterpart_number }}')" @click="pane = 'thread'"
                                @class(['ns-msg__row', 'is-on' => $active === $thread->counterpart_number])>
                            <span class="ns-msg__avatar" aria-hidden="true">{{ $nm ? mb_strtoupper(mb_substr($nm, 0, 1)) : '#' }}</span>
                            <span class="ns-msg__main">
                                <span class="ns-msg__line"><b>{{ $nm ?: $thread->counterpart_number }}</b><small>{{ optional($thread->last_at)->diffForHumans(null, true) }}</small></span>
                                <span class="ns-msg__line">
                                    <span class="ns-msg__preview">@if ($thread->last_direction === 'out')<i>{{ __('messages.you') }}:</i> @endif{{ $thread->last_body }}</span>
                                    @if ($thread->unread_count > 0)<em class="ns-msg__badge">{{ $thread->unread_count }}</em>@endif
                                </span>
                            </span>
                        </button>
                    @empty
                        <x-nx.empty :title="__('messages.empty_title')" :text="\App\Support\BrandSettings::rebrand(__('messages.empty_text'))" style="margin:14px" />
                    @endforelse
                @else
                    @forelse ($contacts as $c)
                        <button type="button" wire:key="contact-{{ $c->id }}" wire:click="startWith('{{ $c->phone_number }}')" @click="pane = 'thread'" class="ns-msg__row">
                            <span class="ns-msg__avatar" aria-hidden="true">{{ $c->initials() }}</span>
                            <span class="ns-msg__main">
                                <span class="ns-msg__line"><b>{{ $c->name }}</b>@if ($c->is_favorite)<x-nx.icon name="star" class="ns-msg__fav" />@endif</span>
                                <span class="ns-msg__line"><span class="ns-msg__preview">{{ $c->phone_number }}</span></span>
                            </span>
                            <span class="ns-msg__go" aria-hidden="true"><x-nx.icon name="send" /></span>
                        </button>
                    @empty
                        <x-nx.empty :title="__('messages.no_contacts')" :text="__('messages.no_contacts_text')" style="margin:14px">
                            <a href="{{ route('numbers.contacts') }}" wire:navigate class="ns-btn" style="margin-top:12px">{{ __('messages.open_contacts') }}</a>
                        </x-nx.empty>
                    @endforelse
                @endif
            </div>
        </section>

        <div class="ns-msg__grip" role="separator" aria-orientation="vertical" aria-label="{{ __('messages.resize_list') }}" tabindex="0"
             @pointerdown.prevent="drag($event, 'l')" @keydown.arrow-left.prevent="nudge('l', -16)" @keydown.arrow-right.prevent="nudge('l', 16)" :aria-valuenow="l"><i></i></div>

        {{-- ============ CENTRE: thread ============ --}}
        <section class="ns-msg__panel ns-msg__thread" aria-label="{{ __('messages.thread') }}">
            @if ($active)
                @php($activeName = $names[$active] ?? null)
                <header class="ns-msg__head ns-msg__head--thread">
                    <button type="button" class="ns-msg__iconbtn ns-msg__back" wire:click="closeThread" @click="pane = 'list'" aria-label="{{ __('messages.back') }}"><x-nx.icon name="chevron-left" /></button>
                    <button type="button" class="ns-msg__who" @click="info = !info; pane = window.innerWidth < 1024 ? 'info' : pane; save()" :aria-expanded="info">
                        <span class="ns-msg__avatar" aria-hidden="true">{{ $activeName ? mb_strtoupper(mb_substr($activeName, 0, 1)) : '#' }}</span>
                        <span><b>{{ $activeName ?: $active }}</b>@if ($activeName)<small>{{ $active }}</small>@endif</span>
                    </button>
                    <a href="{{ route('numbers.dialer', ['to' => $active]) }}" wire:navigate class="ns-msg__iconbtn" aria-label="{{ __('messages.call') }}"><x-nx.icon name="phone" /></a>
                    <button type="button" class="ns-msg__iconbtn ns-msg__infobtn" @click="info = !info; save()" :class="info ? 'is-on' : ''" aria-label="{{ __('messages.info') }}"><x-nx.icon name="info" /></button>
                </header>

                <div class="ns-msg__scroll ns-msg__timeline" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                    @forelse ($timeline as $m)
                        <div class="ns-msg__bubblerow {{ $m->direction === 'out' ? 'is-out' : '' }}" wire:key="m-{{ $loop->index }}">
                            <div class="ns-bubble {{ $m->direction === 'out' ? 'ns-bubble--user' : 'ns-bubble--bot' }} ns-msg__bubble">
                                @if ($m->attachment_url && $m->is_voicemail)
                                    <audio controls preload="none" src="{{ $m->attachment_url }}" style="width:100%;max-width:230px;margin-bottom:4px"></audio>
                                @elseif ($m->attachment_url)
                                    <img src="{{ $m->attachment_url }}" alt="{{ __('messages.attachment') }}" style="max-height:170px;border-radius:10px;margin-bottom:4px">
                                @endif
                                <p style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $m->body }}</p>
                                <small class="ns-msg__time">{{ optional($m->at)->format('M j, g:i a') }}</small>
                            </div>
                        </div>
                    @empty
                        <x-nx.empty :title="__('messages.thread_empty_title')" :text="__('messages.thread_empty_text')" style="margin:auto 18px" />
                    @endforelse
                </div>

                {{-- The reply bar opens the existing send modal (its composer, billing and provider routing are unchanged). --}}
                <footer class="ns-msg__reply">
                    <button type="button" class="ns-msg__replybar" wire:click="$dispatch('open-send-message', { to: '{{ $active }}', name: '{{ $activeName ? e($activeName) : '' }}' })">
                        <span>{{ __('messages.type_message') }}</span><x-nx.icon name="send" />
                    </button>
                </footer>
            @else
                <x-nx.empty :title="__('messages.select_title')" :text="__('messages.select_text')" style="margin:auto 22px" />
            @endif
        </section>

        <div class="ns-msg__grip ns-msg__grip--r" role="separator" aria-orientation="vertical" aria-label="{{ __('messages.resize_info') }}" tabindex="0" x-show="info"
             @pointerdown.prevent="drag($event, 'r')" @keydown.arrow-left.prevent="nudge('r', -16)" @keydown.arrow-right.prevent="nudge('r', 16)" :aria-valuenow="r"><i></i></div>

        {{-- ============ RIGHT: contact info ============ --}}
        <aside class="ns-msg__panel ns-msg__info" x-show="info" aria-label="{{ __('messages.info') }}">
            @if ($active && $info)
                @php($c = $info['contact'])
                <header class="ns-msg__head"><b>{{ __('messages.info') }}</b>
                    <button type="button" class="ns-msg__iconbtn" @click="info = false; pane = 'thread'; save()" aria-label="{{ __('messages.close_info') }}"><x-nx.icon name="x" /></button>
                </header>
                <div class="ns-msg__scroll" style="padding:6px 16px 18px">
                    <div class="ns-msg__hero">
                        <span class="ns-msg__avatar ns-msg__avatar--xl" aria-hidden="true">{{ $c ? $c->initials() : '#' }}</span>
                        <b>{{ $c?->name ?: $active }}</b>
                        @if ($c)<small>{{ $active }}</small>@endif
                    </div>
                    <div class="ns-msg__actions">
                        <a href="{{ route('numbers.dialer', ['to' => $active]) }}" wire:navigate class="ns-btn"><x-nx.icon name="phone" /> {{ __('messages.call') }}</a>
                        <button type="button" class="ns-btn" wire:click="$dispatch('open-send-message', { to: '{{ $active }}', name: '{{ $c ? e($c->name) : '' }}' })"><x-nx.icon name="send" /> {{ __('messages.message') }}</button>
                    </div>
                    @unless ($c)
                        <x-nx.note variant="link" icon="plus" tag="a" :href="route('numbers.contacts')" wire:navigate style="margin-top:14px"><b>{{ __('messages.save_contact') }}</b>{{ __('messages.save_contact_text') }}</x-nx.note>
                    @endunless
                    <dl class="ns-msg__facts">
                        <div><dt>{{ __('messages.number') }}</dt><dd>{{ $active }}</dd></div>
                        <div><dt>{{ __('messages.received') }}</dt><dd>{{ $info['incoming'] }}</dd></div>
                        <div><dt>{{ __('messages.sent') }}</dt><dd>{{ $info['outgoing'] }}</dd></div>
                        @if ($info['first'])<div><dt>{{ __('messages.first') }}</dt><dd>{{ $info['first']->format('M j, Y') }}</dd></div>@endif
                        @if ($info['last'])<div><dt>{{ __('messages.last') }}</dt><dd>{{ $info['last']->format('M j, Y') }}</dd></div>@endif
                    </dl>
                </div>
            @else
                <x-nx.empty :title="__('messages.info')" :text="__('messages.info_empty')" style="margin:auto 18px" />
            @endif
        </aside>
    </div>

    {{-- Reply composer (reuses the existing send modal). --}}
    @livewire('send-message')
</x-nx.page>
</div>
