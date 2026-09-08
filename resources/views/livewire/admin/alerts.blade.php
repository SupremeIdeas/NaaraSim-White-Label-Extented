@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="mx-auto max-w-4xl px-4 py-6 sm:px-6">
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">Login notices</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Pop-ups shown to users on login — announcements, offers, coupons, migration nudges.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,360px)]">
        {{-- Editor --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $editingId ? 'Edit notice' : 'New notice' }}</h2>

            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Title</label>
            <input type="text" wire:model="form.title" class="{{ $inp }}">
            @error('form.title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

            {{-- Rich text editor (contenteditable + execCommand, no dependency) --}}
            <label class="mb-1 mt-4 block text-xs font-semibold text-slate-500 dark:text-slate-400">Message</label>
            <div wire:ignore x-data="nxRichEditor(@js($form['body'] ?? ''))" class="rounded-lg border border-slate-200 dark:border-white/10">
                <div class="flex flex-wrap gap-1 border-b border-slate-200 p-1.5 dark:border-white/10">
                    <button type="button" @click="cmd('bold')" class="rounded px-2 py-1 text-sm font-bold hover:bg-slate-100 dark:hover:bg-white/10">B</button>
                    <button type="button" @click="cmd('italic')" class="rounded px-2 py-1 text-sm italic hover:bg-slate-100 dark:hover:bg-white/10">i</button>
                    <button type="button" @click="cmd('underline')" class="rounded px-2 py-1 text-sm underline hover:bg-slate-100 dark:hover:bg-white/10">U</button>
                    <button type="button" @click="format('h2')" class="rounded px-2 py-1 text-xs font-semibold hover:bg-slate-100 dark:hover:bg-white/10">H2</button>
                    <button type="button" @click="cmd('insertUnorderedList')" class="rounded px-2 py-1 text-xs hover:bg-slate-100 dark:hover:bg-white/10">• List</button>
                    <button type="button" @click="link()" class="rounded px-2 py-1 text-xs hover:bg-slate-100 dark:hover:bg-white/10">Link</button>
                </div>
                <div x-ref="editor" contenteditable="true" @input="sync()" @blur="sync()"
                     class="nx-prose min-h-[120px] px-3 py-2 text-sm text-slate-800 focus:outline-none dark:text-slate-100"></div>
            </div>
            @error('form.body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">CTA label</label>
                    <input type="text" wire:model="form.cta_label" class="{{ $inp }}"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">CTA link (URL or /path)</label>
                    <input type="text" wire:model="form.cta_url" placeholder="/merchant/apply" class="{{ $inp }}"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Coupon code (optional)</label>
                    <input type="text" wire:model="form.coupon_code" class="{{ $inp }}"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Priority</label>
                    <input type="number" wire:model="form.priority" class="{{ $inp }}"></div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Audience</label>
                    <select wire:model="form.audience" class="{{ $inp }}">
                        <option value="all">All users</option>
                        <option value="new">New users</option>
                        <option value="old">Returning users</option>
                    </select></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">"New" window (days)</label>
                    <input type="number" wire:model="form.new_days" class="{{ $inp }}"></div>
                <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Show up to (times)</label>
                    <input type="number" wire:model="form.max_views" class="{{ $inp }}"><p class="mt-0.5 text-[11px] text-slate-400">0 = unlimited</p></div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Trigger</label>
                    <select wire:model="form.trigger" class="{{ $inp }}">
                        <option value="any">Any login</option>
                        <option value="first_registration">New sign-ups only</option>
                    </select></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Start</label>
                        <input type="datetime-local" wire:model="form.starts_at" class="{{ $inp }}"></div>
                    <div><label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">End</label>
                        <input type="datetime-local" wire:model="form.ends_at" class="{{ $inp }}"></div>
                </div>
            </div>
            @error('form.ends_at') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

            <label class="mt-4 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" wire:model="form.is_active" class="rounded border-slate-300 text-primary focus:ring-primary"> Active
            </label>

            <div class="mt-5 flex gap-2">
                <button type="button" wire:click="save" class="flex-1 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark">Save notice</button>
                @if ($editingId)
                    <button type="button" wire:click="newNotice" class="rounded-full border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-600 dark:border-white/15 dark:text-slate-300">New</button>
                @endif
            </div>
        </div>

        {{-- List --}}
        <div class="space-y-2">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">All notices</h2>
            @forelse ($alerts as $a)
                <div class="rounded-xl border border-slate-200/70 bg-white p-3 dark:border-white/10 dark:bg-slate-900/60" wire:key="alert-{{ $a->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <button type="button" wire:click="edit({{ $a->id }})" class="min-w-0 flex-1 text-left">
                            <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $a->title }}</p>
                            <p class="text-xs text-slate-400">{{ ucfirst($a->audience) }} · {{ $a->views_count }} view{{ $a->views_count === 1 ? '' : 's' }} · {{ $a->max_views === 0 ? '∞' : $a->max_views }}x</p>
                        </button>
                        <div class="flex items-center gap-1">
                            <button type="button" wire:click="toggle({{ $a->id }})" class="rounded px-1.5 text-xs font-semibold {{ $a->is_active ? 'text-emerald-600' : 'text-slate-400' }}">{{ $a->is_active ? 'On' : 'Off' }}</button>
                            <button type="button" wire:click="delete({{ $a->id }})" wire:confirm="Delete this notice?" class="text-slate-400 hover:text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">No notices yet.</p>
            @endforelse
        </div>
    </div>

    @once
        <script>
            window.nxRichEditor = function (initial) {
                return {
                    init() {
                        this.$refs.editor.innerHTML = initial || '';
                        // Keep the editor in sync if Livewire resets the form (new/edit).
                        this.$wire.$watch('form.body', (v) => {
                            if (v !== this.$refs.editor.innerHTML) { this.$refs.editor.innerHTML = v || ''; }
                        });
                    },
                    cmd(c) { document.execCommand(c, false, null); this.sync(); this.$refs.editor.focus(); },
                    format(tag) { document.execCommand('formatBlock', false, tag); this.sync(); },
                    link() { const u = prompt('Link URL'); if (u) { document.execCommand('createLink', false, u); this.sync(); } },
                    sync() { this.$wire.set('form.body', this.$refs.editor.innerHTML, false); },
                };
            };
        </script>
    @endonce
</div>
