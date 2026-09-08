{{-- Hero flag orbit: floating flag + comms-icon nodes joined by animated gold
     "signal" lines, layered over the WebGL hero (and the login panel) to carry
     the "connect across borders" message. Purely decorative; pointer-none so it
     never blocks clicks. Positions are % so it scales to any container.
     Props: tone = 'light' (marketing hero) | 'dark' (navy auth panel). --}}
@props(['tone' => 'light'])
@php
    // 7 popular flags + a message + a call node, kept to the periphery so the
    // hero copy stays clear. [code|icon, top%, left%]
    $nodes = [
        ['ng', 16, 9], ['gb', 10, 82], ['us', 44, 4],
        ['jp', 38, 93], ['br', 78, 16], ['ae', 80, 84],
        ['in', 30, 62],
        ['icon:message-circle', 66, 30], ['icon:phone', 62, 72],
    ];
    // Faint network between node indices.
    $links = [[0,2],[2,7],[7,4],[1,3],[3,8],[8,6],[6,1],[0,7],[3,6],[4,8]];
    $lineColor = $tone === 'dark' ? '#D4A017' : '#0A6E6E';
    $chip = $tone === 'dark'
        ? 'border-white/15 bg-white/10 text-white'
        : 'border-slate-200/70 bg-white/80 text-slate-700 dark:border-white/10 dark:bg-white/10 dark:text-slate-200';
@endphp
<div {{ $attributes->merge(['class' => 'pointer-events-none absolute inset-0 overflow-hidden']) }} aria-hidden="true">
    <svg class="absolute inset-0 h-full w-full" preserveAspectRatio="none" viewBox="0 0 100 100">
        @foreach ($links as [$a, $b])
            <line class="nx-orbit__line" x1="{{ $nodes[$a][2] }}" y1="{{ $nodes[$a][1] }}" x2="{{ $nodes[$b][2] }}" y2="{{ $nodes[$b][1] }}"
                  stroke="{{ $lineColor }}" stroke-width="0.18" opacity="0.35" vector-effect="non-scaling-stroke" style="animation-delay: {{ $loop->index * 0.3 }}s" />
        @endforeach
    </svg>
    @foreach ($nodes as $node)
        <div class="nx-orbit__node absolute -translate-x-1/2 -translate-y-1/2" style="top: {{ $node[1] }}%; left: {{ $node[2] }}%">
            @if (str_starts_with($node[0], 'icon:'))
                <span class="flex h-9 w-9 items-center justify-center rounded-full border shadow-lg backdrop-blur {{ $tone === 'dark' ? 'border-accent/40 bg-accent/20 text-accent' : 'border-primary/30 bg-white/90 text-primary shadow-primary/20 dark:bg-primary/20 dark:text-teal-300' }}">
                    <x-icon name="{{ substr($node[0], 5) }}" class="h-4 w-4" />
                </span>
            @else
                <span class="flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 shadow-lg backdrop-blur {{ $chip }}">
                    <span class="fi fi-{{ $node[0] }} h-4 w-6 rounded-[2px] bg-cover ring-1 ring-black/10" role="img"></span>
                </span>
            @endif
        </div>
    @endforeach
</div>
