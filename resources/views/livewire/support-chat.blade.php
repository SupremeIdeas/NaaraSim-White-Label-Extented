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

    {{-- Composer — one unified input bar (BUILD-3 §3): text + attach + mic in a
         single rounded container, plus a send button, reading as one control
         cluster. The mic records in-page (getUserMedia/MediaRecorder), not via a
         native file picker; it explains itself BEFORE the OS permission prompt. --}}
    <div class="mt-3 space-y-2" x-data="voiceRecorder()">

        {{-- "Explain first" permission priming — shown BEFORE the browser prompt. --}}
        <div x-show="state === 'priming'" x-cloak x-transition
             class="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 p-3 text-sm dark:border-primary/30 dark:bg-primary/10">
            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary dark:bg-primary/25">
                <x-icon name="mic" class="h-4 w-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-medium text-slate-800 dark:text-slate-100">{{ $agentName }} needs microphone access to record your voice note.</p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Your browser will ask next. Nothing is recorded until you tap record.</p>
                <div class="mt-2 flex gap-2">
                    <button type="button" @click="requestMic()"
                            class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-dark">Allow microphone</button>
                    <button type="button" @click="reset()"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-50 dark:border-[var(--brand-card-border-dark)] dark:text-slate-300 dark:hover:bg-[#243352]">Not now</button>
                </div>
            </div>
        </div>

        {{-- Denied — a clear next step, never a dead end. --}}
        <div x-show="state === 'denied'" x-cloak x-transition
             class="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            <x-icon name="shield" class="mt-0.5 h-4 w-4 shrink-0" />
            <div class="flex-1">
                Microphone access is blocked. Enable it for this site in your browser or app settings, then tap the mic again. You can still type or attach a file.
                <button type="button" @click="reset()" class="ml-1 font-semibold underline">Dismiss</button>
            </div>
        </div>

        {{-- Unsupported / unexpected error. --}}
        <div x-show="state === 'unsupported' || state === 'error'" x-cloak x-transition
             class="flex items-start gap-2 rounded-xl bg-slate-100 p-3 text-xs text-slate-600 dark:bg-[#243352] dark:text-slate-300">
            <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
            <div class="flex-1">
                <span x-show="state === 'unsupported'">Recording isn’t supported in this browser — you can still type or attach an audio file.</span>
                <span x-show="state === 'error'">Something went wrong recording. Please try again, or type your message instead.</span>
                <button type="button" @click="reset()" class="ml-1 font-semibold underline">Dismiss</button>
            </div>
        </div>

        <form wire:submit="send" class="flex items-center gap-2"
              x-on:message-added.window="$nextTick(() => { const i = $el.querySelector('textarea'); i && i.focus(); })">
            <x-nia-glow-wrapper interactive class="flex-1 rounded-full">
            {{-- Marketing/Chat blueprint Phase B: elastic growth (auto-resizing,
                 capped, internal scroll beyond that) + contextual reveal — the
                 mic is the resting default (WhatsApp's own behaviour); the send
                 button and attach icon reveal together the moment there's
                 actual content (typed text or an attached file) to send.
                 `hasContent` reads `$wire.draft`/`$wire.evidence` directly
                 (same pattern already used in send-message.blade.php's char
                 counter) so the reveal is instant with zero extra network
                 round-trips. --}}
            <div class="flex items-end gap-1 rounded-3xl border border-slate-200/70 bg-white px-2 py-1 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/40 dark:border-white/10 dark:bg-[#243352]"
                 x-data="{
                    autoGrow(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 120) + 'px'; },
                    get hasContent() { return (($wire.draft || '').trim() !== '') || !!$wire.evidence; },
                 }">

                {{-- Normal controls (hidden while recording). appearance-none:
                     some browsers paint a subtle default text-field chrome on
                     `appearance: auto` inputs that can peek through a parent's
                     rounded border — belt-and-suspenders alongside the real
                     fix (the input row now correctly clears the bottom nav
                     instead of rendering behind it). `wire:model` (deferred,
                     not `.live`) keeps typing itself request-free; the
                     `hasContent` getter above reads `$wire.draft` directly
                     rather than a separate entangled copy. --}}
                <textarea rows="1" wire:model="draft" autocomplete="off" placeholder="Type your message…"
                          x-show="state !== 'recording' && state !== 'uploading'"
                          x-init="autoGrow($el)" x-on:input="autoGrow($el)"
                          class="min-w-0 flex-1 resize-none appearance-none overflow-y-auto border-0 bg-transparent px-2 py-2 text-sm text-slate-900 focus:ring-0 dark:text-slate-100"
                          wire:loading.attr="disabled" wire:target="send,sendVoice"></textarea>

                <label x-show="(state !== 'recording' && state !== 'uploading') && hasContent"
                       class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-[#2D4060]" title="Attach evidence (image or PDF)">
                    <x-icon name="upload" class="h-5 w-5" />
                    <input type="file" accept="{{ \App\Support\SupportAttachment::acceptAttribute() }}" class="hidden" wire:model="evidence">
                </label>

                {{-- Mic — the resting default when there's nothing to send yet
                     (a soft breathing pulse invites the tap, since the app's
                     own "explain permissions before requesting" safety design
                     needs a real click to show that explanation first, rather
                     than a press-and-hold that could get interrupted mid-hold
                     by the OS permission dialog). --}}
                <button type="button" @click="promptMic()"
                        x-show="(state !== 'recording' && state !== 'uploading') && ! hasContent"
                        class="flex h-9 w-9 shrink-0 animate-pulse items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 hover:!animate-none dark:text-slate-300 dark:hover:bg-[#2D4060]" title="Record a voice note">
                    <x-icon name="mic" class="h-5 w-5" />
                </button>

                {{-- Recording controls (fill the bar while recording/uploading). --}}
                <div x-show="state === 'recording' || state === 'uploading'" x-cloak class="flex flex-1 items-center gap-2 px-2">
                    <span class="relative flex h-2.5 w-2.5 shrink-0">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75 motion-safe:animate-ping"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"></span>
                    </span>
                    <span class="font-mono text-sm tabular-nums text-slate-700 dark:text-slate-200" x-text="timeLabel">0:00</span>
                    <span class="flex-1 truncate text-xs text-slate-400" x-text="state === 'uploading' ? 'Sending…' : 'Recording your voice note…'"></span>
                    <button type="button" @click="cancel()" x-show="state === 'recording'"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-red-600 dark:hover:bg-[#2D4060]" title="Cancel">
                        <x-icon name="x" class="h-4 w-4" />
                    </button>
                    <button type="button" @click="stop()" x-show="state === 'recording'"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-white hover:bg-primary-dark" title="Stop &amp; send">
                        <x-icon name="check" class="h-4 w-4" />
                    </button>
                </div>
            </div>
            </x-nia-glow-wrapper>

            <button type="submit" wire:loading.attr="disabled" wire:target="send"
                    x-show="(($wire.draft || '').trim() !== '') || !!$wire.evidence"
                    x-bind:disabled="state === 'recording' || state === 'uploading' || state === 'requesting'"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-white hover:bg-primary-dark disabled:opacity-60">
                <x-icon name="send" class="h-5 w-5" />
            </button>
        </form>

        {{-- Selected-evidence chip (before send). --}}
        @if ($evidence)
            <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                <span wire:loading wire:target="evidence" class="text-slate-400">Attaching…</span>
                <span wire:loading.remove wire:target="evidence" class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 dark:bg-[#243352]">
                    <x-icon name="file-text" class="h-3.5 w-3.5" /> Evidence ready to send
                    <button type="button" wire:click="$set('evidence', null)" class="text-slate-400 hover:text-red-600"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                </span>
            </div>
        @endif

        @error('draft') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        @error('voiceNote') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        @error('evidence') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        <p wire:loading wire:target="sendVoice" class="text-xs text-slate-400">Sending your voice note…</p>
    </div>
</div>
