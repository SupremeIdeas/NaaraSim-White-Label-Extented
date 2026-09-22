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
             class="relative flex max-h-[92vh] w-full max-w-md flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl dark:bg-[#0D1B2A] sm:rounded-3xl">
            <div class="mx-auto mt-3 h-1.5 w-10 shrink-0 rounded-full bg-slate-300 dark:bg-white/20 sm:hidden"></div>

            <div class="flex items-center justify-between px-5 py-4">
                <h2 class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white">
                    <x-icon name="message-circle" class="h-5 w-5 text-primary" /> New message
                </h2>
                <button type="button" @click="open = false" aria-label="Close" class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10"><x-icon name="x" class="h-5 w-5" /></button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-6">
                @if ($lines->isEmpty())
                    {{-- No Line yet — messaging needs a real "from" number. --}}
                    <div class="rounded-2xl border border-dashed border-slate-300 p-5 text-center dark:border-white/10">
                        <p class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ \App\Support\BrandSettings::rebrand('You need a Naara Line to text') }}</p>
                        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">A message has to come from a number you own. Get a permanent voice + SMS line to start texting.</p>
                        <a href="{{ route('numbers', ['modal' => 'line']) }}" wire:navigate
                           class="inline-flex items-center gap-2 rounded-2xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark">
                            <x-icon name="plus" class="h-4 w-4" /> {{ \App\Support\BrandSettings::rebrand('Get a Naara Line') }}
                        </a>
                    </div>
                @else
                    {{-- To — fixed when opened for a known recipient (a contact /
                         a dialed number), or an editable field when opened blank
                         from a Line, so messaging works without a saved contact
                         first (BUILD-4 §6.1). --}}
                    @if ($to !== '' && $peerName !== '')
                        <div class="mb-3 flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 dark:bg-white/5">
                            <span class="text-xs font-medium uppercase tracking-wide text-slate-400">To</span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $peerName }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $to }}</p>
                            </div>
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-400">To</label>
                            <input type="tel" inputmode="tel" wire:model="to" placeholder="+1 555 123 4567"
                                   class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-2 focus:ring-primary/30 dark:border-white/10 dark:bg-white/5 dark:text-slate-100">
                        </div>
                    @endif

                    {{-- From (Line picker only when the user owns more than one) --}}
                    <div class="mb-3">
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">From your Line</label>
                        @if ($lines->count() > 1)
                            <select wire:model.live="lineId"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100">
                                @foreach ($lines as $line)
                                    <option value="{{ $line->id }}">{{ $line->phone_number }}</option>
                                @endforeach
                            </select>
                        @else
                            <p class="rounded-xl bg-slate-50 px-3 py-2.5 text-sm font-medium text-slate-700 dark:bg-white/5 dark:text-slate-200">{{ $lines->first()->phone_number }}</p>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div x-data="{ get len() { return ($wire.body || '').length; } }">
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Message</label>
                        <textarea wire:model.live.debounce.400ms="body" rows="4" maxlength="918" placeholder="Type your message…"
                                  class="w-full resize-none rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-white/10 dark:bg-[#243352] dark:text-slate-100"></textarea>
                        <div class="mt-1 flex items-center justify-between text-[11px] text-slate-400">
                            <span x-text="len + ' chars'"></span>
                            @if ($quote)
                                <span>{{ $quote['segments'] }} {{ Str::plural('part', $quote['segments']) }}</span>
                            @endif
                        </div>
                        @error('to') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Attachment (MMS) — only offered on an MMS-capable line
                         (Marketing/Chat blueprint Phase B3: provider-aware, not
                         just US/CA — see VirtualNumber::supportsMms()). Reuses
                         the SAME recorder component as NaaraCare chat, wired to
                         this component's own `send()` method instead of
                         `sendVoice()`. --}}
                    @if ($canAttach)
                        <div class="mt-3" x-data="voiceRecorder({ sendMethod: 'send' })">
                            @if ($attachment)
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-white/10 dark:bg-white/5">
                                    <span class="flex min-w-0 items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                        <x-icon name="image" class="h-4 w-4 shrink-0 text-primary" />
                                        <span class="truncate">{{ method_exists($attachment, 'getClientOriginalName') ? $attachment->getClientOriginalName() : 'Image' }}</span>
                                    </span>
                                    <button type="button" wire:click="$set('attachment', null)" aria-label="Remove attachment" class="shrink-0 text-slate-400 hover:text-red-600"><x-icon name="x" class="h-4 w-4" /></button>
                                </div>
                                <div wire:loading wire:target="attachment" class="mt-1 text-[11px] text-slate-400">Uploading…</div>
                            @elseif ($voiceNote)
                                <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-white/10 dark:bg-white/5">
                                    <span class="flex min-w-0 items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                        <x-icon name="mic" class="h-4 w-4 shrink-0 text-primary" /> Voice note
                                    </span>
                                    <button type="button" wire:click="$set('voiceNote', null)" aria-label="Remove voice note" class="shrink-0 text-slate-400 hover:text-red-600"><x-icon name="x" class="h-4 w-4" /></button>
                                </div>
                                <div wire:loading wire:target="voiceNote,send" class="mt-1 text-[11px] text-slate-400">Uploading…</div>
                            @else
                                {{-- Recording controls (fill the row while recording/uploading). --}}
                                <div x-show="state === 'recording' || state === 'uploading'" x-cloak class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 dark:border-white/10">
                                    <span class="relative flex h-2.5 w-2.5 shrink-0">
                                        <span class="absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75 motion-safe:animate-ping"></span>
                                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"></span>
                                    </span>
                                    <span class="font-mono text-sm tabular-nums text-slate-700 dark:text-slate-200" x-text="timeLabel">0:00</span>
                                    <span class="flex-1 truncate text-xs text-slate-400" x-text="state === 'uploading' ? 'Sending…' : 'Recording…'"></span>
                                    <button type="button" @click="cancel()" x-show="state === 'recording'" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-red-600 dark:hover:bg-white/10" title="Cancel"><x-icon name="x" class="h-4 w-4" /></button>
                                    <button type="button" @click="stop()" x-show="state === 'recording'" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-white hover:bg-primary-dark" title="Stop &amp; use"><x-icon name="check" class="h-4 w-4" /></button>
                                </div>

                                <div x-show="state !== 'recording' && state !== 'uploading'" class="flex flex-wrap gap-2">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-slate-300 px-3 py-2 text-xs font-medium text-slate-500 transition hover:border-primary/40 hover:text-primary dark:border-white/10 dark:text-slate-400">
                                        <x-icon name="image" class="h-4 w-4" /> Add a photo
                                        <input type="file" wire:model="attachment" accept="image/jpeg,image/png,image/gif" class="hidden">
                                    </label>
                                    <button type="button" @click="promptMic()" class="inline-flex items-center gap-2 rounded-xl border border-dashed border-slate-300 px-3 py-2 text-xs font-medium text-slate-500 transition hover:border-primary/40 hover:text-primary dark:border-white/10 dark:text-slate-400">
                                        <x-icon name="mic" class="h-4 w-4" /> Add a voice note
                                    </button>
                                </div>

                                <div x-show="state === 'priming'" x-cloak class="mt-2 flex items-start gap-2 rounded-xl border border-primary/20 bg-primary/5 p-3 text-xs dark:border-primary/30 dark:bg-primary/10">
                                    <x-icon name="mic" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                    <div class="flex-1">
                                        Allow microphone access to record a voice note.
                                        <div class="mt-1.5 flex gap-2">
                                            <button type="button" @click="requestMic()" class="rounded-lg bg-primary px-2.5 py-1 text-xs font-semibold text-white hover:bg-primary-dark">Allow</button>
                                            <button type="button" @click="reset()" class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-500 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300">Not now</button>
                                        </div>
                                    </div>
                                </div>
                                <div x-show="state === 'denied' || state === 'unsupported' || state === 'error'" x-cloak class="mt-2 flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                    <x-icon name="shield" class="mt-0.5 h-4 w-4 shrink-0" />
                                    <div class="flex-1">
                                        <span x-show="state === 'denied'">Microphone access is blocked — enable it for this site to record a voice note.</span>
                                        <span x-show="state === 'unsupported'">Recording isn't supported in this browser.</span>
                                        <span x-show="state === 'error'">Something went wrong recording. Please try again.</span>
                                        <button type="button" @click="reset()" class="ml-1 font-semibold underline">Dismiss</button>
                                    </div>
                                </div>
                            @endif
                            @error('attachment') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            @error('voiceNote') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        {{-- §6.3: explain the MMS scope rather than silently hiding it. --}}
                        <p class="mt-3 flex items-center gap-1.5 text-[11px] text-slate-400">
                            <x-icon name="image" class="h-3.5 w-3.5" /> Photo and voice-note attachments (MMS) are available on US &amp; Canada numbers.
                        </p>
                    @endif

                    @if ($error)
                        <div class="mt-3 flex items-start gap-2 rounded-xl bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                            <x-icon name="x" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ $error }}</span>
                        </div>
                    @endif

                    {{-- Live retail quote (cost never shown). --}}
                    @if ($quote)
                        <div class="mt-4 flex items-center justify-between rounded-xl bg-primary/5 px-4 py-3 text-sm dark:bg-primary/10">
                            <span class="text-slate-600 dark:text-slate-300">Cost to send</span>
                            <span class="font-semibold text-primary">${{ number_format($quote['retail_total'], 2) }}</span>
                        </div>
                    @endif

                    <button type="button" wire:click="send" wire:loading.attr="disabled" wire:target="send,attachment,voiceNote"
                            @disabled(trim($body) === '' && ! $attachment && ! $voiceNote)
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3 text-sm font-semibold text-white transition hover:bg-primary-dark disabled:opacity-50">
                        <span wire:loading.remove wire:target="send" class="inline-flex items-center gap-2"><x-icon name="send" class="h-4 w-4" /> {{ $attachment ? 'Send with photo' : ($voiceNote ? 'Send voice note' : 'Send message') }}</span>
                        <span wire:loading wire:target="send" class="inline-flex items-center gap-2"><x-ui.spinner class="h-4 w-4" /> Sending…</span>
                    </button>
                    <p class="mt-2 text-center text-[11px] text-slate-400 dark:text-slate-500">Charged from your wallet. @if ($quote && ($quote['is_mms'] ?? false))Photos and voice notes send as MMS.@else Longer messages send as multiple parts.@endif</p>
                @endif
            </div>
        </div>
    </div>
</div>
