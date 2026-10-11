{{-- nx:converted (skin tokens only; see docs/appearance/SKIN-CONTRACT.md) --}}
{{-- Send an SMS from the user's Naara Line — a bottom sheet (mobile) / centred
     dialog (desktop) that pops like the mobile menu. Livewire owns the money. --}}
<div x-data="{ open: @entangle('open').live }">
    <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center"
         @keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-label="New message">
        <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-black/60" @click="open = false"></div>

        <div x-show="open" x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-8 opacity-0 sm:scale-95" x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-8 opacity-0"
             class="relative flex max-h-[92vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl bg-[rgb(var(--nx-surface))] shadow-2xl sm:rounded-3xl">
            <div class="mx-auto mt-3 h-1.5 w-10 shrink-0 rounded-full bg-[rgb(var(--nx-line-strong))] sm:hidden"></div>

            <div class="flex items-center justify-between px-5 py-4">
                <h2 class="flex items-center gap-2 text-base font-bold text-[rgb(var(--nx-text))]">
                    <x-icon name="message-circle" class="h-5 w-5 text-[rgb(var(--nx-teal-ink))]" /> New message
                </h2>
                <button type="button" @click="open = false" aria-label="Close" class="flex h-8 w-8 items-center justify-center rounded-full text-[rgb(var(--nx-text-2))] hover:bg-[rgb(var(--nx-surface-3))]"><x-icon name="x" class="h-5 w-5" /></button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-6">
                @if ($lines->isEmpty())
                    {{-- No Line yet — messaging needs a real "from" number. --}}
                    <div class="rounded-2xl border border-dashed border-[rgb(var(--nx-line-strong))] p-5 text-center">
                        <p class="mb-1 text-sm font-semibold text-[rgb(var(--nx-text))]">{{ \App\Support\BrandSettings::rebrand('You need a Naara Line to text') }}</p>
                        <p class="mb-4 text-xs text-[rgb(var(--nx-text-2))]">A message has to come from a number you own. Get a permanent voice + SMS line to start texting.</p>
                        <a href="{{ route('numbers', ['modal' => 'line']) }}" wire:navigate
                           class="inline-flex items-center gap-2 rounded-2xl bg-[rgb(var(--nx-cta-b))] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))]">
                            <x-icon name="plus" class="h-4 w-4" /> {{ \App\Support\BrandSettings::rebrand('Get a Naara Line') }}
                        </a>
                    </div>
                @else
                    {{-- To — fixed when opened for a known recipient (a contact /
                         a dialed number), or an editable field when opened blank
                         from a Line, so messaging works without a saved contact
                         first (BUILD-4 §6.1). --}}
                    @if ($to !== '' && $peerName !== '')
                        <div class="mb-3 flex items-center gap-3 rounded-2xl bg-[rgb(var(--nx-surface-2))] px-4 py-3">
                            <span class="text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">To</span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-[rgb(var(--nx-text))]">{{ $peerName }}</p>
                                <p class="truncate text-xs text-[rgb(var(--nx-text-2))]">{{ $to }}</p>
                            </div>
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-[rgb(var(--nx-text-2))]">To</label>
                            <input type="tel" inputmode="tel" wire:model="to" placeholder="+1 555 123 4567"
                                   class="w-full rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] px-4 py-3 text-sm text-[rgb(var(--nx-text))] focus:border-[rgb(var(--nx-teal))] focus:ring-2 focus:ring-[rgb(var(--nx-teal)/0.3)]">
                        </div>
                    @endif

                    {{-- From (Line picker only when the user owns more than one) --}}
                    <div class="mb-3">
                        <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">From your Line</label>
                        @if ($lines->count() > 1)
                            <select wire:model.live="lineId"
                                    class="w-full rounded-xl border border-[rgb(var(--nx-line-strong))] bg-[rgb(var(--nx-surface))] px-3 py-2.5 text-sm">
                                @foreach ($lines as $line)
                                    <option value="{{ $line->id }}">{{ $line->phone_number }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="rounded-xl bg-[rgb(var(--nx-surface-2))] px-3 py-2.5 text-sm font-medium text-[rgb(var(--nx-text))]">{{ $lines->first()->phone_number }}</p>
                        @endif
                    </div>

{{-- Body + attachments: Chat Composer Pro in `field` mode. It only COLLECTS text / one photo / a voice note; this modal keeps its own
                         explicit, wallet-charging Send button, live quote and server rules (a money action never gets an undo timer or a
                         background retry). Changes are mirrored into the existing `body` / `attachment` / `voiceNote` properties. --}}
                    @if (config('composer.surfaces.send_message'))
                        <div x-data="{
                                t: null, img: null, vb: null,
                                len: 0,
                                up(prop, file) { return new Promise((resolve, reject) => $wire.upload(prop, file, resolve, reject)); },
                                sync(d) {
                                    this.len = (d.text || '').length;
                                    clearTimeout(this.t);
                                    this.t = setTimeout(async () => {
                                        if (($wire.body || '') !== d.text) await $wire.$set('body', d.text);
                                        const photo = d.files[0] || null;
                                        if (photo && photo !== this.img) { this.img = photo; await this.up('attachment', photo); }
                                        else if (!photo && this.img) { this.img = null; await $wire.$set('attachment', null); }
                                        if (d.voice && d.voice.blob !== this.vb) { this.vb = d.voice.blob; await this.up('voiceNote', new File([d.voice.blob], 'voice-note.' + d.voice.ext, { type: d.voice.mime })); }
                                        else if (! d.voice && this.vb) { this.vb = null; await $wire.$set('voiceNote', null); }
                                    }, 350);
                                },
                             }"
                             x-on:cc:change="sync($event.detail)">
                            <label class="mb-1 block text-xs font-medium text-[rgb(var(--nx-text-2))]">Message</label>
                            @if ($open)
                                <x-composer mode="field" :rows="4" :max-chars="918" convo-id="send-message"
                                            :features="$canAttach ? ['emoji', 'attach', 'voice'] : ['emoji']" :attach-kinds="['media']"
                                            :accept="['media' => 'image/jpeg,image/png,image/gif']" :max-files="1" :max-file-mb="1"
                                            placeholder="Type your message…" />
                            @endif
                            <div class="mt-1 flex items-center justify-between text-[11px] text-[rgb(var(--nx-text-2))]">
                                <span x-text="len + ' chars'"></span>
                                <span wire:loading wire:target="attachment,voiceNote">Uploading…</span>
                                @if ($quote)
                                    <span>{{ $quote['segments'] }} {{ Str::plural('part', $quote['segments']) }}</span>
                                @endif
                            </div>
                            @error('to') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                            @error('attachment') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                            @error('voiceNote') <p class="mt-1 text-xs text-[rgb(var(--nx-bad))]">{{ $message }}</p> @enderror
                        </div>
                        @unless ($canAttach)
                            {{-- §6.3: explain the MMS scope rather than silently hiding it. --}}
                            <p class="mt-3 flex items-center gap-1.5 text-[11px] text-[rgb(var(--nx-text-2))]">
                                <x-icon name="image" class="h-3.5 w-3.5" /> Photo and voice-note attachments (MMS) are available on US &amp; Canada numbers.
                            </p>
                        @endunless
                    @else
                        @include('livewire.partials.legacy-send-message-body')
                    @endif
                    @if ($error)
                        <div class="mt-3 flex items-start gap-2 rounded-xl bg-[rgb(var(--nx-bad)/0.1)] p-3 text-sm text-[rgb(var(--nx-bad))]">
                            <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
                        </div>
                    @endif

                    {{-- Live retail quote (cost never shown). --}}
                    @if ($quote)
                        <div class="mt-4 flex items-center justify-between rounded-xl bg-[rgb(var(--nx-cta-b)/0.05)] px-4 py-3 text-sm">
                            <span class="text-[rgb(var(--nx-text-2))]">Cost to send</span>
                            <span class="font-semibold text-[rgb(var(--nx-teal-ink))]">${{ number_format($quote['retail_total'], 2) }}</span>
                        </div>
                    @endif

                    <button type="button" wire:click="send" wire:loading.attr="disabled" wire:target="send,attachment,voiceNote"
                            @disabled(trim($body) === '' && ! $attachment && ! $voiceNote)
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-[rgb(var(--nx-cta-b))] py-3 text-sm font-semibold text-white transition hover:bg-[rgb(var(--nx-cta-a))] disabled:opacity-50">
                        <span wire:loading.remove wire:target="send" class="inline-flex items-center gap-2"><x-icon name="send" class="h-4 w-4" /> {{ $attachment ? 'Send with photo' : ($voiceNote ? 'Send voice note' : 'Send message') }}</span>
                        <span wire:loading wire:target="send" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Sending…</span>
                    </button>
                    <p class="mt-2 text-center text-[11px] text-[rgb(var(--nx-text-2))]">Charged from your wallet. @if ($quote && ($quote['is_mms'] ?? false))Photos and voice notes send as MMS.@else Longer messages send as multiple parts.@endif</p>
                @endif
            </div>
        </div>
    </div>
</div>
