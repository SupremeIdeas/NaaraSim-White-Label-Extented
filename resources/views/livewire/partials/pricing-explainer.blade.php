{{-- AI/knowledge pricing education (Module 29). Rendered inline under a plan
     when the visitor taps "What does this mean for me?". --}}
@if (($explanations[$ref] ?? null) && $openExplainer === $ref)
    @php($ex = $explanations[$ref])
    <div class="mt-3 rounded-2xl border border-primary/20 bg-primary/5 p-4 text-left dark:border-primary/30 dark:bg-primary/10">
        <p class="text-sm leading-relaxed text-slate-700 dark:text-slate-200">{{ $ex['summary'] }}</p>
        @if (! empty($ex['tips']))
            <ul class="mt-3 space-y-1.5">
                @foreach ($ex['tips'] as $tip)
                    <li class="flex items-start gap-2 text-xs text-slate-600 dark:text-slate-300">
                        <x-icon name="check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" /> <span>{{ $tip }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="mt-3 text-[10px] uppercase tracking-wide text-slate-400 dark:text-slate-500">
            {{ $ex['ai'] ? 'NaaraSim guide · AI-assisted' : 'NaaraSim guide' }}
        </p>
    </div>
@endif
