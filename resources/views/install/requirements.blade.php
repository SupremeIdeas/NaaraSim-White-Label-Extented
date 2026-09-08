<x-layouts.install title="Install — Server Requirements">
    <x-install-shell :step="2">
        <h2 class="mb-4 text-center text-lg font-bold text-slate-900 dark:text-slate-100">Server Requirements</h2>
        <ul class="space-y-2">
            @foreach ($requirements as $req)
                <li class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 text-sm dark:border-[#243352]">
                    <span class="text-slate-700 dark:text-slate-200">{{ $req['label'] }}
                        @unless ($req['required']) <span class="text-xs text-slate-400">(optional)</span> @endunless
                    </span>
                    @if ($req['ok'])
                        <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400"><x-icon name="check" class="h-4 w-4" /></span>
                    @else
                        <span class="inline-flex items-center gap-1 {{ $req['required'] ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400' }}"><x-icon name="x" class="h-4 w-4" /></span>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="mt-6 flex items-center justify-between">
            <a href="/install" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary dark:text-slate-400">
                <x-icon name="chevron-right" class="h-4 w-4 rotate-180" /> Back
            </a>
            @if ($pass)
                <a href="/install/setup" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 font-semibold text-white hover:bg-primary-dark">
                    Next <x-icon name="chevron-right" class="h-4 w-4" />
                </a>
            @else
                <span class="rounded-lg bg-slate-100 px-4 py-2.5 font-semibold text-slate-400 dark:bg-[#243352]">Fix the required items</span>
            @endif
        </div>
    </x-install-shell>
</x-layouts.install>
