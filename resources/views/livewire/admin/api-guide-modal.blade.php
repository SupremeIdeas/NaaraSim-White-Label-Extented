<div>
    {{-- Uses the shared modal engine (blueprint Section 31) — focus-trap, ESC,
         backdrop + ARIA come from <x-ui.modal>; this only supplies content. --}}
    <x-ui.modal wire="open" :title="$title" max-width="2xl">
        @if ($content)
            <dl class="space-y-4 text-sm">
                <div>
                    <dt class="font-semibold text-slate-500 dark:text-slate-400">Config key</dt>
                    <dd class="mt-1 rounded-lg bg-slate-100 px-3 py-2 font-mono text-slate-800 dark:bg-[#243352] dark:text-slate-200">{{ $content['config_key'] }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-slate-500 dark:text-slate-400">Where to get it</dt>
                    <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $content['where'] }}</dd>
                </div>
                <div class="flex gap-8">
                    <div>
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">Format</dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $content['format'] }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-500 dark:text-slate-400">Required</dt>
                        <dd class="mt-1">
                            <span @class([
                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $content['required'],
                                'bg-slate-100 text-slate-600 dark:bg-[#243352] dark:text-slate-400' => ! $content['required'],
                            ])>
                                {{ $content['required'] ? 'Required' : 'Optional' }}
                            </span>
                        </dd>
                    </div>
                </div>
            </dl>
        @endif
    </x-ui.modal>
</div>
