<div class="mx-auto flex max-w-2xl flex-col" x-data="niaChatLayout" :style="height ? `height: ${height}px` : ''">
    <div class="mb-3 flex items-center gap-3">
        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
            <x-icon name="message-circle" class="h-5 w-5" />
        </span>
        <div>
            <h1 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $agentName }} — Support</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Ask about eSIMs, numbers, your orders or device compatibility.</p>
        </div>
    </div>

    {{-- Transcript --}}
    <div class="flex-1 space-y-3 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 dark:border-[var(--brand-card-border-dark)] dark:bg-[var(--brand-card-dark)]"
         x-data x-init="$el.scrollTop = $el.scrollHeight"
         x-on:message-added.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
        @forelse ($messages as $m)
            @if ($m['role'] === 'user')
                <div class="flex justify-end">
                    <div class="max-w-[80%] rounded-2xl rounded-br-sm bg-primary px-4 py-2 text-sm text-white">
                        {{ $m['body'] }}
                        @if ($m['voice'])<audio controls preload="none" src="{{ $m['voice'] }}" class="mt-2 w-full"></audio>@endif
                        @if (! empty($m['attachment']))
                            @if ($m['attachment_image'])
                                <a href="{{ $m['attachment'] }}" target="_blank" rel="noopener">
                                    <img src="{{ $m['attachment'] }}" alt="{{ $m['attachment_name'] }}" class="mt-2 max-h-48 rounded-lg border border-white/20" />
                                </a>
                            @else
                                <a href="{{ $m['attachment'] }}" target="_blank" rel="noopener"
                                   class="mt-2 inline-flex items-center gap-1 rounded-lg bg-white/15 px-2 py-1 text-xs">
                                    <x-icon name="file-text" class="h-4 w-4" /> {{ $m['attachment_name'] ?: 'Attachment' }}
                                </a>
                            @endif
                        @endif
                    </div>
                </div>
            @else
                @php $isStream = $m['role'] === 'assistant' && $m['id'] === ($streamMessageId ?? null); @endphp
                {{-- BUILD-3 §5: the freshly-arrived Nia reply is hidden while the
                     reading/typing phases run, then revealed char-by-char. --}}
                <div class="flex flex-col items-start gap-1"
                     @if ($isStream) x-show="!($store.nia.phase === 'reading' || $store.nia.phase === 'typing')" x-cloak @endif>
                    @if ($m['role'] === 'staff')
                        <span class="ml-1 inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-primary"><x-icon name="id-card" class="h-3 w-3" /> Human agent</span>
                    @endif
                    <div @if ($m['role'] === 'assistant') x-data="niaBubble" data-full="{{ $m['body'] }}" data-stream="{{ $isStream ? '1' : '0' }}" data-id="{{ $m['id'] }}" :class="{ 'nia-glow--on': streaming }" @endif
                         class="max-w-[85%] whitespace-pre-line rounded-2xl rounded-bl-sm px-4 py-2 text-sm {{ $m['role'] === 'staff' ? 'bg-primary/10 text-slate-800 dark:bg-primary/20 dark:text-slate-100' : 'nia-glow bg-slate-100 text-slate-800 dark:bg-[#243352] dark:text-slate-100' }}">
                        @if ($m['role'] === 'assistant')
                            <span x-text="shown">{{ $m['body'] }}</span>
                        @else
                            {{ $m['body'] }}
                        @endif
                        @if ($m['voice'])
                            <audio controls preload="none" src="{{ $m['voice'] }}" class="mt-2 w-full"></audio>
                        @elseif ($m['voice_pending'])
                            <span class="mt-1 block text-[11px] text-slate-400">Preparing voice reply…</span>
                        @endif
                    </div>
                    @if (! empty($m['nav']))
                        <a href="{{ $m['nav'] }}" wire:navigate
                           class="ml-1 inline-flex items-center gap-1 rounded-lg border border-primary/30 bg-primary/5 px-3 py-1 text-xs font-medium text-primary hover:bg-primary/10">
                            <x-icon name="chevron-right" class="h-3 w-3" /> Take me there
                        </a>
                    @endif
                </div>
            @endif
        @empty
            <div class="flex h-full items-center justify-center text-center text-sm text-slate-400">
                <p>Hi, I'm {{ $agentName }}. How can I help you today?</p>
            </div>
        @endforelse

        {{-- Network wait while the reply is fetched (before the paced reveal). --}}
        <div wire:loading wire:target="send,sendVoice" class="flex items-center gap-2 text-sm text-slate-400">
            <span class="flex gap-1">
                <span class="h-2 w-2 animate-bounce rounded-full bg-slate-300 [animation-delay:-0.3s]"></span>
                <span class="h-2 w-2 animate-bounce rounded-full bg-slate-300 [animation-delay:-0.15s]"></span>
                <span class="h-2 w-2 animate-bounce rounded-full bg-slate-300"></span>
            </span>
            {{ $agentName }} is reading…
        </div>

        {{-- BUILD-3 §5 Phase 2: Nia's typing indicator — a glowing bubble with
             three bouncing dots, shown only during the typing phase. --}}
        <div x-show="$store.nia.phase === 'typing'" x-cloak class="flex justify-start">
            <x-nia-glow-wrapper typing="$store.nia.phase === 'typing'" class="rounded-2xl rounded-bl-sm">
                <div class="flex items-center gap-1.5 rounded-2xl rounded-bl-sm bg-slate-100 px-4 py-3 dark:bg-[#243352]">
                    <span class="h-2 w-2 animate-bounce rounded-full bg-slate-400 [animation-delay:-0.3s]"></span>
                    <span class="h-2 w-2 animate-bounce rounded-full bg-slate-400 [animation-delay:-0.15s]"></span>
                    <span class="h-2 w-2 animate-bounce rounded-full bg-slate-400"></span>
                </div>
            </x-nia-glow-wrapper>
        </div>
    </div>

    @if ($humanHandling)
        <div class="mt-3 flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            <x-icon name="id-card" class="h-4 w-4" /> A member of our team is looking after this conversation. Replies may take a little longer.
        </div>
    @endif

    {{-- Composer: Chat Composer Pro (<naara-composer>). The element builds ONE payload and fires `cc:transmit`; we answer it with the SAME
         Livewire pipeline this chat always used (`$wire.upload()` + send()/sendVoice()), so validation, throttling, private storage,
         transcoding and the AI hand-off are unchanged — no second transport. Features are limited to what the server accepts: text, emoji,
         ONE evidence file (image/PDF, SupportAttachment) and a voice note. The glow wrapper is the only focus treatment (`bare`). --}}
    @if (config('composer.surfaces.support_chat'))
        <div class="mt-3 space-y-2"
             x-data="{
                upload(prop, file) { return new Promise((resolve, reject) => $wire.upload(prop, file, resolve, reject)); },
                async transmit(p) {
                    const fail = () => { throw Object.assign(new Error(@js(__('composer.notSent'))), { fatal: true }); };
                    if (p.voice) {
                        await this.upload('voiceNote', new File([p.voice.blob], 'voice-note.' + p.voice.ext, { type: p.voice.mime }));
                        await $wire.sendVoice();
                        if ($wire.voiceNote) fail();
                        return;
                    }
                    await $wire.set('draft', p.text, false);
                    if (p.files[0]) await this.upload('evidence', p.files[0]);
                    await $wire.send();
                    if (($wire.draft || '').trim() !== '' || $wire.evidence) fail();
                },
             }"
             x-on:cc:transmit="$event.detail.wait(transmit($event.detail.payload))"
             x-on:message-added.window="$nextTick(() => $refs.cc && $refs.cc.focus())">
            <x-nia-glow-wrapper interactive class="rounded-3xl">
                <x-composer x-ref="cc" bare convo-id="support-{{ $conversation?->id }}"
                            :max-files="1" :max-file-mb="5"
                            :features="['emoji', 'attach', 'voice']" :attach-kinds="['media', 'document']"
                            :accept="['media' => implode(',', \App\Support\SupportAttachment::IMAGE_MIMES), 'document' => 'application/pdf']"
                            placeholder="Type your message…"
                            :labels="['micPrimer' => $agentName.' needs microphone access to record your voice note.']" />
            </x-nia-glow-wrapper>
            @error('draft') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            @error('voiceNote') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            @error('evidence') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
    @else
        @include('livewire.partials.legacy-support-composer')
    @endif
</div>
