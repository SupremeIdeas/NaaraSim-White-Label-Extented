@php $inp = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-white/10 dark:bg-white/5 dark:text-slate-100'; @endphp
<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
    <div class="mb-5">
        <h1 class="text-xl font-bold text-slate-900 dark:text-white">User guides</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Edit the guide + agreement each audience sees in their area. HTML is sanitized on save.</p>
    </div>

    {{-- Audience tabs --}}
    <div class="mb-6 flex flex-wrap gap-2">
        @foreach ($audiences as $a)
            <button type="button" wire:click="selectAudience('{{ $a }}')"
                @class(['rounded-full px-3.5 py-1.5 text-sm font-medium transition', 'bg-primary text-white' => $audience === $a, 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300' => $audience !== $a])>
                {{ \App\Support\UserGuides::label($a) }}
            </button>
        @endforeach
    </div>

    <div class="space-y-5">
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Title</label>
            <input type="text" wire:model="title" class="{{ $inp }}">
            @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <label class="mb-1 mt-3 block text-xs font-semibold text-slate-500 dark:text-slate-400">Intro</label>
            <textarea wire:model="intro" rows="2" class="{{ $inp }}"></textarea>
        </div>

        {{-- Sections --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Sections</h2>
                <button type="button" wire:click="addSection" class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary dark:text-teal-300">+ Section</button>
            </div>
            <div class="space-y-3">
                @foreach ($sections as $i => $section)
                    <div class="rounded-xl border border-slate-200/70 p-3 dark:border-white/10" wire:key="gs-{{ $i }}">
                        <div class="mb-2 flex items-center justify-between">
                            <input type="text" wire:model="sections.{{ $i }}.heading" placeholder="Heading" class="{{ $inp }} mr-2">
                            <button type="button" wire:click="removeSection({{ $i }})" class="shrink-0 text-slate-400 hover:text-red-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                        </div>
                        <textarea wire:model="sections.{{ $i }}.body" rows="3" placeholder="Body (HTML: p, ul/li, strong, a…)" class="{{ $inp }} font-mono text-xs"></textarea>
                        <div class="mt-2 flex items-center gap-2">
                            @if (! empty($section['image']))
                                <img src="{{ $section['image'] }}" class="h-10 w-16 rounded object-cover">
                                <button type="button" wire:click="clearSectionImage({{ $i }})" class="text-xs text-slate-400 hover:text-red-600">Remove</button>
                            @endif
                            <input type="file" wire:model="sectionImage" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-primary/10 file:px-2 file:py-1 file:text-xs file:text-primary dark:text-slate-400">
                            <button type="button" wire:click="uploadSectionImage({{ $i }})" class="shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary dark:text-teal-300">Set image</button>
                        </div>
                    </div>
                @endforeach
                @if (empty($sections))<p class="text-sm text-slate-400">No sections yet.</p>@endif
            </div>
        </div>

        {{-- Agreement --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 dark:border-white/10 dark:bg-slate-900/60">
            <label class="mb-1 block text-xs font-semibold text-slate-500 dark:text-slate-400">Agreement &amp; policy (HTML — pricing promise, rules, one-account warning)</label>
            <textarea wire:model="agreement" rows="12" class="{{ $inp }} font-mono text-xs"></textarea>
        </div>

        <button type="button" wire:click="save" class="w-full rounded-full bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:bg-primary-dark">Save {{ \App\Support\UserGuides::label($audience) }}</button>
    </div>
</div>
