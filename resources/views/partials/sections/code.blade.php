@php
    /** Code snippet (Section Builder §2). Escaped, monospace block for docs pages.
     *  No client-side highlighter dependency — escaped text in a styled <pre>. */
    $c = $config ?? [];
    $code = (string) ($c['code'] ?? '');
@endphp

@if (trim($code) !== '')
    <section class="nx-sec-code py-10 sm:py-14">
        <div class="mx-auto max-w-4xl px-4 sm:px-6">
            @if (! empty($c['caption']))
                <p class="mb-2 text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $c['caption'] }}</p>
            @endif
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
                <div class="flex items-center justify-between border-b border-white/10 px-4 py-2">
                    <span class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $c['language'] ?? 'code' }}</span>
                </div>
                <pre class="overflow-x-auto p-4 text-xs leading-relaxed text-slate-100"><code>{{ $code }}</code></pre>
            </div>
        </div>
    </section>
@endif
