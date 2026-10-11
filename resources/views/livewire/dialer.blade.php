@php
    // iOS-style keypad: digit + the letters printed beneath it. '0' long-presses
    // to '+' (handled in Alpine below). '*' / '#' carry no letters.
    $keypad = [
        ['d' => '1', 'l' => ''],       ['d' => '2', 'l' => 'A B C'],  ['d' => '3', 'l' => 'D E F'],
        ['d' => '4', 'l' => 'G H I'],  ['d' => '5', 'l' => 'J K L'],  ['d' => '6', 'l' => 'M N O'],
        ['d' => '7', 'l' => 'P Q R S'],['d' => '8', 'l' => 'T U V'],  ['d' => '9', 'l' => 'W X Y Z'],
        ['d' => '*', 'l' => ''],       ['d' => '0', 'l' => '+'],      ['d' => '#', 'l' => ''],
    ];
@endphp

<div data-dialer data-token-url="{{ route('voice.token') }}">
<x-nx.page class="ns-dl">
    <h1 class="ns-h1" style="margin-top:6px">Call abroad</h1>
    <p class="ns-sub">
        Dial any international number straight from your browser: no app, no second phone.
        You're charged per minute from your wallet, and unused minutes come straight back.
    </p>

    {{-- Nav: back to Numbers, and the contact book. --}}
    <div class="ns-dl__nav">
        <a href="{{ route('numbers') }}" wire:navigate class="ns-linkink"><x-nx.icon name="left" /> Back to Numbers</a>
        <a href="{{ route('numbers.contacts') }}" wire:navigate class="ns-linkink"><x-nx.icon name="idcard" /> Contacts</a>
    </div>

    {{-- Desktop two-column: the keypad card on the left, the contacts + recent-calls rail on the right. One column on a phone. --}}
    <div class="ns-dl__grid">
    <div>{{-- left column: dial card --}}
    <div class="ns-dl__card ns-ring"
         x-data="{
            hold: null, held: false,
            cc: @js($defaultCountry), ccOpen: false, ccQuery: '',
            press(d) { $wire.destination = ($wire.destination || '') + d; },
            back() { $wire.destination = ($wire.destination || '').slice(0, -1); },
            clearAll() { $wire.destination = ''; },
            startZero() { this.held = false; this.hold = setTimeout(() => { this.held = true; this.press('+'); }, 400); },
            endZero() { clearTimeout(this.hold); if (! this.held) this.press('0'); },
            get valid() { return /^\+?[0-9]{6,15}$/.test(($wire.destination || '').trim()); },
            // Pick a country: keep the local part already keyed, swap the leading +<code>. When the prior code is unknown (a paste),
            // start the local part fresh so we never mangle their number.
            pickCountry(iso, name, code) {
                const prev = this.cc ? '+' + this.cc.code : null;
                let local = ($wire.destination || '');
                if (prev && local.startsWith(prev)) local = local.slice(prev.length);
                else if (local.startsWith('+')) local = '';
                this.cc = { iso, name, code };
                $wire.destination = '+' + code + local;
                this.ccOpen = false; this.ccQuery = '';
            },
            ccMatch(name, code) {
                const q = this.ccQuery.trim().toLowerCase();
                if (! q) return true;
                return name.toLowerCase().includes(q) || ('+' + code).includes(q) || code.includes(q);
            }
         }"
         x-init="if (! ($wire.destination || '').length) $wire.destination = '+' + cc.code">
        @if ($error)
            <div class="ns-dl__err" role="alert"><x-nx.icon name="x" /> <span>{{ $error }}</span></div>
        @endif

        {{-- Country picker + desktop wallet chip (mobile has the balance in the header bar). --}}
        <div class="ns-dl__top">
            <div class="ns-dl__ccwrap" x-on:keydown.escape.window="ccOpen = false">
                <button type="button" x-on:click="ccOpen = ! ccOpen" aria-haspopup="listbox" x-bind:aria-expanded="ccOpen" class="ns-dl__cc">
                    <span class="inline-block h-4 w-6 rounded-[3px] bg-cover" role="img" x-bind:class="cc ? 'fi fi-' + cc.iso : ''" x-bind:aria-label="cc ? cc.name : ''"></span>
                    <span x-text="cc ? '+' + cc.code : 'Country'"></span>
                    <span class="ns-dl__chev" x-bind:class="ccOpen ? 'is-open' : ''"><x-nx.icon name="chevron-right" /></span>
                </button>
                <div x-show="ccOpen" x-cloak x-transition x-on:click.outside="ccOpen = false" class="ns-dl__menu">
                    <div class="ns-dl__menusearch">
                        <input type="text" x-model="ccQuery" x-ref="ccSearch" placeholder="Search country or code" aria-label="Search country or code" class="ns-input" style="height:42px;font-size:15px">
                    </div>
                    <ul role="listbox">
                        @foreach ($dialCountries as $c)
                            <li x-show="ccMatch(@js($c['name']), '{{ $c['code'] }}')" wire:key="dc-opt-{{ $c['iso'] }}">
                                <button type="button" x-on:click="pickCountry('{{ $c['iso'] }}', @js($c['name']), '{{ $c['code'] }}')" class="ns-dl__opt" x-bind:class="cc && cc.iso === '{{ $c['iso'] }}' ? 'is-on' : ''">
                                    <x-country-flag :country="$c['iso']" class="h-4 w-6 shrink-0" />
                                    <span class="ns-dl__optname">{{ $c['name'] }}</span>
                                    <span class="ns-dl__optcode">+{{ $c['code'] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <a href="{{ route('wallet') }}" wire:navigate class="ns-dl__wallet"><x-nx.icon name="wallet" /> ${{ number_format($walletUsd, 2) }}</a>
        </div>

        {{-- Number display: big, centred, editable (paste-friendly). --}}
        <div class="ns-dl__display">
            <input type="tel" wire:model.live="destination" inputmode="tel" placeholder="Enter a number" aria-label="Number to call" class="ns-dl__num">
            <button type="button" x-show="($wire.destination || '').length" x-on:click="back()" x-on:contextmenu.prevent="clearAll()" aria-label="Delete last digit" class="ns-dl__bs"><x-nx.icon name="delete" /></button>
        </div>

        {{-- Live quote (retail only: provider cost is never shown). --}}
        <div class="ns-dl__quote">
            @if ($quoted && $ratePerMin !== null)
                <p><b>${{ number_format($ratePerMin, 2) }}</b>/min · <x-nx.icon name="wallet" style="font-size:14px;vertical-align:-2px" /> {{ $fundedMinutes }} min funded</p>
            @else
                <button type="button" wire:click="prepare" wire:loading.attr="disabled" wire:target="prepare" x-bind:disabled="! valid" class="ns-dl__rate">
                    <span wire:loading.remove wire:target="prepare">Check the per-minute rate</span>
                    <span wire:loading wire:target="prepare" class="inline-flex items-center gap-1"><x-ui.spinner class="h-3 w-3" /> Checking…</span>
                </button>
            @endif
        </div>

        {{-- Keypad --}}
        <div class="ns-dl__pad">
            @foreach ($keypad as $k)
                @if ($k['d'] === '0')
                    <button type="button" wire:key="key-0" x-on:pointerdown.prevent="startZero()" x-on:pointerup="endZero()" x-on:pointerleave="clearTimeout(hold)" class="ns-dl__key" aria-label="0, hold for plus">
                        <b>0</b><small>+</small>
                    </button>
                @else
                    <button type="button" wire:key="key-{{ $k['d'] }}" x-on:click="press('{{ $k['d'] }}')" class="ns-dl__key" aria-label="{{ $k['d'] }}">
                        <b>{{ $k['d'] }}</b>@if ($k['l'] !== '')<small>{{ $k['l'] }}</small>@endif
                    </button>
                @endif
            @endforeach
        </div>

        {{-- Call button: the server validates + holds the wallet; the whole money path lives in dial(). --}}
        <div class="ns-dl__callrow">
            <button type="button" wire:click="dial" wire:loading.attr="disabled" wire:target="dial" x-bind:disabled="! valid" aria-label="Call" class="ns-dl__call">
                <span wire:loading.remove wire:target="dial"><x-nx.icon name="phone" /></span>
                <span wire:loading wire:target="dial"><x-ui.spinner class="h-7 w-7" /></span>
            </button>
        </div>
        <p class="ns-small" style="text-align:center;margin-top:12px">We reserve your funded minutes before dialling and refund whatever you don't use.</p>

        {{-- Text the same number without saving a contact first; the modal handles the "need an SMS-capable Line" gate itself. --}}
        <div style="display:flex;justify-content:center;margin-top:14px">
            <button type="button" x-bind:disabled="! valid" x-on:click="$dispatch('open-send-message', { to: ($wire.destination || '').trim(), name: '' })" class="ns-cta ns-cta--pill ns-cta--ghost ns-cta--sm">
                <x-nx.icon name="message-circle" /> Text this number instead
            </button>
        </div>
    </div>

    {{-- One send-message modal host for the dialer (catches open-send-message). --}}
    @livewire('send-message')
    </div>{{-- /left column --}}

    <div class="ns-dl__rail">{{-- right column: contacts + recents rail --}}
    {{-- Contacts quick-pick: tap a saved name to fill the field. --}}
    @if ($contacts->isNotEmpty())
        <div x-data="{ fill(n) { $wire.destination = n; } }">
            <div class="ns-dl__railhead">
                <span class="ns-lbl" style="margin:0">Your contacts</span>
                <a href="{{ route('numbers.contacts') }}" wire:navigate class="ns-linkink" style="font-size:13px">Manage</a>
            </div>
            <div class="ns-dl__chips">
                @foreach ($contacts as $contact)
                    <button type="button" wire:key="dc-{{ $contact->id }}" x-on:click="fill('{{ $contact->phone_number }}')" class="ns-dl__chip ns-ring">
                        @include('partials.contact-avatar', ['contact' => $contact, 'size' => 'h-9 w-9 text-xs'])
                        <span>{{ $contact->name }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Recent calls (retail totals only: never provider cost). --}}
    @if ($recent->isNotEmpty())
        <div class="ns-dl__recent">
            <span class="ns-lbl">Recent calls</span>
            <div class="ns-dl__calls ns-ring">
                @foreach ($recent as $call)
                    <div class="ns-dl__call-row" wire:key="call-{{ $call->id }}">
                        <span class="ns-dl__dest"><x-nx.icon name="phone" /> <b>{{ $call->destination }}</b></span>
                        <span class="ns-dl__amt">
                            @if ($call->status === 'completed' && (int) $call->minutes_billed > 0)
                                <b>${{ number_format((float) $call->amount_charged, 2) }}</b><small> · {{ $call->minutes_billed }} min</small>
                            @else
                                <small>No answer, refunded</small>
                            @endif
                        </span>
                        {{-- Spam-report + auto-block (Prompt 11). --}}
                        <button type="button" wire:click="reportSpam({{ $call->id }})" wire:confirm="Report {{ $call->destination }} as spam?" aria-label="Report {{ $call->destination }} as spam" class="ns-ct__act ns-ct__act--spam"><x-nx.icon name="alert-triangle" /></button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    </div>{{-- /right column --}}
    </div>{{-- /two-column grid --}}
</x-nx.page>

{{-- nx:allow:start the live-call screen is a deliberate always-dark full-screen overlay (driven by dialer.js), the same in every skin --}}
    {{-- Live-call screen — a full-screen iOS-style overlay driven by
         dialer.js (shows/hides via the `hidden` class). Alpine owns only
         the in-call DTMF keypad's visibility; JS owns the audio + controls.
         ============================================================ --}}
    <div data-dialer-panel class="nx-callbg hidden fixed inset-0 z-[70] flex flex-col items-center justify-between
                px-6 py-12 text-white"
         x-data="{ pad: false }">

        {{-- Callee identity --}}
        <div class="flex flex-1 flex-col items-center justify-center gap-4" x-show="! pad" x-transition.opacity>
            <span data-dialer-avatar aria-hidden="true"
                  class="flex h-28 w-28 items-center justify-center rounded-full bg-gradient-to-br from-teal-400 to-teal-600 text-4xl font-bold text-white shadow-2xl shadow-black/40">
                <x-icon name="phone" class="h-12 w-12" data-dialer-avatar-glyph />
            </span>
            <div class="text-center">
                <p data-dialer-peer class="text-2xl font-semibold">—</p>
                <p data-dialer-peer-sub class="mt-0.5 text-sm text-white/50"></p>
            </div>
            <p class="flex items-center gap-2 text-sm text-white/70">
                <span data-dialer-state>Connecting…</span>
                <span data-dialer-timer class="font-mono tabular-nums">00:00</span>
            </p>
        </div>

        {{-- In-call DTMF keypad (send tones during the call). --}}
        <div class="flex flex-1 flex-col items-center justify-center" x-show="pad" x-cloak x-transition.opacity>
            <p class="mb-4 flex items-center gap-2 text-sm text-white/60">
                <span data-dialer-peer-mirror>—</span> · <span data-dialer-timer-mirror class="font-mono tabular-nums">00:00</span>
            </p>
            <div class="grid max-w-[17rem] grid-cols-3 gap-x-6 gap-y-3">
                @foreach (['1','2','3','4','5','6','7','8','9','*','0','#'] as $d)
                    <button type="button" data-dialer-dtmf="{{ $d }}" wire:key="dtmf-{{ $d }}"
                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white/10 text-xl font-semibold text-white transition active:scale-90 active:bg-white/20">{{ $d }}</button>
                @endforeach
            </div>
            <button type="button" x-on:click="pad = false" class="mt-5 text-sm font-medium text-white/60 hover:text-white">Hide keypad</button>
        </div>

        {{-- Controls: mute · keypad · speaker --}}
        <div class="w-full max-w-xs shrink-0">
            <div class="mb-8 grid grid-cols-3 gap-4">
                <button type="button" data-dialer-mute aria-pressed="false"
                        class="nx-callctl flex flex-col items-center gap-1.5 text-xs text-white/70">
                    <span class="nx-callctl-btn flex h-14 w-14 items-center justify-center rounded-full bg-white/10 transition">
                        <x-icon name="mic" class="h-6 w-6 nx-callctl-on" /><x-icon name="mic-off" class="h-6 w-6 nx-callctl-off" />
                    </span>
                    <span class="nx-callctl-on">Mute</span><span class="nx-callctl-off">Muted</span>
                </button>

                <button type="button" x-on:click="pad = ! pad"
                        class="flex flex-col items-center gap-1.5 text-xs text-white/70">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-white/10 transition" x-bind:class="pad ? 'bg-white/25' : ''">
                        <x-icon name="grid" class="h-6 w-6" />
                    </span>
                    Keypad
                </button>

                <button type="button" data-dialer-speaker aria-pressed="false"
                        class="nx-callctl flex flex-col items-center gap-1.5 text-xs text-white/70">
                    <span class="nx-callctl-btn flex h-14 w-14 items-center justify-center rounded-full bg-white/10 transition">
                        <x-icon name="volume-2" class="h-6 w-6" />
                    </span>
                    Speaker
                </button>
            </div>

            {{-- End call --}}
            <div class="flex justify-center">
                <button type="button" data-dialer-hangup aria-label="End call"
                        class="flex h-16 w-16 items-center justify-center rounded-full bg-red-500 text-white shadow-lg shadow-red-500/30 transition hover:bg-red-600 active:scale-90">
                    <x-icon name="phone" class="h-7 w-7 rotate-[135deg]" />
                </button>
            </div>
        </div>
    </div>
{{-- nx:allow:end --}}
</div>
