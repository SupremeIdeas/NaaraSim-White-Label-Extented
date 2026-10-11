{{-- LEGACY Naara Line body + attachment controls, kept behind config('composer.surfaces.send_message') = false as an instant rollback. Remove once Chat Composer Pro has run clean in production. --}}
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

